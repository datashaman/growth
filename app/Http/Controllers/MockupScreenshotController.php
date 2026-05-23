<?php

namespace App\Http\Controllers;

use App\Models\SpecMockup;
use App\Support\MockupScreenshotAsset;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class MockupScreenshotController extends Controller
{
    public function show(Request $request, SpecMockup $mockup, string $revision, MockupScreenshotAsset $asset): Response
    {
        $revision = $mockup->revisions()->find($revision);

        abort_unless($revision !== null, 404);

        try {
            $screenshot = $asset->render($mockup, $revision, (string) $request->query('theme', 'assigned'));
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return response($screenshot['content'], 200, [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
