{{-- Partials: modals for section, lesson, assignment, quiz, live class + JS. --}}

<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<style>#lContentEditor .ql-editor,#aInstrEditor .ql-editor{min-height:150px}.ql-toolbar.ql-snow,.ql-container.ql-snow{border-color:#dee2e6}</style>

{{-- Section --}}
<div class="modal fade" id="sectionModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('lms.sections.store', $course) }}">@csrf
        <div class="modal-header"><h5 class="modal-title">Add section</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <label class="form-label small">Title *</label><input name="title" class="form-control mb-2" required>
            <label class="form-label small">Description</label><input name="description" class="form-control">
        </div>
        <div class="modal-footer"><button class="action-btn btn-primary-cb">Add section</button></div>
    </form>
</div></div></div>

{{-- Lesson (shared create/edit) --}}
<div class="modal fade" id="lessonModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" id="lessonForm"
          data-store="{{ route('lms.lessons.store', $course) }}"
          data-update="{{ route('lms.lessons.update', [$course, 0]) }}"
          data-chunk="{{ route('lms.media.chunk', $course) }}"
          enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="_method" id="lessonMethod" value="POST">
        <div class="modal-header"><h5 class="modal-title" id="lessonModalTitle">Add lesson</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-2">
                <div class="col-md-8"><label class="form-label small">Title *</label><input name="title" id="lTitle" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label small">Type *</label>
                    <select name="type" id="lType" class="form-select" onchange="lmsToggleLessonFields()">
                        @foreach(\App\Models\LmsLesson::TYPES as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select></div>

                <div class="col-md-8"><label class="form-label small">Section</label>
                    <select name="section_id" id="lSection" class="form-select"><option value="">Ungrouped</option>
                        @foreach($course->sections as $s)<option value="{{ $s->id }}">{{ $s->title }}</option>@endforeach
                    </select></div>
                <div class="col-md-4"><label class="form-label small">Duration (min)</label><input type="number" min="0" name="duration_minutes" id="lDuration" class="form-control"></div>

                <div class="col-12 lf lf-text"><label class="form-label small">Content</label>
                    <textarea name="content" id="lContent" style="display:none"></textarea>
                    <div id="lContentEditor" class="bg-white"></div></div>

                <div class="col-12 lf lf-video_embed"><label class="form-label small">Video link (YouTube / Vimeo / embed URL)</label><input name="video_url" id="lVideo" class="form-control" placeholder="https://youtu.be/..."></div>

                <div class="col-12 lf lf-file lf-video_upload"><label class="form-label small">Upload file <span id="lAttachCurrent" class="text-muted"></span></label>
                    <input type="file" name="attachment" id="lAttachment" class="form-control">
                    <input type="hidden" name="attachment_path" id="lAttachPath">
                    <input type="hidden" name="attachment_name" id="lAttachName">
                    <div class="progress mt-2 d-none" id="lAttachBar" style="height:6px"><div class="progress-bar" id="lAttachBarInner" style="width:0%"></div></div>
                    <div class="form-text" id="lAttachProg">Large videos upload in the background in small chunks (works around the server upload limit). Embedding a YouTube/Vimeo link is best for very large videos.</div></div>

                <div class="col-12 lf lf-cbt"><label class="form-label small">Linked CBT exam</label>
                    <select name="exam_id" id="lExam" class="form-select"><option value="">— select exam —</option>
                        @foreach($exams as $ex)<option value="{{ $ex->id }}">{{ $ex->title }}</option>@endforeach
                    </select>
                    <div class="form-text">Learners take this graded test in the existing CBT module.</div></div>

                <div class="col-12 lf lf-live"><div class="cb-banner info"><i class="ri-information-line"></i><div class="small">Schedule the actual session under <strong>Live &amp; Announcements</strong>. This lesson simply lists it in the curriculum.</div></div></div>

                <div class="col-12 d-flex gap-4 mt-2">
                    <div class="form-check form-switch"><input type="hidden" name="is_published" value="0"><input class="form-check-input" type="checkbox" name="is_published" value="1" id="lPublished" checked><label class="form-check-label small" for="lPublished">Published</label></div>
                    <div class="form-check form-switch"><input type="hidden" name="is_preview" value="0"><input class="form-check-input" type="checkbox" name="is_preview" value="1" id="lPreview"><label class="form-check-label small" for="lPreview">Free preview</label></div>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button class="action-btn btn-primary-cb" id="lessonSubmit">Add lesson</button></div>
    </form>
</div></div></div>

{{-- Assignment --}}
<div class="modal fade" id="assignmentModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" action="{{ route('lms.assignments.store', $course) }}">@csrf
        <div class="modal-header"><h5 class="modal-title">New assignment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-2">
                <div class="col-md-8"><label class="form-label small">Title *</label><input name="title" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label small">Max score *</label><input type="number" min="1" name="max_score" value="100" class="form-control" required></div>
                <div class="col-12"><label class="form-label small">Instructions</label>
                    <textarea name="instructions" id="aInstr" style="display:none"></textarea>
                    <div id="aInstrEditor" class="bg-white"></div></div>
                <div class="col-md-6"><label class="form-label small">Lesson (optional)</label>
                    <select name="lesson_id" class="form-select"><option value="">—</option>@foreach($course->lessons as $l)<option value="{{ $l->id }}">{{ $l->title }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label small">Due date</label><input type="datetime-local" name="due_at" class="form-control"></div>
                <div class="col-12 d-flex gap-4 mt-1">
                    <div class="form-check form-switch"><input type="hidden" name="allow_text" value="0"><input class="form-check-input" type="checkbox" name="allow_text" value="1" id="aText" checked><label class="form-check-label small" for="aText">Text answer</label></div>
                    <div class="form-check form-switch"><input type="hidden" name="allow_file" value="0"><input class="form-check-input" type="checkbox" name="allow_file" value="1" id="aFile" checked><label class="form-check-label small" for="aFile">File upload</label></div>
                    <div class="form-check form-switch"><input type="hidden" name="is_published" value="0"><input class="form-check-input" type="checkbox" name="is_published" value="1" id="aPub" checked><label class="form-check-label small" for="aPub">Published</label></div>
                </div>
                <div class="col-12">
                    <label class="form-label small mb-1">Rubric (optional) — adds criteria; the max score becomes their total</label>
                    <div id="rubricRows"></div>
                    <button type="button" class="action-btn btn-open mt-1" onclick="lmsRubricRow('','')"><i class="ri-add-line"></i>Add criterion</button>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button class="action-btn btn-primary-cb">Create</button></div>
    </form>
</div></div></div>

{{-- Quiz --}}
<div class="modal fade" id="quizModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('lms.quizzes.store', $course) }}">@csrf
        <div class="modal-header"><h5 class="modal-title">New quiz</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <label class="form-label small">Title *</label><input name="title" class="form-control mb-2" required>
            <label class="form-label small">Description</label><textarea name="description" rows="2" class="form-control mb-2"></textarea>
            <div class="row g-2">
                <div class="col-6"><label class="form-label small">Pass mark %</label><input type="number" min="0" max="100" name="pass_mark" value="50" class="form-control"></div>
                <div class="col-6"><label class="form-label small">Max attempts (0=∞)</label><input type="number" min="0" name="max_attempts" value="0" class="form-control"></div>
                <div class="col-6"><label class="form-label small">Time limit (min)</label><input type="number" min="0" name="time_limit_minutes" class="form-control"></div>
                <div class="col-6"><label class="form-label small">Lesson</label>
                    <select name="lesson_id" class="form-select"><option value="">—</option>@foreach($course->lessons as $l)<option value="{{ $l->id }}">{{ $l->title }}</option>@endforeach</select></div>
            </div>
            <div class="form-check form-switch mt-2"><input type="hidden" name="shuffle" value="0"><input class="form-check-input" type="checkbox" name="shuffle" value="1" id="qShuffle"><label class="form-check-label small" for="qShuffle">Shuffle questions</label></div>
        </div>
        <div class="modal-footer"><button class="action-btn btn-primary-cb">Create &amp; add questions</button></div>
    </form>
</div></div></div>

{{-- Live class --}}
<div class="modal fade" id="liveModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('lms.live.store', $course) }}">@csrf
        <div class="modal-header"><h5 class="modal-title">Schedule live class</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <label class="form-label small">Title *</label><input name="title" class="form-control mb-2" required>
            <label class="form-label small">Description</label><textarea name="description" rows="2" class="form-control mb-2"></textarea>
            <div class="row g-2">
                <div class="col-md-6"><label class="form-label small">Provider</label>
                    <select name="provider" class="form-select"><option value="">—</option><option>zoom</option><option>meet</option><option>teams</option><option value="other">other</option></select></div>
                <div class="col-md-6"><label class="form-label small">Duration (min)</label><input type="number" min="0" name="duration_minutes" value="60" class="form-control"></div>
                <div class="col-12"><label class="form-label small">Join link</label><input name="join_url" class="form-control" placeholder="https://..."></div>
                <div class="col-12"><label class="form-label small">Scheduled at</label><input type="datetime-local" name="scheduled_at" class="form-control"></div>
            </div>
        </div>
        <div class="modal-footer"><button class="action-btn btn-primary-cb">Schedule</button></div>
    </form>
</div></div></div>

<script>
var lmsQuill=null, lmsAsgQuill=null;
document.addEventListener('DOMContentLoaded', function(){
    if(!window.Quill) return;
    var mods={toolbar:[['bold','italic','underline'],[{list:'ordered'},{list:'bullet'}],[{header:[2,3,false]}],['link','blockquote','code-block'],['clean']]};
    if(document.getElementById('lContentEditor')){
        lmsQuill=new Quill('#lContentEditor',{theme:'snow',modules:mods,placeholder:'Write the lesson…'});
        lmsQuill.on('text-change',function(){document.getElementById('lContent').value=lmsQuill.root.innerHTML;});
    }
    if(document.getElementById('aInstrEditor')){
        lmsAsgQuill=new Quill('#aInstrEditor',{theme:'snow',modules:mods,placeholder:'Instructions for students…'});
        lmsAsgQuill.on('text-change',function(){document.getElementById('aInstr').value=lmsAsgQuill.root.innerHTML;});
    }
    var am=document.getElementById('assignmentModal');
    if(am){ am.addEventListener('show.bs.modal',function(){ if(lmsAsgQuill){ lmsAsgQuill.root.innerHTML=''; document.getElementById('aInstr').value=''; } }); }
});
function lmsRubricRow(name, max){
    var w=document.getElementById('rubricRows'); if(!w) return;
    var r=document.createElement('div'); r.className='input-group input-group-sm mb-1';
    r.innerHTML='<input type="text" class="form-control" name="rubric_name[]" placeholder="Criterion (e.g. Structure)" value="'+String(name||'').replace(/"/g,'&quot;')+'">'
        +'<input type="number" min="0" class="form-control" name="rubric_max[]" placeholder="Max" style="max-width:110px" value="'+(max||'')+'">'
        +'<button type="button" class="btn btn-outline-danger" onclick="this.closest(\'.input-group\').remove()"><i class="ri-close-line"></i></button>';
    w.appendChild(r);
}
function lmsToggleLessonFields(){
    var t = document.getElementById('lType').value;
    document.querySelectorAll('#lessonForm .lf').forEach(function(el){ el.style.display='none'; });
    document.querySelectorAll('#lessonForm .lf-'+t).forEach(function(el){ el.style.display=''; });
}
function lmsPrepLesson(btn){
    var f = document.getElementById('lessonForm');
    var mode = btn.getAttribute('data-mode');
    var g = function(a){ return btn.getAttribute(a) || ''; };
    document.getElementById('lessonModalTitle').textContent = (mode==='edit'?'Edit lesson':'Add lesson');
    document.getElementById('lessonSubmit').textContent = (mode==='edit'?'Save changes':'Add lesson');

    if(mode==='edit'){
        f.action = f.getAttribute('data-update').replace(/0$/, g('data-id'));
        document.getElementById('lessonMethod').value = 'PUT';
        document.getElementById('lTitle').value = g('data-title');
        document.getElementById('lType').value = g('data-type') || 'text';
        document.getElementById('lSection').value = g('data-section');
        document.getElementById('lContent').value = g('data-content');
        if(lmsQuill){ lmsQuill.root.innerHTML = g('data-content') || ''; }
        document.getElementById('lVideo').value = g('data-video');
        document.getElementById('lExam').value = g('data-exam');
        document.getElementById('lDuration').value = g('data-duration');
        document.getElementById('lPreview').checked = g('data-preview')==='1';
        document.getElementById('lPublished').checked = g('data-published')==='1';
        var cur = g('data-attachment');
        document.getElementById('lAttachCurrent').textContent = cur ? ('(current: '+cur+')') : '';
    } else {
        f.action = f.getAttribute('data-store');
        document.getElementById('lessonMethod').value = 'POST';
        f.reset();
        document.getElementById('lType').value = 'text';
        document.getElementById('lPublished').checked = true;
        document.getElementById('lAttachCurrent').textContent = '';
        if(lmsQuill){ lmsQuill.root.innerHTML = ''; document.getElementById('lContent').value = ''; }
    }
    lmsResetAttach();
    lmsToggleLessonFields();
}
document.addEventListener('DOMContentLoaded', lmsToggleLessonFields);

// ── Chunked upload for lesson files/videos (bypasses PHP upload limit) ──
var LMS_CSRF = '{{ csrf_token() }}';
function lmsResetAttach(){
    var inp=document.getElementById('lAttachment'); if(inp){ inp.disabled=false; inp.value=''; }
    var p=document.getElementById('lAttachPath'); if(p) p.value='';
    var n=document.getElementById('lAttachName'); if(n) n.value='';
    var bar=document.getElementById('lAttachBar'); if(bar) bar.classList.add('d-none');
    var inner=document.getElementById('lAttachBarInner'); if(inner) inner.style.width='0%';
}
async function lmsChunkUpload(file){
    var f=document.getElementById('lessonForm');
    var url=f.getAttribute('data-chunk');
    var id='up'+Date.now()+Math.random().toString(36).slice(2,8);
    var chunk=5*1024*1024, total=Math.ceil(file.size/chunk);
    var bar=document.getElementById('lAttachBar'), inner=document.getElementById('lAttachBarInner'), prog=document.getElementById('lAttachProg');
    if(bar) bar.classList.remove('d-none');
    for(var i=0;i<total;i++){
        var fd=new FormData();
        fd.append('upload_id',id); fd.append('index',i); fd.append('total',total);
        fd.append('chunk', file.slice(i*chunk,(i+1)*chunk));
        if(i===total-1) fd.append('filename', file.name);
        fd.append('_token', LMS_CSRF);
        var r;
        try { r=await fetch(url,{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}); }
        catch(e){ return null; }
        if(!r.ok) return null;
        var pct=Math.round((i+1)/total*100);
        if(inner) inner.style.width=pct+'%';
        if(prog) prog.textContent='Uploading… '+pct+'%';
        var j=await r.json();
        if(j.done){ if(prog) prog.textContent='Uploaded: '+j.name; return j; }
    }
    return null;
}
document.addEventListener('DOMContentLoaded', function(){
    var inp=document.getElementById('lAttachment');
    if(!inp) return;
    inp.addEventListener('change', async function(){
        if(!this.files || !this.files.length) return;
        var file=this.files[0];
        var res=await lmsChunkUpload(file);
        if(res && res.path){
            document.getElementById('lAttachPath').value=res.path;
            document.getElementById('lAttachName').value=res.name;
            this.disabled=true; // keep the form POST small; server uses the pre-uploaded path
        } else {
            var prog=document.getElementById('lAttachProg');
            if(prog) prog.textContent='Background upload unavailable — the file will be sent with the form (subject to the server limit).';
        }
    });
});
</script>
