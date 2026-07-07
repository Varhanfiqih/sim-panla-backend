<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfilePhotoController extends Controller
{
    public function show(User $user, string $filename): StreamedResponse
    {
        abort_unless($user->profile_photo_path, Response::HTTP_NOT_FOUND);
        abort_unless(basename($user->profile_photo_path) === $filename, Response::HTTP_NOT_FOUND);

        $storage = Storage::disk('public');
        abort_unless($storage->exists($user->profile_photo_path), Response::HTTP_NOT_FOUND);

        return $storage->response($user->profile_photo_path, $filename, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
