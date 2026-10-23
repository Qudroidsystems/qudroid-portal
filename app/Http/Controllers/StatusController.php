<?php

namespace App\Http\Controllers;

use App\Services\Presence\Presence;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The signed-in person's availability (away, busy, do not disturb) and status message. */
class StatusController extends Controller
{
    public function show(Request $request, Presence $presence)
    {
        return response()->json($this->payload($request->user(), $presence));
    }

    public function update(Request $request, Presence $presence)
    {
        $payload = $this->save($request, $presence);

        return $request->expectsJson() ? response()->json($payload) : back()->with('success', 'Status updated.');
    }

    /** Apply a status change for the signed-in person; also used by the mobile API. */
    public function save(Request $request, Presence $presence): array
    {
        $data = $request->validate([
            'presence'       => ['nullable', Rule::in(array_keys(Presence::CHOICES))],
            'presence_for'   => ['nullable', 'string', 'max:20'],
            'status_emoji'   => ['nullable', 'string', 'max:16'],
            'status_text'    => ['nullable', 'string', 'max:100'],
            'status_for'     => ['nullable', 'string', 'max:20'],
            'clear_status'   => ['nullable', 'boolean'],
        ]);
        $user = $request->user();

        if (array_key_exists('presence', $data) && $data['presence'] !== null) {
            $user->forceFill([
                'presence' => $data['presence'],
                'presence_until' => $data['presence'] === 'auto' ? null : Presence::until($data['presence_for'] ?? null),
            ]);
        }
        if ($request->boolean('clear_status')) {
            $user->forceFill(['status_emoji' => null, 'status_text' => null, 'status_until' => null]);
        } elseif ($request->has('status_text') || $request->has('status_emoji')) {
            $text = trim((string) ($data['status_text'] ?? ''));
            $emoji = trim((string) ($data['status_emoji'] ?? ''));
            $user->forceFill([
                'status_emoji' => $emoji !== '' ? $emoji : null,
                'status_text' => $text !== '' ? $text : null,
                'status_until' => ($text !== '' || $emoji !== '') ? Presence::until($data['status_for'] ?? null) : null,
            ]);
        }
        $user->save();

        return $this->payload($user->refresh(), $presence);
    }

    public function payload($user, Presence $presence): array
    {
        $p = $presence->of($user);

        return $p + [
            'presence' => $user->presence ?? 'auto',
            'presence_until' => $user->presence_until?->isFuture() ? $user->presence_until->toIso8601String() : null,
            'status_until' => $user->status_until?->isFuture() ? $user->status_until->toIso8601String() : null,
            'line' => $presence->line($user),
        ];
    }
}
