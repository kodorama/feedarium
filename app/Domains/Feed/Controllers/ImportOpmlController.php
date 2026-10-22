<?php

namespace App\Domains\Feed\Controllers;

use InvalidArgumentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\Feed\Jobs\ImportOpmlJob;
use App\Domains\Feed\Support\OpmlParser;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Validation\ValidationException;
use App\Domains\Feed\Requests\ImportOpmlRequest;

final class ImportOpmlController extends Controller
{
    use DispatchesJobs;

    public function __invoke(ImportOpmlRequest $request, OpmlParser $parser): JsonResponse
    {
        try {
            $parsed = $parser->parse($request->file('file')->get());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $summary = $this->dispatchSync(new ImportOpmlJob(
            $parsed['feeds'],
            $parsed['duplicates'],
            $parsed['invalid'],
            $parsed['unsupported'],
        ));

        return response()->json([
            'message' => 'OPML import completed.',
            'summary' => $summary,
        ]);
    }
}
