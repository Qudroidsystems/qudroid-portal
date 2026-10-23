<?php

namespace App\Services\Workflows;

/**
 * Ready-made workflows to start from. Each opens in the builder to adjust before saving;
 * nothing runs until someone switches it on.
 */
class Recipes
{
    public static function all(): array
    {
        $notify = fn (array $people, string $title, string $message, array $roles = []) => ['type' => 'notify', 'config' => ['to' => ['people' => $people, 'users' => [], 'roles' => $roles], 'title' => $title, 'message' => $message, 'link' => '']];
        $def = fn (string $trigger, array $steps, array $rules = [], array $config = []) => ['trigger' => ['key' => $trigger, 'config' => $config], 'conditions' => ['match' => 'all', 'rules' => $rules], 'steps' => $steps];

        return [
            'overdue-task' => [
                'name' => 'Nudge when a task is overdue', 'icon' => 'ri-alarm-warning-line',
                'description' => 'Tell the assignee and the project manager the day a task passes its due date.',
                'definition' => $def('task.overdue', [$notify(['task.assignee', 'project.manager'], 'Overdue: {{task.key}} {{task.title}}', '{{task.title}} in {{project.name}} was due {{task.due}} and is still {{task.status}}.')]),
            ],
            'task-review' => [
                'name' => 'Ask for a review', 'icon' => 'ri-eye-line',
                'description' => 'When a task moves to "In review", tell whoever added it.',
                'definition' => $def('task.status_changed', [$notify(['task.reporter'], 'Ready for review: {{task.key}}', '{{actor.name}} moved "{{task.title}}" to review.')],
                    [['field' => 'task.status', 'op' => 'is', 'value' => 'review']]),
            ],
            'invoice-chase' => [
                'name' => 'Chase an overdue invoice', 'icon' => 'ri-bill-line',
                'description' => 'E-mail the customer when an invoice is overdue, wait 3 days, and tell finance if it is still unpaid.',
                'definition' => $def('invoice.overdue', [
                    ['type' => 'email', 'config' => ['to' => ['people' => ['invoice.customer'], 'users' => [], 'roles' => []], 'emails' => '', 'subject' => 'Invoice {{invoice.number}} is overdue',
                        'body' => "Invoice {{invoice.number}} ({{invoice.title}}) was due on {{invoice.due}}. The balance is {{invoice.balance}}.\n\nIf you have already paid, thank you and please ignore this message.", 'include_link' => false]],
                    ['type' => 'wait', 'config' => ['amount' => 3, 'unit' => 'days']],
                    ['type' => 'filter', 'config' => ['match' => 'all', 'rules' => [['field' => 'invoice.status', 'op' => 'is_not', 'value' => 'paid'], ['field' => 'invoice.status', 'op' => 'is_not', 'value' => 'void']]]],
                    $notify([], 'Still unpaid: {{invoice.number}}', '{{invoice.customer}} still owes {{invoice.balance}} on {{invoice.number}}, 3 days after the reminder.', ['Finance Officer']),
                ]),
            ],
            'deal-won' => [
                'name' => 'Celebrate a won deal', 'icon' => 'ri-trophy-line',
                'description' => 'Post in a channel when a deal is won. Pick the channel before saving.',
                'definition' => $def('deal.won', [
                    ['type' => 'chat', 'config' => ['conversation_id' => 0, 'message' => '🎉 {{deal.owner}} just won {{deal.title}} with {{deal.company}} ({{deal.value}}).', 'include_link' => false]],
                ]),
            ],
            'leave-request' => [
                'name' => 'Send leave requests to the line manager', 'icon' => 'ri-calendar-event-line',
                'description' => 'Tell the line manager straight away when someone asks for leave.',
                'definition' => $def('leave.requested', [$notify(['leave.manager'], 'Leave request: {{leave.employee}}', '{{leave.employee}} asked for {{leave.days}} days of {{leave.type}}, {{leave.from}} to {{leave.to}}.')]),
            ],
            'low-stock' => [
                'name' => 'Reorder when stock runs low', 'icon' => 'ri-archive-line',
                'description' => 'Tell the inventory team when an item drops to its reorder level.',
                'definition' => $def('stock.low', [$notify([], 'Low stock: {{item.name}}', 'Only {{item.on_hand}} {{item.unit}} left (reorder level {{item.reorder_level}}). Supplier: {{item.supplier}}.', ['Inventory Officer'])]),
            ],
            'new-applicant' => [
                'name' => 'Welcome a new applicant', 'icon' => 'ri-graduation-cap-line',
                'description' => 'Thank the applicant by e-mail and tell the admissions team.',
                'definition' => $def('application.submitted', [
                    ['type' => 'email', 'config' => ['to' => ['people' => [], 'users' => [], 'roles' => []], 'emails' => '{{application.email}}', 'subject' => 'We have your application ({{application.reference}})',
                        'body' => "Thank you for applying for {{application.course}}. Your reference is {{application.reference}}.\n\nWe will review your application and get back to you soon.", 'include_link' => false]],
                    $notify([], 'New application: {{application.applicant}}', '{{application.applicant}} applied for {{application.course}} ({{application.cohort}}).', ['Academy Administrator']),
                ]),
            ],
            'new-employee' => [
                'name' => 'Onboard a new employee', 'icon' => 'ri-user-add-line',
                'description' => 'Welcome the new starter and remind their manager to plan the first week.',
                'definition' => $def('employee.created', [
                    $notify(['employee.self'], 'Welcome to Qudroid, {{employee.name}}!', 'We are glad to have you. Your line manager is {{employee.manager}}.'),
                    $notify(['employee.manager'], 'New starter: {{employee.name}}', '{{employee.name}} joins as {{employee.job_title}} on {{employee.start}}. Plan their first week.'),
                ]),
            ],
            'standup' => [
                'name' => 'Daily stand-up reminder', 'icon' => 'ri-time-line',
                'description' => 'Every weekday at 9:00, post a stand-up prompt in a channel.',
                'definition' => $def('schedule.daily', [
                    ['type' => 'chat', 'config' => ['conversation_id' => 0, 'message' => "Good morning! Stand-up for {{today}}:\n1. What did you finish yesterday?\n2. What are you doing today?\n3. Anything blocking you?", 'include_link' => false]],
                ], [], ['time' => '09:00', 'weekdays_only' => true]),
            ],
            'urgent-channel' => [
                'name' => 'Flag urgent chat messages', 'icon' => 'ri-error-warning-line',
                'description' => 'When someone writes "urgent" in a channel, notify the managers.',
                'definition' => $def('message.channel', [$notify([], 'Urgent in #{{message.channel}}', '{{message.from}}: {{message.text}}', ['Managing Director'])],
                    [['field' => 'message.text', 'op' => 'contains', 'value' => 'urgent']]),
            ],
        ];
    }
}
