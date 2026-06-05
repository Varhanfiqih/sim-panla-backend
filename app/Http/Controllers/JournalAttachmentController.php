<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JournalAttachmentController extends Controller
{
    public function show(Journal $journal): StreamedResponse
    {
        $this->authorizeAccess();
        $storage = $this->attachmentStorage($journal);

        return $storage->response(
            $journal->attachment_path,
            basename($journal->attachment_path),
            ['Content-Disposition' => 'inline'],
        );
    }

    public function download(Journal $journal): StreamedResponse
    {
        $this->authorizeAccess();
        $storage = $this->attachmentStorage($journal);

        return $storage->download(
            $journal->attachment_path,
            basename($journal->attachment_path),
        );
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();

        abort_unless($user && ($user->isKepsek() || $user->isSuperAdmin()), 403);
    }

    private function attachmentStorage(Journal $journal): FilesystemAdapter
    {
        abort_unless(filled($journal->attachment_path), 404);

        $storage = Storage::disk('public');
        abort_unless($storage->exists($journal->attachment_path), 404);

        return $storage;
    }
}
