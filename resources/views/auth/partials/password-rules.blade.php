{{-- Live checklist for a new password; layouts.auth ticks it as #password-input changes. --}}
<div id="password-contain" class="p-3 bg-light mb-4 password-strength-container">
    <h5 class="fs-sm fw-semibold mb-2">Password must contain:</h5>
    <p id="pass-length" class="password-requirement invalid mb-2"><i class="ri-close-circle-fill"></i><span>Minimum <b>8 characters</b></span></p>
    <p id="pass-lower" class="password-requirement invalid mb-2"><i class="ri-close-circle-fill"></i><span>At least one <b>lowercase</b> letter (a-z)</span></p>
    <p id="pass-upper" class="password-requirement invalid mb-2"><i class="ri-close-circle-fill"></i><span>At least one <b>uppercase</b> letter (A-Z)</span></p>
    <p id="pass-number" class="password-requirement invalid mb-0"><i class="ri-close-circle-fill"></i><span>At least one <b>number</b> (0-9)</span></p>
</div>
