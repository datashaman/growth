<?php

namespace App\Http\Controllers;

use App\Models\SpecMockup;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class SpecMockupController extends Controller
{
    /**
     * Serve a spec mockup's raw, agent-authored HTML.
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
     */
    public function raw(Request $request, SpecMockup $mockup): BaseResponse
    {
        if ($request->header('Sec-Fetch-Dest') !== 'iframe') {
            return redirect()->route('mockups.show', $mockup);
        }

        return response($mockup->html)
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
            ]))
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
