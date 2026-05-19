<?php

namespace App\Http\Controllers;

use App\Models\EvidenceAsset;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $assets = DB::transaction(function () use ($deliveryLink, $data): array {
            // Supersession: the upload is the complete gallery, so clear the
            // existing set first. Deleting through the model layer fires each
            // asset's `deleting` event, removing its S3 object too.
            $deliveryLink->evidenceAssets->each->delete();

            $stored = [];

            foreach ($data['images'] as $index => $image) {
                $path = $image->store('evidence-assets', EvidenceAsset::DISK);

                $stored[] = $deliveryLink->evidenceAssets()->create([
                    'path' => $path,
                    'caption' => $data['captions'][$index],
                    'content_type' => 'image/png',
                ]);
            }

            return $stored;
        });

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
