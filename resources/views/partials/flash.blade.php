@if(session('success'))<div class="cb-banner success"><i class="ri-checkbox-circle-line"></i><div>{{ session('success') }}</div></div>@endif
@if(session('error'))<div class="cb-banner danger"><i class="ri-error-warning-line"></i><div>{{ session('error') }}</div></div>@endif
@if(session('temp_password'))
    <div class="cb-banner warning"><i class="ri-key-2-line"></i><div>Temporary password: <code class="fs-6">{{ session('temp_password') }}</code>. Give it to the student now; it is not shown again. They will choose their own at first sign-in.</div></div>
@endif
@if($errors->any())
    <div class="cb-banner danger"><i class="ri-error-warning-line"></i><div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>
@endif
@if(session('client_password'))
    <div class="cb-banner warning"><i class="ri-key-2-line"></i><div>Temporary password: <code class="fs-6">{{ session('client_password') }}</code>. Send it to the client now with the sign-in link; it is not shown again. They will choose their own at first sign-in.</div></div>
@endif
