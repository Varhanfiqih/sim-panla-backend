<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileDeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'max:20'],
        ]);

        $deviceToken = MobileDeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? 'android',
                'last_seen_at' => now(),
            ],
        );

        Log::info('Mobile device token registered', [
            'user_id' => $request->user()->id,
            'device_token_id' => $deviceToken->id,
            'platform' => $deviceToken->platform,
        ]);

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
