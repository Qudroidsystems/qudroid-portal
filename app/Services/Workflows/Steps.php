<?php

namespace App\Services\Workflows;

use App\Mail\WorkflowMail;
use App\Models\Connect\Conversation;
use App\Models\Projects\Project;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowRun;
use App\Services\Connect\ConnectService;
use App\Services\Messaging\PortalNotifier;
use App\Services\Projects\ProjectService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * The steps a workflow can take, the "only if" rules, and {{placeholder}} filling.
 *
 * run() does a step for real. With $dry it only says what it would do, for the
 * builder's preview, so trying a workflow never e-mails a customer by accident.
 */
class Steps
{
    public const TYPES = [
        'notify' => ['Send a notification', 'ri-notification-3-line', 'The bell in the portal and a push to the phone app.'],
        'email' => ['Send an e-mail', 'ri-mail-send-line', 'To people on the record, staff or roles, or any e-mail address.'],
        'chat' => ['Post in chat', 'ri-chat-smile-3-line', 'A message in a channel or group, posted as you.'],
        'task' => ['Create a task', 'ri-task-line', 'A new task in a project, optionally assigned.'],
        'webhook' => ['Call a webhook', 'ri-links-line', 'Send the details to another app (Zapier, Make, your own server).'],
        'wait' => ['Wait', 'ri-hourglass-line', 'Pause, then carry on with fresh details.'],
        'filter' => ['Only continue if', 'ri-filter-3-line', 'Stop here unless these are true.'],
    ];

    public const OPS = [
        'is' => 'is', 'is_not' => 'is not', 'contains' => 'contains', 'not_contains' => 'does not contain',
        'gt' => 'is more than', 'lt' => 'is less than', 'empty' => 'is empty', 'not_empty' => 'is not empty',
    ];

    public const UNITS = ['minutes' => 'minutes', 'hours' => 'hours', 'days' => 'days'];

    /** Bell notification kinds, so people can still mute a kind they don't want. */
    public const NOTIFY_TYPES = ['projects' => 'task', 'crm' => 'client', 'finance' => 'invoice', 'academy' => 'course', 'hr' => 'leave',
        'inventory' => 'inventory', 'meetings' => 'meeting', 'connect' => 'message', 'schedule' => 'reminder'];

    // ── Placeholders and rules ──────────────────────────────────────────

    public static function fill(?string $text, array $payload, ?array $trigger): string
    {
        $fields = $trigger['fields'] ?? [];

        return (string) preg_replace_callback('/\{\{\s*([a-z_]+(?:\.[a-z_]+)?)\s*\}\}/i', function ($m) use ($payload, $fields) {
            $key = strtolower($m[1]);
            if ($key === 'record.link') return (string) ($payload['_url'] ?? '');
            if ($key === 'record.title') return (string) ($payload['_title'] ?? '');
            $v = $payload[$key] ?? '';
            $options = $fields[$key][1] ?? null;
            if ($options && is_scalar($v) && isset($options[$v])) return (string) $options[$v];

            return is_scalar($v) ? (string) $v : '';
        }, (string) $text);
    }

    public static function passes(array $conditions, array $payload): bool
    {
        $rules = array_values(array_filter((array) ($conditions['rules'] ?? []), fn ($r) => !empty($r['field'])));
        if (!$rules) return true;
        $results = array_map(fn ($r) => self::test($r, $payload), $rules);

        return ($conditions['match'] ?? 'all') === 'any' ? in_array(true, $results, true) : !in_array(false, $results, true);
    }

    protected static function test(array $rule, array $payload): bool
    {
        $have = $payload[$rule['field']] ?? null;
        $have = is_scalar($have) ? trim((string) $have) : '';
        $want = trim((string) ($rule['value'] ?? ''));
        $num = fn ($v) => (float) preg_replace('/[^0-9.\-]/', '', $v);

        return match ($rule['op'] ?? 'is') {
            'is' => mb_strtolower($have) === mb_strtolower($want),
            'is_not' => mb_strtolower($have) !== mb_strtolower($want),
            'contains' => $want !== '' && str_contains(mb_strtolower($have), mb_strtolower($want)),
            'not_contains' => $want === '' || !str_contains(mb_strtolower($have), mb_strtolower($want)),
            'gt' => $have !== '' && $num($have) > $num($want),
            'lt' => $have !== '' && $num($have) < $num($want),
            'empty' => $have === '',
            'not_empty' => $have !== '',
            default => false,
        };
    }

    public static function describeRules(array $conditions, ?array $trigger): string
    {
        $fields = $trigger['fields'] ?? [];
        $parts = [];
        foreach ((array) ($conditions['rules'] ?? []) as $r) {
            if (empty($r['field'])) continue;
            $label = $fields[$r['field']][0] ?? $r['field'];
            $op = self::OPS[$r['op'] ?? 'is'] ?? 'is';
            $value = in_array($r['op'] ?? '', ['empty', 'not_empty'], true) ? '' : ' "' . (($fields[$r['field']][1] ?? [])[$r['value'] ?? ''] ?? ($r['value'] ?? '')) . '"';
            $parts[] = $label . ' ' . $op . $value;
        }

        return implode(($conditions['match'] ?? 'all') === 'any' ? ' or ' : ' and ', $parts);
    }

    // ── People ──────────────────────────────────────────────────────────

    /** @return User[] active accounts the step sends to */
    public static function recipients(array $to, array $payload): array
    {
        $ids = array_map('intval', (array) ($to['users'] ?? []));
        foreach ((array) ($to['people'] ?? []) as $key) {
            if ($id = $payload['_people'][$key] ?? null) $ids[] = (int) $id;
        }
        $roles = array_filter((array) ($to['roles'] ?? []));
        $q = User::query()->where('is_disabled', false);
        $q->where(function ($w) use ($ids, $roles) {
            $w->whereIn('id', $ids ?: [0]);
            if ($roles) $w->orWhereHas('roles', fn ($r) => $r->whereIn('name', $roles));
        });

        return $q->limit(500)->get()->all();
    }

    // ── Running ─────────────────────────────────────────────────────────

    /**
     * @return array{ok: bool, note: string, wait?: \Illuminate\Support\Carbon, stop?: bool}
     */
    public static function run(array $step, Workflow $wf, array $payload, ?WorkflowRun $run = null, bool $dry = false): array
    {
        $c = (array) ($step['config'] ?? []);
        $trigger = Triggers::get($wf->trigger);

        return match ($step['type'] ?? '') {
            'notify' => self::notify($c, $wf, $payload, $trigger, $dry),
            'email' => self::email($c, $wf, $payload, $trigger, $dry),
            'chat' => self::chat($c, $wf, $payload, $trigger, $dry),
            'task' => self::task($c, $wf, $payload, $trigger, $dry),
            'webhook' => self::webhook($c, $wf, $payload, $run, $dry),
            'wait' => self::wait($c, $dry),
            'filter' => self::passes($c, $payload)
                ? ['ok' => true, 'note' => 'Carried on: ' . (self::describeRules($c, $trigger) ?: 'no rules')]
                : ['ok' => true, 'stop' => true, 'note' => 'Stopped: not true that ' . self::describeRules($c, $trigger)],
            default => ['ok' => false, 'note' => 'Unknown step.'],
        };
    }

    protected static function names(array $users): string
    {
        $names = array_map(fn ($u) => $u->name, array_slice($users, 0, 6));

        return implode(', ', $names) . (count($users) > 6 ? ' and ' . (count($users) - 6) . ' more' : '');
    }

    protected static function notify(array $c, Workflow $wf, array $payload, ?array $trigger, bool $dry): array
    {
        $users = self::recipients((array) ($c['to'] ?? []), $payload);
        if (!$users) return ['ok' => true, 'note' => 'Nobody to notify (no one matched).'];
        $title = mb_substr(self::fill($c['title'] ?? '', $payload, $trigger) ?: $wf->name, 0, 150);
        $body = self::fill($c['message'] ?? '', $payload, $trigger);
        $link = trim(self::fill($c['link'] ?? '', $payload, $trigger)) ?: ($payload['_url'] ?? null);
        if ($dry) return ['ok' => true, 'note' => 'Would notify ' . self::names($users) . ': "' . $title . '" ' . $body];

        $type = self::NOTIFY_TYPES[$trigger['group'] ?? ''] ?? 'system';
        $sent = PortalNotifier::toUsers(array_map(fn ($u) => $u->id, $users), $title, $body, $link, $type);

        return ['ok' => true, 'note' => 'Notified ' . self::names($users) . ($sent < count($users) ? ' (' . (count($users) - $sent) . ' have these muted)' : '') . '.'];
    }

    protected static function email(array $c, Workflow $wf, array $payload, ?array $trigger, bool $dry): array
    {
        $addresses = [];
        foreach (self::recipients((array) ($c['to'] ?? []), $payload) as $u) if ($u->email) $addresses[mb_strtolower($u->email)] = $u->name;
        foreach (preg_split('/[\s,;]+/', self::fill($c['emails'] ?? '', $payload, $trigger)) as $e) {
            $e = trim($e);
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) $addresses[mb_strtolower($e)] ??= null;
        }
        $addresses = array_slice($addresses, 0, 50, true);
        if (!$addresses) return ['ok' => true, 'note' => 'Nobody to e-mail (no addresses).'];
        $subject = mb_substr(self::fill($c['subject'] ?? '', $payload, $trigger) ?: $wf->name, 0, 200);
        $body = self::fill($c['body'] ?? '', $payload, $trigger);
        $list = implode(', ', array_slice(array_keys($addresses), 0, 6)) . (count($addresses) > 6 ? ' and ' . (count($addresses) - 6) . ' more' : '');
        if ($dry) return ['ok' => true, 'note' => 'Would e-mail ' . $list . ': "' . $subject . '"'];

        $link = $payload['_url'] ?? null;
        foreach ($addresses as $email => $name) {
            // One e-mail each, so nobody sees the other addresses.
            Mail::to($email, $name)->send(new WorkflowMail($subject, $body, !empty($c['include_link']) ? $link : null, $name));
        }

        return ['ok' => true, 'note' => 'E-mailed ' . $list . '.'];
    }

    protected static function chat(array $c, Workflow $wf, array $payload, ?array $trigger, bool $dry): array
    {
        $conversation = Conversation::whereIn('type', ['channel', 'group'])->find((int) ($c['conversation_id'] ?? 0));
        if (!$conversation) return ['ok' => false, 'note' => 'The chat to post in no longer exists.'];
        $owner = $wf->creator;
        if (!$owner || $owner->is_disabled) return ['ok' => false, 'note' => 'Whoever built this workflow can no longer sign in, so it cannot post as them.'];
        $text = trim(self::fill($c['message'] ?? '', $payload, $trigger));
        if (!empty($c['include_link']) && !empty($payload['_url'])) $text .= "\n" . $payload['_url'];
        $where = ($conversation->type === 'channel' ? '#' : '') . $conversation->name;
        if ($text === '') return ['ok' => true, 'note' => 'Nothing to post (the message was empty).'];
        if ($dry) return ['ok' => true, 'note' => 'Would post in ' . $where . ' as ' . $owner->name . ': ' . mb_strimwidth($text, 0, 200, '…')];

        $svc = app(ConnectService::class);
        if ($conversation->type === 'channel' && !$svc->participant($conversation, $owner)) $svc->join($conversation, $owner);
        $svc->send($conversation, $owner, mb_substr($text, 0, 5000));

        return ['ok' => true, 'note' => 'Posted in ' . $where . '.'];
    }

    protected static function task(array $c, Workflow $wf, array $payload, ?array $trigger, bool $dry): array
    {
        $projectId = ($c['project'] ?? 'record') === 'record' ? ($payload['_project_id'] ?? null) : (int) $c['project'];
        $project = $projectId ? Project::find($projectId) : null;
        if (!$project) return ['ok' => false, 'note' => 'No project to add the task to.'];
        $owner = $wf->creator;
        if (!$owner || $owner->is_disabled) return ['ok' => false, 'note' => 'Whoever built this workflow can no longer sign in, so the task has no creator.'];

        $title = mb_substr(trim(self::fill($c['title'] ?? '', $payload, $trigger)), 0, 200) ?: 'Follow up: ' . ($payload['_title'] ?? $wf->name);
        $assignee = null;
        $a = (string) ($c['assignee'] ?? '');
        if (str_starts_with($a, 'person:')) $assignee = $payload['_people'][substr($a, 7)] ?? null;
        if (str_starts_with($a, 'user:')) $assignee = (int) substr($a, 5);
        $days = max(0, min(365, (int) ($c['due_days'] ?? 0)));
        $data = [
            'title' => $title, 'description' => self::fill($c['description'] ?? '', $payload, $trigger) ?: null,
            'priority' => array_key_exists($c['priority'] ?? '', Project::PRIORITIES) ? $c['priority'] : 'normal',
            'due_on' => !empty($c['due_days']) || ($c['due_days'] ?? '') === '0' ? today()->addDays($days)->toDateString() : null,
            'assignee_id' => $assignee ?: null,
        ];
        $who = $assignee ? User::find($assignee)?->name : null;
        if ($dry) return ['ok' => true, 'note' => 'Would add "' . $title . '" to ' . $project->code . ($who ? ', assigned to ' . $who : '') . '.'];

        $svc = app(ProjectService::class);
        $note = '';
        try {
            $task = $svc->createTask($project, $data, $owner);
        } catch (ValidationException $e) {
            // Usually the assignee isn't on the project: add the task anyway, unassigned.
            if (!$data['assignee_id']) throw $e;
            $task = $svc->createTask($project, ['assignee_id' => null] + $data, $owner);
            $note = ' (' . ($who ?? 'The assignee') . ' is not on this project, so it is unassigned)';
        }

        return ['ok' => true, 'note' => 'Added ' . $task->key_label . ' "' . $task->title . '"' . $note . '.', 'url' => route('projects.tasks.show', [$project, $task->number])];
    }

    /** Only public web addresses: never this server or the private network. */
    public static function safeUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) return false;
        $host = strtolower(trim($parts['host'], '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) return false;
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (app()->runningUnitTests() ? [] : (gethostbynamel($host) ?: []));
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        }

        return true;
    }

    protected static function webhook(array $c, Workflow $wf, array $payload, ?WorkflowRun $run, bool $dry): array
    {
        $url = trim((string) ($c['url'] ?? ''));
        if (!self::safeUrl($url)) return ['ok' => false, 'note' => 'The webhook address must be a public http(s) address.'];
        if ($dry) return ['ok' => true, 'note' => 'Would send the details to ' . parse_url($url, PHP_URL_HOST) . '.'];

        $data = array_filter($payload, fn ($k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
        $body = ['workflow' => ['id' => $wf->id, 'name' => $wf->name], 'trigger' => $wf->trigger, 'run_id' => $run?->id,
            'record' => ['title' => $payload['_title'] ?? null, 'url' => $payload['_url'] ?? null], 'data' => $data, 'sent_at' => now()->toIso8601String()];
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type' => 'application/json', 'User-Agent' => 'Qudroid-Workflows/1'];
        if ($secret = (string) ($c['secret'] ?? '')) $headers['X-Qudroid-Signature'] = 'sha256=' . hash_hmac('sha256', $json, $secret);

        $res = Http::timeout(10)->withOptions(['allow_redirects' => false])->withHeaders($headers)->withBody($json, 'application/json')->post($url);
        if (!$res->successful()) return ['ok' => false, 'note' => 'The webhook answered ' . $res->status() . '.'];

        return ['ok' => true, 'note' => 'Sent to ' . parse_url($url, PHP_URL_HOST) . ' (' . $res->status() . ').'];
    }

    protected static function wait(array $c, bool $dry = false): array
    {
        $n = max(1, min(365, (int) ($c['amount'] ?? 1)));
        $unit = array_key_exists($c['unit'] ?? '', self::UNITS) ? $c['unit'] : 'hours';
        $until = match ($unit) { 'minutes' => now()->addMinutes($n), 'days' => now()->addDays($n), default => now()->addHours($n) };

        return ['ok' => true, 'wait' => $until, 'note' => ($dry ? 'Would wait ' : 'Waiting ') . $n . ' ' . ($n === 1 ? rtrim($unit, 's') : $unit) . ', until ' . $until->format('D j M, g:ia') . '.'];
    }
}
