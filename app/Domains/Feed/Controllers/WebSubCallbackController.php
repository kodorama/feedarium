<?php

namespace App\Domains\Feed\Controllers;

use App\Models\Feed;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Domains\Feed\Jobs\ImportFeedItemsJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\Feed\Support\WebSubSignatureVerifier;

/**
 * Handles WebSub (PubSubHubbub) hub verification (GET) and content push (POST).
 *
 * GET:  Hub sends hub.challenge for subscription verification — echo it back.
 * POST: Hub sends new content push — verify HMAC signature and import items.
 */
final class WebSubCallbackController extends Controller
{
    use DispatchesJobs;

    public function __invoke(Request $request, int $feedId): Response
    {
        if ($request->isMethod('GET')) {
            return $this->handleVerification($request);
        }

        return $this->handlePush($request, $feedId);
    }

    private function handleVerification(Request $request): Response
    {
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        if (! $challenge) {
            return response('Missing hub.challenge', 400);
        }

        return response((string) $challenge, 200)
            ->header('Content-Type', 'text/plain');
    }

    private function handlePush(Request $request, int $feedId): Response
    {
        $feed = Feed::query()->find($feedId);

        if (! $feed) {
            return response('Feed not found', 404);
        }

        $signature = $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Hub-Signature');

        if ($feed->websub_secret && $signature) {
            if (! WebSubSignatureVerifier::verify($feed->websub_secret, $request->getContent(), (string) $signature)) {
                return response('Invalid signature', 403);
            }
        } elseif ($feed->websub_secret && ! $signature) {
            return response('Missing signature', 403);
        }

        $rawXml = $request->getContent();

        if ($rawXml) {
            $this->dispatch(new ImportFeedItemsJob($feedId, $rawXml));
        }

        return response('', 200);
    }
}
