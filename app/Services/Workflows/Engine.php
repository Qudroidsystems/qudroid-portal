<?php

namespace App\Services\Workflows;

use App\Models\Finance\Invoice;
use App\Models\Projects\Task;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowRun;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Runs workflows.
 *
 * A record change (task added, deal won...) fires its trigger; each switched-on workflow for
 * it whose conditions pass gets a run row straight away, and the steps run after the
 * response is sent, so a slow webhook never slows a click. `workflows:tick` (every minute)
 * resumes runs that waited, picks up any run left behind, checks for overdue tasks and
 * invoices, and starts scheduled workflows.
 *
 * A workflow's steps can change records and so fire other workflows; that chain stops
 * after three levels and never loops back into a workflow already running in it.
 */
class Engine
{
    public const MAX_DEPTH = 3;

    protected static array $queue = [];
    protected static bool $hooked = false;
    protected static int $depth = 0;
    protected static array $chain = [];
    protected static ?bool $ready = null;

    /** Hook the model events. Called once from AppServiceProvider. */
    public static function listen(): void
    {
        foreach (Triggers::listeners() as $class => $events) {
            foreach ($events as $event => $keys) {
                call_user_func([$class, $event], function (Model $m) use ($keys) {
                    foreach ($keys as $key) self::modelEvent($key, $m);
                });
            }
        }
        Workflow::saved(fn () => self::forget());
        Workflow::deleted(fn () => self::forget());
    }

    public static function forget(): void
    {
        Cache::forget('workflows.active');
    }

    /** Trigger keys with at least one switched-on workflow (cached, so ordinary saves cost nothing). */
    public static function activeTriggers(): array
    {
        if (self::$ready === null) {
            try { self::$ready = Schema::hasTable('workflows'); } catch (\Throwable $e) { self::$ready = false; }
        }
        if (!self::$ready) return [];

        return Cache::rememberForever('workflows.active', fn () => Workflow::where('is_active', true)->distinct()->pluck('trigger')->all());
    }

    protected static function modelEvent(string $key, Model $m): void
    {
        if (!in_array($key, self::activeTriggers(), true)) return;
        $t = Triggers::get($key);
        try {
            if ($t['when'] && !($t['when'])($m)) return;
            self::fire($key, $m);
        } catch (\Throwable $e) {
            // An automation must never break the save that fired it.
            Log::warning('Workflow trigger failed', ['trigger' => $key, 'error' => $e->getMessage()]);
        }
    }

    /** Start every matching workflow for this trigger. Returns how many runs began. */
    public static function fire(string $key, ?Model $subject = null, ?string $dedupe = null): int
    {
        if (self::$depth >= self::MAX_DEPTH) return 0;
        $workflows = Workflow::where('is_active', true)->where('trigger', $key)->whereNotIn('id', self::$chain ?: [0])->get();
        if ($workflows->isEmpty()) return 0;
        $payload = Triggers::payload($key, $subject);
        $n = 0;
        foreach ($workflows as $wf) if (self::start($wf, $subject, $payload, $dedupe)) $n++;

        return $n;
    }

    public static function start(Workflow $wf, ?Model $subject, array $payload, ?string $dedupe = null): ?WorkflowRun
    {
        if (!Steps::passes($wf->conditions(), $payload)) return null;
        try {
            $run = WorkflowRun::create([
                'workflow_id' => $wf->id, 'status' => 'queued',
                'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(),
                'title' => mb_substr((string) ($payload['_title'] ?? ''), 0, 200) ?: null,
                'payload' => $payload, 'dedupe' => $dedupe, 'depth' => self::$depth, 'log' => [],
            ]);
        } catch (QueryException $e) {
            // Same dedupe key: this one already ran (an overdue task, today's schedule).
            if ($dedupe) return null;
            throw $e;
        }
        $wf->forceFill(['last_run_at' => now(), 'runs_count' => $wf->runs_count + 1])->saveQuietly();
        self::$queue[$run->id] = true;
        if (!self::$hooked) {
            self::$hooked = true;
            app()->terminating(fn () => self::flush());
        }

        return $run;
    }

    /** Run the queued runs now (after the response, or straight away in tests and commands). */
    public static function flush(): void
    {
        while (self::$queue) {
            $ids = array_keys(self::$queue);
            self::$queue = [];
            self::$hooked = false;
            foreach (WorkflowRun::whereIn('id', $ids)->get() as $run) self::process($run);
        }
    }

    public static function pending(): array
    {
        return array_keys(self::$queue);
    }

    public static function process(WorkflowRun $run): void
    {
        $claimed = WorkflowRun::whereKey($run->id)->whereIn('status', ['queued', 'waiting'])
            ->update(['status' => 'running', 'resume_at' => null, 'updated_at' => now()]);
        if (!$claimed) return;
        $run->refresh();
        $wf = $run->workflow;
        if (!$wf) return;

        $payload = (array) $run->payload;
        $log = (array) $run->log;
        // After a wait, read the record again so "if it's still unpaid" sees today's state.
        if ($run->step > 0 && $run->subject) {
            $payload = Triggers::payload($wf->trigger, $run->subject) + $payload;
        }

        $outerDepth = self::$depth;
        $outerChain = self::$chain;
        self::$depth = $run->depth + 1;
        self::$chain[] = $wf->id;
        $steps = $wf->steps();
        try {
            for ($i = $run->step; $i < count($steps); $i++) {
                try {
                    $r = Steps::run($steps[$i], $wf, $payload, $run);
                } catch (ValidationException $e) {
                    $r = ['ok' => false, 'note' => collect($e->errors())->flatten()->first() ?? $e->getMessage()];
                } catch (\Throwable $e) {
                    Log::warning('Workflow step failed', ['workflow' => $wf->id, 'run' => $run->id, 'step' => $i, 'error' => $e->getMessage()]);
                    $r = ['ok' => false, 'note' => 'Something went wrong: ' . mb_strimwidth($e->getMessage(), 0, 200, '…')];
                }
                $log[] = ['step' => $i, 'type' => $steps[$i]['type'] ?? '', 'ok' => $r['ok'], 'note' => $r['note'], 'url' => $r['url'] ?? null, 'at' => now()->toIso8601String()];

                if (!$r['ok']) {
                    $run->update(['status' => 'failed', 'step' => $i, 'log' => $log, 'error' => mb_substr($r['note'], 0, 500), 'payload' => $payload, 'finished_at' => now()]);

                    return;
                }
                if (!empty($r['stop'])) {
                    $run->update(['status' => 'stopped', 'step' => $i + 1, 'log' => $log, 'payload' => $payload, 'finished_at' => now()]);

                    return;
                }
                if (!empty($r['wait'])) {
                    $run->update(['status' => 'waiting', 'step' => $i + 1, 'resume_at' => $r['wait'], 'log' => $log, 'payload' => $payload]);

                    return;
                }
            }
            $run->update(['status' => 'done', 'step' => count($steps), 'log' => $log, 'payload' => $payload, 'finished_at' => now()]);
        } finally {
            self::$depth = $outerDepth;
            self::$chain = $outerChain;
        }
    }

    /**
     * What the workflow would do with a real recent record, without doing it.
     *
     * @return array{record: ?string, passes: bool, conditions: string, steps: array}
     */
    public static function preview(Workflow $wf): array
    {
        $t = Triggers::get($wf->trigger);
        $subject = $t && $t['model'] ? self::sampleFor($wf->trigger) : null;
        $payload = Triggers::payload($wf->trigger, $subject);
        $passes = Steps::passes($wf->conditions(), $payload);
        $steps = [];
        foreach ($wf->steps() as $i => $s) {
            try {
                $r = Steps::run($s, $wf, $payload, null, true);
            } catch (\Throwable $e) {
                $r = ['ok' => false, 'note' => $e->getMessage()];
            }
            $steps[] = ['type' => $s['type'] ?? '', 'ok' => $r['ok'], 'note' => $r['note']];
            if (!empty($r['stop']) || !$r['ok']) break;
        }

        return [
            'record' => $subject ? ($payload['_title'] ?? null) : null,
            'needs_record' => $t && $t['model'] && !$subject,
            'passes' => $passes,
            'conditions' => Steps::describeRules($wf->conditions(), $t),
            'steps' => $steps,
        ];
    }

    protected static function sampleFor(string $key): ?Model
    {
        $t = Triggers::get($key);
        $q = ($t['model'])::query()->latest('id');
        if ($key === 'message.channel') $q->whereHas('conversation', fn ($c) => $c->where('type', 'channel'))->whereNull('thread_id')->where('is_system', false);
        if ($key === 'task.overdue') $q->where('status', '!=', 'done')->whereNotNull('due_on');
        if (in_array($key, ['deal.won', 'deal.lost'], true)) $q->orderByRaw('stage = ? desc', [substr($key, 5)]);

        return $q->first();
    }

    // ── The every-minute check ──────────────────────────────────────────

    public static function tick(?Carbon $now = null): array
    {
        $now ??= now();
        $done = ['resumed' => 0, 'leftover' => 0, 'scanned' => 0, 'scheduled' => 0];
        $active = self::activeTriggers();

        foreach (WorkflowRun::where('status', 'waiting')->where('resume_at', '<=', $now)->orderBy('resume_at')->limit(200)->get() as $run) {
            self::process($run);
            $done['resumed']++;
        }
        foreach (WorkflowRun::where('status', 'queued')->where('created_at', '<=', $now->copy()->subMinute())->limit(200)->get() as $run) {
            self::process($run);
            $done['leftover']++;
        }

        if (in_array('task.overdue', $active, true)) {
            Task::where('status', '!=', 'done')->whereNotNull('due_on')->whereDate('due_on', '<', $now->toDateString())
                ->with('project')->orderBy('id')->limit(500)->get()
                ->each(function (Task $t) use (&$done) { $done['scanned'] += self::fire('task.overdue', $t, 'task:' . $t->id . ':' . $t->due_on?->toDateString()); });
        }
        if (in_array('invoice.overdue', $active, true)) {
            Invoice::whereIn('status', ['issued', 'partially_paid'])->whereNotNull('due_date')->whereDate('due_date', '<', $now->toDateString())
                ->orderBy('id')->limit(500)->get()
                ->each(function (Invoice $i) use (&$done) { $done['scanned'] += self::fire('invoice.overdue', $i, 'invoice:' . $i->id . ':' . \Illuminate\Support\Carbon::parse($i->due_date)->toDateString()); });
        }

        foreach (Workflow::where('is_active', true)->whereIn('trigger', ['schedule.daily', 'schedule.weekly', 'schedule.monthly'])->get() as $wf) {
            if ($slot = self::dueSlot($wf, $now)) {
                if (self::start($wf, null, Triggers::payload($wf->trigger, null), $slot)) $done['scheduled']++;
            }
        }
        self::flush();

        return $done;
    }

    /** The schedule slot (its dedupe key) when this scheduled workflow should run now, else null. */
    public static function dueSlot(Workflow $wf, Carbon $now): ?string
    {
        $c = $wf->triggerConfig();
        [$h, $m] = array_map('intval', explode(':', preg_match('/^\d{1,2}:\d{2}$/', (string) ($c['time'] ?? '')) ? $c['time'] : '08:00'));
        $slot = $now->copy()->setTime($h, $m);
        // Run in the two hours after the time, never on catch-up from days ago.
        if ($now->lt($slot) || $now->gt($slot->copy()->addHours(2))) return null;

        return match ($wf->trigger) {
            'schedule.daily' => !empty($c['weekdays_only']) && $now->isWeekend() ? null : 'day:' . $now->toDateString(),
            'schedule.weekly' => (int) $now->isoWeekday() === (int) ($c['weekday'] ?? 1) ? 'week:' . $now->toDateString() : null,
            'schedule.monthly' => (int) $now->day === max(1, min(28, (int) ($c['day'] ?? 1))) ? 'month:' . $now->toDateString() : null,
            default => null,
        };
    }
}
