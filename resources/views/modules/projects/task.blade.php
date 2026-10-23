@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$task->title" icon="ri-task-line" :subtitle="$project->code . '-' . $task->number . ' · ' . $task->status_label . ($task->assignee ? ' · ' . $task->assignee->name : '')" :back="route('projects.board', $project)" back-label="Board" />

    @include('partials.flash')

    @include('modules.projects._task-body')

</div></div></div>
@endsection

@push('styles')
@include('modules.projects._task-styles')
@endpush
