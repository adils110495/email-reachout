<?php

namespace App\Http\Controllers\Sequencer;

use App\Http\Controllers\Controller;
use App\Models\LeadEmail;
use App\Sequencer\Services\UnsubscribeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class UnsubscribeController extends Controller
{
    public function __construct(private readonly UnsubscribeService $unsubscribes) {}

    /** Confirmation page. Changes nothing: mail scanners and link previewers GET every link. */
    public function show(string $token): View
    {
        $lead = $this->unsubscribes->findByToken($token);
        abort_if($lead === null, 404);

        return view('sequencer.public.unsubscribe', ['token' => $token, 'email' => $this->mask((string) $lead->email), 'done' => false]);
    }

    public function perform(Request $request, string $token): View|Response
    {
        $lead = $this->unsubscribes->findByToken($token);
        abort_if($lead === null, 404);

        $email = LeadEmail::where('lead_id', $lead->id)->whereNotNull('sent_at')->latest('sent_at')->first();
        $this->unsubscribes->unsubscribe($lead, $email, $request->has('List-Unsubscribe') ? 'one_click' : 'link');

        // RFC 8058 one-click clients only need a 2xx.
        if ($request->has('List-Unsubscribe')) {
            return response('Unsubscribed', 200, ['Content-Type' => 'text/plain']);
        }

        return view('sequencer.public.unsubscribe', ['token' => $token, 'email' => $this->mask((string) $lead->email), 'done' => true]);
    }

    /** a***@example.com : confirms who is being unsubscribed without exposing the full address. */
    private function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).str_repeat('*', max(2, mb_strlen($local) - 1)).'@'.$domain;
    }
}
