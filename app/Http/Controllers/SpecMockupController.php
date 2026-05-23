<?php

namespace App\Http\Controllers;

use App\Models\SpecMockup;
use App\Models\Theme;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class SpecMockupController extends Controller
{
    /**
     * Serve a spec mockup revision's raw, agent-authored HTML.
     *
     * The `revision` query parameter selects a revision by ULID; without it
     * the mockup's current (latest) revision is served. A revision id that
     * does not belong to this mockup 404s.
     *
     * This is the document embedded by the mockup page's sandboxed iframe.
     * The Content-Security-Policy lets a self-contained mockup pull styling
     * and scripting from HTTPS CDNs, but `connect-src 'none'` and
     * `form-action 'none'` deny it any channel to phone home, and
     * `frame-ancestors 'self'` keeps it embeddable only by Growth itself.
     * The iframe's own `sandbox` (allow-scripts, no allow-same-origin) is the
     * other half: the document runs in an opaque origin, walled off from
     * Growth's session, cookies, and DOM.
     *
     * That isolation only holds while the HTML is framed. Reached as a
     * top-level navigation it would run in Growth's own origin, unsandboxed —
     * so a request that is not a sub-resource frame fetch is bounced to the
     * wrapper page. `Sec-Fetch-Dest` is `iframe` for a framed load and
     * `document` for top-level navigation; modern browsers always send it.
     *
     * The CSP `sandbox` directive is defence-in-depth on top of that bounce:
     * it sandboxes the document into an opaque origin from the response
     * itself, so the mockup stays walled off from Growth even if the
     * `Sec-Fetch-Dest` guard is bypassed by a browser that omits the header.
     *
     * `script-src`/`style-src`/`img-src` stay open to any HTTPS origin by
     * design — mockups pull from whichever CDN the agent reached for, and an
     * allowlist would silently break them. The opaque origin plus
     * `connect-src 'none'` and `form-action 'none'` are what contain it; a
     * resource load can still leak via its URL, an accepted cost of that
     * flexibility.
     */
    public function raw(Request $request, SpecMockup $mockup): BaseResponse
    {
        if ($request->header('Sec-Fetch-Dest') !== 'iframe') {
            return redirect()->route('mockups.show', $mockup);
        }

        // An empty `?revision=` is treated as absent, falling through to the
        // current revision rather than a findOrFail('') 404.
        $revisionId = $request->query('revision') ?: null;
        $revision = $revisionId !== null
            ? $mockup->revisions()->findOrFail($revisionId)
            : $mockup->currentRevision;

        abort_if($revision === null, 404);

        $html = $this->applyTheme((string) $revision->html, $mockup, (string) $request->query('theme', ''));

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Security-Policy', implode('; ', [
                "default-src 'none'",
                "script-src 'unsafe-inline' 'unsafe-eval' https:",
                "style-src 'unsafe-inline' https:",
                'img-src data: https:',
                'font-src data: https:',
                "connect-src 'none'",
                "form-action 'none'",
                "frame-ancestors 'self'",
                "base-uri 'none'",
                'sandbox allow-scripts',
            ]))
            ->header('X-Content-Type-Options', 'nosniff');
    }

    private function applyTheme(string $html, SpecMockup $mockup, string $themeSlug): string
    {
        $themeSlug = trim($themeSlug);

        if ($themeSlug === '') {
            return $html;
        }

        $owner = $mockup->owner;
        $projectId = $owner?->project_id;

        if (! is_string($projectId)) {
            return $html;
        }

        $theme = Theme::query()
            ->where('project_id', $projectId)
            ->where('slug', $themeSlug)
            ->first();

        if (! $theme) {
            return $html;
        }

        $style = $theme->styleElement();

        if ($style === '') {
            return $html;
        }

        if (preg_match('/<head\b[^>]*>/i', $html) === 1) {
            return preg_replace('/<head\b[^>]*>/i', '$0'."\n".$style, $html, 1) ?? $html;
        }

        if (preg_match('/<html\b[^>]*>/i', $html) === 1) {
            return preg_replace('/<html\b[^>]*>/i', '$0'."\n<head>\n".$style."\n</head>", $html, 1) ?? $html;
        }

        return $style."\n".$html;
    }
}
