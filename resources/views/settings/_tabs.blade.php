<ul class="nav nav-tabs mb-3">
    @can('Manage settings')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('portal-settings.payments') ? 'active' : '' }}" href="{{ route('portal-settings.payments') }}"><i class="ri-bank-card-line me-1"></i>Payments</a></li>
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('portal-settings.integrations') ? 'active' : '' }}" href="{{ route('portal-settings.integrations') }}"><i class="ri-plug-line me-1"></i>Integrations</a></li>
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('portal-settings.units') ? 'active' : '' }}" href="{{ route('portal-settings.units') }}"><i class="ri-building-2-line me-1"></i>Business units</a></li>
    @endcan
    @if(auth()->user()->can('Manage settings') || auth()->user()->can('Manage payroll'))
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('portal-settings.payroll') ? 'active' : '' }}" href="{{ route('portal-settings.payroll') }}"><i class="ri-percent-line me-1"></i>Payroll tax</a></li>
    @endif
</ul>
