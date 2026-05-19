<?php

namespace App\Http\Controllers;

use App\Models\EvidenceAsset;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceAssetController extends Controller
{
    /**
     * Stream a stored visual-evidence image.
     *
     * This is the stable URL handed back at upload and embedded in GitHub
     * gallery comments. It is public and unauthenticated by design — the
     * comment's camo proxy fetches it without credentials, and a private
     * repo's reader still needs the image to render. It is also backend-
     * agnostic: the URL resolves through Growth's own domain, so it never
     * rots as the S3 object's storage details change.
     *
     * No `Sec-Fetch-Dest`/CSP sandboxing is applied — unlike an HTML mockup,
     * a PNG cannot script, so a top-level navigation to it is harmless.
     */
    public function show(EvidenceAsset $evidenceAsset): StreamedResponse
    {
        $disk = Storage::disk(EvidenceAsset::DISK);

        abort_unless($disk->exists($evidenceAsset->path), 404);

        return $disk->response(
            $evidenceAsset->path,
            null,
            [
                'Content-Type' => $evidenceAsset->content_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
