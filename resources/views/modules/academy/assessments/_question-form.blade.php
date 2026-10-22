{{-- Add or edit one quiz question. $question, $action, $method, $key --}}
@php $opts = array_pad($question->options && $question->type !== 'boolean' ? $question->options : [], 6, ''); $correct = $question->correct ?? []; @endphp
<form method="POST" action="{{ $action }}" data-question-form>
    @csrf @if($method === 'PUT') @method('PUT') @endif
    <div class="row g-2 mb-2">
        <div class="col-sm-8"><select name="type" class="form-select form-select-sm">@foreach(\App\Models\Academy\Question::TYPES as $k => $v)<option value="{{ $k }}" @selected($question->type === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-sm-4"><div class="input-group input-group-sm"><input name="points" type="number" step="0.5" min="0.5" max="100" class="form-control" value="{{ $question->points + 0 }}" required><span class="input-group-text">points</span></div></div>
    </div>
    <textarea name="question" class="form-control mb-2" rows="2" required placeholder="The question">{{ $question->question }}</textarea>

    <div data-for="single multiple" class="mb-2">
        <div class="small text-muted mb-1">Options. Tick the correct one(s).</div>
        @foreach($opts as $i => $o)
            <div class="input-group input-group-sm mb-1">
                <span class="input-group-text"><input data-correct type="radio" name="correct[]" value="{{ $i }}" @checked(in_array($i, $correct) && $question->type !== 'boolean') aria-label="Correct"></span>
                <input name="options[{{ $i }}]" class="form-control" value="{{ $o }}" placeholder="Option {{ chr(65 + $i) }}">
            </div>
        @endforeach
    </div>
    <div data-for="boolean" class="mb-2">
        <div class="small text-muted mb-1">The correct answer</div>
        <div class="btn-group btn-group-sm" role="group">
            <input type="radio" class="btn-check" name="correct[]" value="0" id="t-{{ $key }}" @checked($question->type === 'boolean' && in_array(0, $correct))><label class="btn btn-outline-success" for="t-{{ $key }}">True</label>
            <input type="radio" class="btn-check" name="correct[]" value="1" id="f-{{ $key }}" @checked($question->type === 'boolean' && in_array(1, $correct))><label class="btn btn-outline-danger" for="f-{{ $key }}">False</label>
        </div>
    </div>
    <div data-for="short_answer" class="mb-2">
        <textarea name="accepted" class="form-control form-control-sm" rows="2" placeholder="Accepted answers, one per line (capitals and extra spaces are ignored)">{{ implode("\n", $question->accepted_answers ?? []) }}</textarea>
    </div>
    <div data-for="essay" class="small text-muted mb-2">Students type a long answer; a teacher marks it from the results page.</div>

    <input name="explanation" class="form-control form-control-sm mb-2" value="{{ $question->explanation }}" placeholder="Explanation shown after submitting (optional)" maxlength="5000">
    <button class="btn btn-sm btn-primary">{{ $method === 'PUT' ? 'Save question' : 'Add question' }}</button>
</form>
