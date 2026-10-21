{{-- Rich-text editor (Quill) bound to a hidden <textarea name="{{ $name }}">. --}}
@once
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <style>.ql-container{font-size:15px}.ql-editor{min-height:var(--h,220px)}.ql-toolbar.ql-snow,.ql-container.ql-snow{border-color:#e2e8f0}.ql-toolbar.ql-snow{border-radius:10px 10px 0 0}.ql-container.ql-snow{border-radius:0 0 10px 10px}</style>
@endonce
@php $eid = 'ed_' . $name . '_' . substr(md5(uniqid()), 0, 5); @endphp
<div id="{{ $eid }}" style="--h:{{ $height ?? 260 }}px">{!! $value !!}</div>
<textarea name="{{ $name }}" id="{{ $eid }}_in" hidden>{{ $value }}</textarea>
<script>
(function () {
    var q = new Quill('#{{ $eid }}', { theme: 'snow', modules: { toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'link', 'video'], [{ align: [] }], ['clean']] } });
    var input = document.getElementById('{{ $eid }}_in');
    q.on('text-change', function () { input.value = q.root.innerHTML === '<p><br></p>' ? '' : q.root.innerHTML; });
})();
</script>
