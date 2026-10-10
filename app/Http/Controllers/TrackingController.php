<?php

namespace App\Http\Controllers;

use App\Sequencer\Services\TrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Open pixel and click redirect for every sent email (Leads compose and sequences).
 * Public by necessity - recipients have no session - so both take nothing but a
 * random token, expose no database ids, and answer identically for unknown tokens.
 */
class TrackingController extends Controller
{
    /** 1x1 transparent GIF. */
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function __construct(private readonly TrackingService $tracking) {}

    /**
     * Open-tracking pixel. Always answers with the 1x1 GIF so a bad token
     * looks identical to a good one.
     */
    public function open(string $token): Response
    {
        if (preg_match('/^[A-Za-z0-9]{20,64}$/', $token)) {
            try {
                $this->tracking->recordOpen($token);
            } catch (Throwable $e) {
                // A tracking hiccup must never break the image the recipient's client is loading.
                Log::warning('Open tracking failed.', ['error' => $e->getMessage()]);
            }
        }

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    /** Record the click, then redirect to the destination stored for this token (never one from the URL). */
    public function click(string $token): RedirectResponse
    {
        $url = preg_match('/^[A-Za-z0-9]{20,64}$/', $token) ? $this->tracking->recordClick($token) : null;

        abort_if($url === null, 404);

        return redirect()->away($url, 302, [
            'Cache-Control'   => 'no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
