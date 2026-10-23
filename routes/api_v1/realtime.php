<?php

use App\Services\Realtime\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Instant updates (Laravel Reverb). Loaded inside the signed-in API group.
// The app connects with the Pusher protocol using these settings, then signs
// private channels (user.{id}, connect.c.{id}, call.{id}) at broadcasting/auth.
Route::get('realtime', fn () => response()->json(['success' => true, 'message' => null, 'data' => ($cfg = Realtime::clientConfig())
    ? $cfg + ['enabled' => true, 'auth_url' => route('api.v1.realtime.auth'), 'channels' => ['user.' . auth()->id(), 'connect.c.{conversation_id}', 'call.{call_id}']]
    : ['enabled' => false]]))->name('realtime');
Route::post('broadcasting/auth', fn (Request $request) => Realtime::enabled() ? Broadcast::auth($request) : abort(404))->name('realtime.auth');
