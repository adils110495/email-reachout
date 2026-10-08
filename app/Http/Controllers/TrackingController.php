<?php

namespace App\Http\Controllers;

use App\Models\LeadEmail;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    /**
     * Open-tracking pixel. Always answers with the 1x1 GIF so a bad token
     * looks identical to a good one.
     */
    public function open(string $token): Response
    {
        $email = LeadEmail::where('tracking_token', $token)->first();

        if ($email) {
            $now = now();

            $email->forceFill([
                'open_count'      => $email->open_count + 1,
                'first_opened_at' => $email->first_opened_at ?? $now,
                'last_opened_at'  => $now,
            ])->save();
        }

        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }
}
