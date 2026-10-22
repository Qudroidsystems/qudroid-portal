<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Projects\Task;
use App\Models\Projects\TimeEntry;
use Illuminate\Http\Request;

/** The signed-in person's tasks across projects, and their time this week. */
class MyWorkController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $tasks = Task::with('project')->where('assignee_id', $user->id)
            ->when(!$request->boolean('done'), fn ($q) => $q->open())
            ->whereHas('project', fn ($q) => $q->open())
            ->orderByRaw('case when due_on is null then 1 else 0 end')->orderBy('due_on')
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
            ->get();

        $weekStart = today()->startOfWeek();
        $week = TimeEntry::with('project')->where('user_id', $user->id)->where('date', '>=', $weekStart->toDateString())->get();

        return view('modules.projects.my-work', [
            'groups' => [
                'Overdue'   => $tasks->filter->isOverdue(),
                'Due today' => $tasks->filter(fn ($t) => $t->status !== 'done' && $t->due_on?->isToday()),
                'Upcoming'  => $tasks->filter(fn ($t) => $t->status !== 'done' && $t->due_on?->gt(today())),
                'No date'   => $tasks->filter(fn ($t) => $t->status !== 'done' && !$t->due_on),
                'Done'      => $tasks->where('status', 'done'),
            ],
            'week'      => $week,
            'weekStart' => $weekStart,
        ]);
    }
}
