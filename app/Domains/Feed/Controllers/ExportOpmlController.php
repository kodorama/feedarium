<?php

namespace App\Domains\Feed\Controllers;

use XMLWriter;
use App\Models\Feed;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class ExportOpmlController extends Controller
{
    public function __invoke(): Response
    {
        abort_unless(auth()->user()?->is_admin === true, 403);

        $feeds = Feed::query()->with('category')->orderBy('name')->get();
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('opml');
        $writer->writeAttribute('version', '2.0');
        $writer->startElement('head');
        $writer->writeElement('title', config('app.name', 'Feedarium').' subscriptions');
        $writer->endElement();
        $writer->startElement('body');

        foreach ($feeds->whereNull('category_id') as $feed) {
            $this->writeFeed($writer, $feed);
        }

        foreach ($feeds->whereNotNull('category_id')->groupBy('category_id') as $categoryFeeds) {
            $category = $categoryFeeds->first()->category;
            if ($category === null) {
                continue;
            }

            $writer->startElement('outline');
            $writer->writeAttribute('text', $category->name);
            $writer->writeAttribute('title', $category->name);
            foreach ($categoryFeeds as $feed) {
                $this->writeFeed($writer, $feed);
            }
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();

        return response($writer->outputMemory(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="feedarium-subscriptions.opml"',
        ]);
    }

    private function writeFeed(XMLWriter $writer, Feed $feed): void
    {
        $writer->startElement('outline');
        $writer->writeAttribute('type', 'rss');
        $writer->writeAttribute('text', $feed->name);
        $writer->writeAttribute('title', $feed->name);
        $writer->writeAttribute('xmlUrl', $feed->url);
        if ($feed->site_url) {
            $writer->writeAttribute('htmlUrl', $feed->site_url);
        }
        if ($feed->description) {
            $writer->writeAttribute('description', $feed->description);
        }
        $writer->endElement();
    }
}
