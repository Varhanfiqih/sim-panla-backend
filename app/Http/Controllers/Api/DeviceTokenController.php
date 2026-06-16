<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileDeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'max:20'],
        ]);

        MobileDeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? 'android',
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['status' => 'success']);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        MobileDeviceToken::query()
            ->where('token', $validated['token'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['status' => 'success']);
    }
}
