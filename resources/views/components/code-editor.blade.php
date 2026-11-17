{{--
    A code editor (CodeMirror 5): line numbers, syntax colours, auto-indent, Tab to indent,
    bracket matching. Falls back to a plain monospace text box if the editor can't load.
    Read-only mode is for marking: clicking a line number fills the comment form's line box
    (pass line-target="#css-selector").
--}}
@props([
    'name' => 'code',
    'value' => '',
    'language' => 'plain',
    'readonly' => false,
    'height' => '320px',
    'lineTarget' => null,
    'placeholder' => '',
    'commented' => [],
])
@php
    $mode = \App\Models\Academy\Assessment::LANGUAGES[$language][1] ?? 'text/plain';
    $label = \App\Models\Academy\Assessment::LANGUAGES[$language][0] ?? 'Code';
    $id = 'code-' . \Illuminate\Support\Str::random(8);
@endphp
<div class="qd-code" data-code-editor data-mode="{{ $mode }}" data-readonly="{{ $readonly ? 1 : 0 }}" data-height="{{ $height }}" @if($lineTarget) data-line-target="{{ $lineTarget }}" @endif data-commented="{{ implode(',', array_filter((array) $commented)) }}">
    <div class="qd-code-bar"><span><i class="ri-code-s-slash-line"></i> {{ $label }}</span>@if($readonly && $lineTarget)<span class="qd-code-hint">Click a line number to comment on that line</span>@endif</div>
    <textarea id="{{ $id }}" @unless($readonly) name="{{ $name }}" @endunless {{ $readonly ? 'readonly' : '' }} spellcheck="false" autocapitalize="off" autocomplete="off"
        class="form-control qd-code-plain" style="min-height:{{ $height }}" placeholder="{{ $placeholder }}">{{ $value }}</textarea>
</div>

@once
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.css">
<style>
.qd-code { border: 1px solid var(--vz-border-color, #e9ebec); border-radius: 8px; overflow: hidden; background: #fff; }
.qd-code-bar { display: flex; justify-content: space-between; gap: 8px; padding: 6px 10px; font-size: 12px; color: #6c757d; background: #f6f8fa; border-bottom: 1px solid var(--vz-border-color, #e9ebec); }
.qd-code-hint { font-style: italic; }
.qd-code-plain { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; border: 0; border-radius: 0; tab-size: 4; white-space: pre; }
.qd-code .CodeMirror { height: auto; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; }
.qd-code .CodeMirror-gutters { background: #f6f8fa; border-right: 1px solid #eef0f3; }
.qd-code[data-readonly="1"] .CodeMirror-linenumber { cursor: pointer; }
.qd-code[data-readonly="1"] .CodeMirror-linenumber:hover { color: #405189; font-weight: 600; }
.qd-code .qd-line-picked { background: #fff6d6; }
.qd-code .qd-line-commented { background: #eef3ff; }
[data-bs-theme="dark"] .qd-code, [data-bs-theme="dark"] .qd-code .CodeMirror { background: #1e1f22; color: #e4e6eb; }
[data-bs-theme="dark"] .qd-code-bar, [data-bs-theme="dark"] .qd-code .CodeMirror-gutters { background: #26282c; }
</style>
@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/meta.min.js"></script>
@foreach(['xml', 'javascript', 'css', 'htmlmixed', 'clike', 'php', 'python', 'sql', 'dart', 'go', 'ruby', 'swift', 'shell'] as $m)
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/{{ $m }}/{{ $m }}.min.js"></script>
@endforeach
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/addon/edit/matchbrackets.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/addon/edit/closebrackets.min.js"></script>
<script>
(function () {
    function boot(box) {
        if (box.dataset.ready || !window.CodeMirror) return;
        var ta = box.querySelector('textarea');
        // Only set it up when it can be seen (CodeMirror measures itself); collapsed panels wait.
        if (!ta || ta.offsetParent === null) return;
        box.dataset.ready = '1';
        var readOnly = box.dataset.readonly === '1';
        var cm = CodeMirror.fromTextArea(ta, {
            mode: box.dataset.mode, lineNumbers: true, readOnly: readOnly, indentUnit: 4, tabSize: 4, indentWithTabs: false,
            matchBrackets: true, autoCloseBrackets: !readOnly, lineWrapping: readOnly, viewportMargin: Infinity,
            extraKeys: { Tab: function (c) { c.somethingSelected() ? c.indentSelection('add') : c.replaceSelection('    ', 'end'); }, 'Shift-Tab': function (c) { c.indentSelection('subtract'); } }
        });
        cm.getWrapperElement().style.minHeight = box.dataset.height;
        cm.getScrollerElement().style.minHeight = box.dataset.height;
        box.cm = cm;
        if (!readOnly && ta.form) ta.form.addEventListener('submit', function () { cm.save(); });
        if (readOnly && box.dataset.lineTarget) {
            var picked = null;
            cm.on('gutterClick', function (c, line) {
                var input = document.querySelector(box.dataset.lineTarget);
                if (!input) return;
                if (picked !== null) c.removeLineClass(picked, 'background', 'qd-line-picked');
                picked = line; c.addLineClass(line, 'background', 'qd-line-picked');
                input.value = line + 1;
                var form = input.form; var body = form && form.querySelector('textarea[name="body"]');
                if (body) body.focus();
            });
        }
        (box.dataset.commented || '').split(',').filter(Boolean).forEach(function (n) { cm.addLineClass(parseInt(n, 10) - 1, 'background', 'qd-line-commented'); });
    }
    function bootAll() { document.querySelectorAll('[data-code-editor]').forEach(boot); }
    document.addEventListener('DOMContentLoaded', bootAll);
    document.addEventListener('shown.bs.collapse', bootAll);
    document.addEventListener('shown.bs.tab', bootAll);
    window.qdCodeEditors = bootAll;
})();
</script>
@endpush
@endonce
