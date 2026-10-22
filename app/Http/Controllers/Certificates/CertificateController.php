<?php

namespace App\Http\Controllers\Certificates;

use App\Http\Controllers\Controller;
use App\Models\Academy\Certificate;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Services\Academy\CertificateService;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Generator;

/**
 * Course certificates: the register (Manage certificates), a student's own
 * certificates, the printable certificate, and the public verify page.
 */
class CertificateController extends Controller
{
    public function __construct(protected CertificateService $certificates) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $manage = $user->can('Manage certificates');
        $q = trim((string) $request->query('q'));
        $mine = Student::where('user_id', $user->id)->value('id');

        $certificates = Certificate::with('student.user', 'course')
            ->when(!$manage, fn ($w) => $w->where('student_id', $mine ?? 0))
            ->when($request->query('status'), fn ($w, $s) => $w->where('status', $s))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('serial', 'like', "%{$q}%")
                ->orWhereHas('student.user', fn ($u) => $u->where('name', 'like', "%{$q}%"))))
            ->latest('issued_at')->paginate(25)->withQueryString();

        // Completed enrollments still without a certificate (for the register).
        $waiting = $manage ? Enrollment::with('student.user', 'course', 'cohort')->where('status', 'completed')
            ->whereHas('course', fn ($c) => $c->where('certificate_enabled', true))
            ->whereDoesntHave('certificate', fn ($c) => $c->where('status', 'issued'))
            ->latest('completed_at')->limit(20)->get() : collect();

        return view('modules.certificates.index', compact('certificates', 'manage', 'waiting'));
    }

    public function show(Request $request, Certificate $certificate)
    {
        $this->authorizeView($request, $certificate);

        return view('modules.certificates.show', [
            'certificate' => $certificate,
            'qr'          => $this->qr($certificate->verifyUrl()),
            'manage'      => $request->user()->can('Manage certificates'),
        ]);
    }

    public function issue(Request $request, Enrollment $enrollment)
    {
        $data = $request->validate(['override' => ['nullable', 'boolean'], 'note' => ['nullable', 'string', 'max:300']]);
        $certificate = $this->certificates->issue($enrollment, $request->user(), $request->boolean('override'), $data['note'] ?? null);

        return redirect()->route('certificates.show', $certificate)->with('success', "Certificate {$certificate->serial} issued.");
    }

    public function revoke(Request $request, Certificate $certificate)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);
        $this->certificates->revoke($certificate, $request->user(), $data['reason']);

        return back()->with('success', "Certificate {$certificate->serial} revoked.");
    }

    /** Public: anyone scanning the QR code can check a certificate is genuine. */
    public function verify(string $token)
    {
        $certificate = strlen($token) === 40 ? Certificate::where('verify_token', $token)->first() : null;
        $certificate?->increment('verify_count');

        return response()->view('modules.certificates.verify', compact('certificate'))->header('X-Robots-Tag', 'noindex');
    }

    protected function authorizeView(Request $request, Certificate $certificate): void
    {
        $user = $request->user();
        abort_unless($user->can('Manage certificates') || $certificate->student->user_id === $user->id, 404);
    }

    protected function qr(string $url): string
    {
        return (string) (new Generator)->format('svg')->size(130)->margin(0)->errorCorrection('M')->generate($url);
    }
}
