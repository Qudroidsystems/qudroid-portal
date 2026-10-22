<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\JsonResponse;

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

    protected function num($v, int $dp = 2): ?float
    {
        return $v === null || $v === '' ? null : round((float) $v, $dp);
    }
}
