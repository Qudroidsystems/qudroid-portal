<?php

namespace App\Services\Workflows;

use App\Models\Connect\Conversation;
use App\Models\Projects\Project;
use Illuminate\Validation\ValidationException;

/**
 * Checks and tidies what the builder sends, so a saved workflow only holds known
 * triggers, steps and settings.
 */
class Definition
{
    public const MAX_STEPS = 20;

    public static function clean(mixed $raw): array
    {
        $d = is_string($raw) ? json_decode($raw, true) : $raw;
        $fail = fn (string $msg) => throw ValidationException::withMessages(['definition' => $msg]);
        if (!is_array($d)) $fail('The workflow could not be read. Reload the page and try again.');

        $key = (string) ($d['trigger']['key'] ?? '');
        $t = Triggers::get($key) ?? $fail('Choose what starts the workflow.');
        $fields = array_keys($t['fields']);
        $people = array_keys($t['people']);

        $config = [];
        foreach ($t['config'] as $name => $spec) {
            $v = $d['trigger']['config'][$name] ?? $spec[2];
            $config[$name] = match ($spec[1]) {
                'time' => preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', (string) $v) ? (string) $v : $fail('Enter a time such as 08:00.'),
                'bool' => (bool) $v,
                'number' => max($spec[3] ?? 1, min($spec[4] ?? 28, (int) $v)),
                'select' => array_key_exists((string) $v, $spec[3]) ? (string) $v : (string) $spec[2],
                default => mb_substr((string) $v, 0, 200),
            };
        }

        $rules = fn ($c) => [
            'match' => ($c['match'] ?? 'all') === 'any' ? 'any' : 'all',
            'rules' => array_values(array_map(fn ($r) => [
                'field' => (string) $r['field'], 'op' => array_key_exists($r['op'] ?? '', Steps::OPS) ? $r['op'] : 'is', 'value' => mb_substr((string) ($r['value'] ?? ''), 0, 200),
            ], array_filter(array_slice((array) ($c['rules'] ?? []), 0, 20), fn ($r) => is_array($r) && in_array($r['field'] ?? '', $fields, true)))),
        ];

        $text = fn ($v, $max = 2000) => mb_substr(trim((string) $v), 0, $max);
        $to = function ($c) use ($people) {
            return [
                'people' => array_values(array_intersect((array) ($c['people'] ?? []), $people)),
                'users' => array_values(array_unique(array_map('intval', array_slice((array) ($c['users'] ?? []), 0, 100)))),
                'roles' => array_values(array_map('strval', array_slice((array) ($c['roles'] ?? []), 0, 20))),
            ];
        };
        $hasTo = fn ($c) => $c['people'] || $c['users'] || $c['roles'];

        $steps = [];
        $raw = array_values((array) ($d['steps'] ?? []));
        if (!$raw) $fail('Add at least one step.');
        if (count($raw) > self::MAX_STEPS) $fail('A workflow can have up to ' . self::MAX_STEPS . ' steps.');
        foreach ($raw as $i => $s) {
            $n = $i + 1;
            $type = (string) ($s['type'] ?? '');
            $c = (array) ($s['config'] ?? []);
            if (!isset(Steps::TYPES[$type])) $fail("Step {$n} is not a known step.");
            $clean = match ($type) {
                'notify' => ['to' => $to($c['to'] ?? []), 'title' => $text($c['title'] ?? '', 150), 'message' => $text($c['message'] ?? '', 1000), 'link' => $text($c['link'] ?? '', 500)],
                'email' => ['to' => $to($c['to'] ?? []), 'emails' => $text($c['emails'] ?? '', 1000), 'subject' => $text($c['subject'] ?? '', 200), 'body' => $text($c['body'] ?? '', 5000), 'include_link' => !empty($c['include_link'])],
                'chat' => ['conversation_id' => (int) ($c['conversation_id'] ?? 0), 'message' => $text($c['message'] ?? '', 4000), 'include_link' => !empty($c['include_link'])],
                'task' => ['project' => ($c['project'] ?? 'record') === 'record' ? 'record' : (string) (int) $c['project'], 'title' => $text($c['title'] ?? '', 200),
                    'description' => $text($c['description'] ?? '', 2000), 'assignee' => preg_match('/^(person:[a-z_.]+|user:\d+)$/', (string) ($c['assignee'] ?? '')) ? $c['assignee'] : '',
                    'priority' => array_key_exists($c['priority'] ?? '', Project::PRIORITIES) ? $c['priority'] : 'normal',
                    'due_days' => ($c['due_days'] ?? '') === '' || $c['due_days'] === null ? '' : (string) max(0, min(365, (int) $c['due_days']))],
                'webhook' => ['url' => $text($c['url'] ?? '', 500), 'secret' => $text($c['secret'] ?? '', 200)],
                'wait' => ['amount' => max(1, min(365, (int) ($c['amount'] ?? 1))), 'unit' => array_key_exists($c['unit'] ?? '', Steps::UNITS) ? $c['unit'] : 'hours'],
                'filter' => $rules($c),
            };

            match ($type) {
                'notify' => !$hasTo($clean['to']) ? $fail("Step {$n}: choose who to notify.") : ($clean['title'] === '' && $clean['message'] === '' ? $fail("Step {$n}: write the notification.") : null),
                'email' => !$hasTo($clean['to']) && $clean['emails'] === '' ? $fail("Step {$n}: choose who to e-mail.") : ($clean['subject'] === '' || $clean['body'] === '' ? $fail("Step {$n}: the e-mail needs a subject and a message.") : null),
                'chat' => !Conversation::whereIn('type', ['channel', 'group'])->whereKey($clean['conversation_id'])->exists() ? $fail("Step {$n}: choose a channel or group to post in.")
                    : ($clean['message'] === '' ? $fail("Step {$n}: write the message to post.") : null),
                'task' => $clean['project'] === 'record' && !$t['project'] ? $fail("Step {$n}: choose the project for the task.")
                    : ($clean['project'] !== 'record' && !Project::whereKey((int) $clean['project'])->exists() ? $fail("Step {$n}: that project no longer exists.") : null),
                'webhook' => !Steps::safeUrl($clean['url']) ? $fail("Step {$n}: the webhook address must be a public http:// or https:// address.") : null,
                'filter' => !$clean['rules'] ? $fail("Step {$n}: add at least one rule, or remove the step.") : null,
                default => null,
            };
            $steps[] = ['type' => $type, 'config' => $clean];
        }
        if (collect($steps)->every(fn ($s) => in_array($s['type'], ['wait', 'filter'], true))) $fail('Add a step that does something, such as a notification.');

        return [
            'trigger' => $key,
            'definition' => ['trigger' => ['key' => $key, 'config' => $config], 'conditions' => $rules($d['conditions'] ?? []), 'steps' => $steps],
        ];
    }
}
