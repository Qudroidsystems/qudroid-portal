{{-- A website form. Posts to site.forms.submit; errors come back under "f.<key>". --}}
@if(session('form_sent') === $form->slug)
    <div class="s-alert is-ok" role="status"><b>Sent.</b> {{ $form->success_message ?: 'Thank you, we will be in touch.' }}</div>
@elseif(!$form->isOpen())
    <div class="s-alert is-info">This form is closed for now.</div>
@else
    <form class="s-form" method="POST" action="{{ route('site.forms.submit', $form) }}#form-{{ $form->slug }}" novalidate>
        @csrf
        <input type="hidden" name="_page" value="{{ request()->path() }}">
        <div class="s-hp" aria-hidden="true"><label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        @foreach((array) $form->fields as $f)
            @php($name = 'f[' . $f['key'] . ']')
            @php($old = old('f.' . $f['key']))
            <div class="s-field">
                @if($f['type'] === 'checkbox')
                    <label class="s-check"><input type="checkbox" name="{{ $name }}" value="1" @checked($old) @required(!empty($f['required']))> <span>{{ $f['label'] }}@if(!empty($f['required'])) *@endif</span></label>
                @else
                    <label for="f_{{ $form->slug }}_{{ $f['key'] }}">{{ $f['label'] }}@if(!empty($f['required'])) *@endif</label>
                    @if($f['type'] === 'textarea')
                        <textarea id="f_{{ $form->slug }}_{{ $f['key'] }}" name="{{ $name }}" @required(!empty($f['required'])) maxlength="5000">{{ $old }}</textarea>
                    @elseif($f['type'] === 'select')
                        <select id="f_{{ $form->slug }}_{{ $f['key'] }}" name="{{ $name }}" @required(!empty($f['required']))>
                            <option value="">Choose…</option>
                            @foreach($f['options'] ?? [] as $o)<option @selected($old === $o)>{{ $o }}</option>@endforeach
                        </select>
                    @else
                        <input class="s-input" id="f_{{ $form->slug }}_{{ $f['key'] }}" name="{{ $name }}" value="{{ $old }}" @required(!empty($f['required']))
                               type="{{ ['email' => 'email', 'phone' => 'tel', 'number' => 'number', 'date' => 'date'][$f['type']] ?? 'text' }}"
                               @if($f['type'] === 'email') autocomplete="email" @elseif($f['type'] === 'phone') autocomplete="tel" @elseif($f['key'] === 'name') autocomplete="name" @endif>
                    @endif
                @endif
                @if(!empty($f['help']))<div class="s-muted" style="font-size:13px;margin-top:4px">{{ $f['help'] }}</div>@endif
                @error('f.' . $f['key'])<div class="s-err">{{ $message }}</div>@enderror
            </div>
        @endforeach
        <button class="s-btn" type="submit">{{ $form->button_label ?: 'Send' }}</button>
    </form>
@endif
