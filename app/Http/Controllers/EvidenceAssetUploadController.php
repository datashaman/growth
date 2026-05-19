<?php

namespace App\Http\Controllers;

use App\Models\EvidenceAsset;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EvidenceAssetUploadController extends Controller
{
    /**
     * Store a set of visual-evidence PNGs against an `evidence` delivery link.
     *
     * The upload is the whole gallery in one request: it replaces any assets
     * the delivery link already holds — prior rows and their S3 objects are
     * removed — so re-running CI for a PR re-uploads a fresh, consistent set
     * rather than appending duplicates.
     *
     * `images[]` carries the PNG files; `captions[]` carries the matching
     * screenshot name from the artifact manifest, positionally aligned.
     * The response is the stable, backend-agnostic public URL of each stored
     * asset — what a gallery comment embeds.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'delivery_link_id' => 'required|string|owned_work_item_delivery_link',
            'images' => 'required|array|min:1',
            'images.*' => 'required|file|mimes:png|mimetypes:image/png',
            'captions' => 'required|array',
            'captions.*' => 'required|string|max:255',
        ]);

        $deliveryLink = WorkItemDeliveryLink::findOrFail($data['delivery_link_id']);

        abort_unless(
            $deliveryLink->type === 'evidence',
            422,
            'Evidence assets can only be uploaded against an evidence delivery link.',
        );

        abort_unless(
            count($data['images']) === count($data['captions']),
            422,
            'Each image must have a matching caption.',
        );

        // The upload is the complete gallery, so it supersedes whatever the
        // delivery link already holds. S3 writes are not transactional, so
        // the new set is stored and committed *first* — only then is the
        // prior set retired. A failure mid-upload rolls the new rows back and
        // leaves the original gallery and its objects intact.
        $superseded = $deliveryLink->evidenceAssets;
        $storedPaths = [];

        try {
            $assets = DB::transaction(function () use ($deliveryLink, $data, &$storedPaths): array {
                $stored = [];

                foreach ($data['images'] as $index => $image) {
                    $path = $image->store('evidence-assets', EvidenceAsset::DISK);
                    $storedPaths[] = $path;

                    $stored[] = $deliveryLink->evidenceAssets()->create([
                        'path' => $path,
                        'caption' => $data['captions'][$index],
                        'content_type' => 'image/png',
                    ]);
                }

                return $stored;
            });
        } catch (Throwable $e) {
            // The transaction rolled the new rows back, but the S3 objects it
            // wrote are not transactional — drop them so they are not orphaned.
            Storage::disk(EvidenceAsset::DISK)->delete($storedPaths);

            throw $e;
        }

        // The new gallery is committed; retire the prior set. Deleting through
        // the model layer fires each asset's `deleting` event, removing its S3
        // object too.
        $superseded->each->delete();

        return response()->json([
            'delivery_link_id' => $deliveryLink->id,
            'assets' => array_map(fn (EvidenceAsset $asset): array => [
                'id' => $asset->id,
                'caption' => $asset->caption,
                'content_type' => $asset->content_type,
                'url' => $asset->publicUrl(),
            ], $assets),
        ], 201);
    }
}
