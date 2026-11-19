@push('styles')
<style>
    .sp-photo { width: 120px; height: 120px; border-radius: 50%; overflow: hidden; background: linear-gradient(135deg, #0071e3, #6e3aff); display: grid; place-items: center; color: #fff; font-size: 40px; font-weight: 600; }
    .sp-photo img { width: 100%; height: 100%; object-fit: cover; }
    .sp-projects { display: grid; gap: 8px; }
    .sp-project { display: flex; gap: 12px; align-items: center; border: 1px solid var(--vz-border-color); border-radius: 10px; padding: 8px 10px; }
    .sp-project.is-editing { border-color: var(--vz-primary); background: rgba(var(--vz-primary-rgb), .04); }
    .sp-shot { width: 64px; height: 42px; border-radius: 6px; background: #1d1d1f; color: #aaa; display: grid; place-items: center; overflow: hidden; flex: none; font-size: 20px; }
    .sp-shot img { width: 100%; height: 100%; object-fit: cover; }
    .sp-project-form { border-top: 1px dashed var(--vz-border-color); padding-top: 16px; }
    .sp-status { display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; border-radius: 10px; background: var(--vz-light); }
    .sp-status i { font-size: 22px; }
    .min-w-0 { min-width: 0; }
</style>
@endpush
