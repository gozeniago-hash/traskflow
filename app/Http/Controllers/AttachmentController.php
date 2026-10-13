<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        // Safe images and PDFs display in the browser; everything else downloads.
        $inline = $request->boolean('inline') && ($attachment->isImage() || $attachment->isPdf());

        $headers = ['X-Content-Type-Options' => 'nosniff'];
        if (! $inline) {
            return $disk->download($attachment->path, $attachment->original_name, $headers);
        }

        return $disk->response($attachment->path, $attachment->original_name, $headers + [
            'Content-Type' => $attachment->displayMimeType(),
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }
}
