<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Schoolsession;
use App\Models\Schoolterm;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * The mobile API response envelope and small shared helpers. Used by
 * ApiController, and by API controllers that extend a web controller
 * (to reuse its logic) and so cannot extend ApiController.
 *
 *   success: { "success": true,  "message": "...", "data": ..., "meta": {...} }
 *   failure: { "success": false, "message": "...", "code": "...", "errors": {...} }
 */
trait RespondsWithApi
{
    protected function ok(mixed $data = null, ?string $message = null, int $status = 200, array $meta = []): JsonResponse
    {
        $body = ['success' => true];
        if ($message !== null) $body['message'] = $message;
        $body['data'] = $data;
        if ($meta) $body['meta'] = $meta;

        return response()->json($body, $status);
    }

    protected function fail(string $message, int $status = 422, ?string $code = null, array $errors = []): JsonResponse
    {
        $body = ['success' => false, 'message' => $message];
        if ($code) $body['code'] = $code;
        if ($errors) $body['errors'] = $errors;

        return response()->json($body, $status);
    }

    /** Paginator → [items, meta] for the envelope. */
    protected function paged($paginator, ?callable $map = null): JsonResponse
    {
        $items = collect($paginator->items());
        if ($map) $items = $items->map($map);

        return $this->ok($items->values(), null, 200, [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ]);
    }

    protected function currentSessionId(): ?int
    {
        $id = Schoolsession::where('status', 'Current')->value('id');
        return $id ? (int) $id : null;
    }

    protected function currentTermId(): ?int
    {
        $id = Schoolterm::current()?->id;
        return $id ? (int) $id : null;
    }

    /** The current session and term, as the app shows them. */
    protected function currentPeriod(): array
    {
        $sessionId = $this->currentSessionId();
        $termId    = $this->currentTermId();

        return [
            'session_id' => $sessionId,
            'session'    => $sessionId ? DB::table('schoolsession')->where('id', $sessionId)->value('session') : null,
            'term_id'    => $termId,
            'term'       => $termId ? DB::table('schoolterm')->where('id', $termId)->value('term') : null,
        ];
    }

    protected function studentPhotoUrl(?string $picture): ?string
    {
        return $picture ? asset('storage/student_avatars/' . basename($picture)) : null;
    }

    protected function staffPhotoUrl(?string $picture): ?string
    {
        return $picture ? asset('storage/staff_avatars/' . basename($picture)) : null;
    }

    protected function num($v, int $dp = 2): ?float
    {
        return $v === null || $v === '' ? null : round((float) $v, $dp);
    }
}
