<?php

use App\Domains\Feed\Support\OpmlParser;

it('parses FreshRSS, Inoreader and generic OPML outlines with nested categories', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <opml version="2.0">
      <head><title>Subscriptions</title></head>
      <body>
        <outline text="FreshRSS">
          <outline text="Fresh Feed" title="Fresh Feed" type="rss" xmlUrl="https://fresh.example/rss" htmlUrl="https://fresh.example"/>
        </outline>
        <outline text="Inoreader" title="Inoreader">
          <outline text="Technology">
            <outline text="Reader Feed" type="rss" xmlUrl="https://reader.example/feed.xml" description="Reader summary"/>
          </outline>
        </outline>
        <outline text="Generic Feed" title="Generic Feed" type="rss" xmlUrl="https://generic.example/rss"/>
      </body>
    </opml>
    XML;

    $parsed = (new OpmlParser)->parse($xml);

    expect($parsed['feeds'])->toBe([
        ['name' => 'Fresh Feed', 'url' => 'https://fresh.example/rss', 'description' => null, 'category' => 'FreshRSS'],
        ['name' => 'Reader Feed', 'url' => 'https://reader.example/feed.xml', 'description' => 'Reader summary', 'category' => 'Inoreader / Technology'],
        ['name' => 'Generic Feed', 'url' => 'https://generic.example/rss', 'description' => null, 'category' => null],
    ])->and($parsed['duplicates'])->toBe(0)
        ->and($parsed['invalid'])->toBe(0)
        ->and($parsed['unsupported'])->toBe(0);
});

it('skips duplicate URLs and rejects unsafe feed URLs without scheduling them', function () {
    $xml = <<<'XML'
    <opml version="2.0"><body>
      <outline text="first" xmlUrl="https://example.com/rss"/>
      <outline text="duplicate" xmlUrl="https://example.com/rss"/>
      <outline text="ftp" xmlUrl="ftp://example.com/rss"/>
      <outline text="loopback" xmlUrl="http://127.0.0.1/feed"/>
      <outline text="localhost" xmlUrl="http://localhost/feed"/>
      <outline text="IPv6 loopback" xmlUrl="http://[::1]/feed"/>
      <outline text="short loopback" xmlUrl="http://127.1/feed"/>
      <outline text="integer loopback" xmlUrl="http://2130706433/feed"/>
      <outline text="hex loopback" xmlUrl="http://0x7f000001/feed"/>
      <outline text="credentials" xmlUrl="https://user:secret@example.com/feed"/>
    </body></opml>
    XML;

    $parsed = (new OpmlParser)->parse($xml);

    expect($parsed['feeds'])->toHaveCount(1)
        ->and($parsed['duplicates'])->toBe(1)
        ->and($parsed['invalid'])->toBe(7)
        ->and($parsed['unsupported'])->toBe(1);
});

it('rejects malformed XML, doctype declarations, excessive input and excessive nesting', function () {
    $parser = new OpmlParser;

    expect(fn () => $parser->parse('<opml><body>'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $parser->parse('<!DOCTYPE opml [<!ENTITY x "boom">]><opml><body/></opml>'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $parser->parse('<opml/>'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $parser->parse('<opml><head><body/></head></opml>'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $parser->parse(str_repeat(' ', OpmlParser::MAX_BYTES + 1)))
        ->toThrow(InvalidArgumentException::class);

    $nested = '<opml><body>'.str_repeat('<outline text="x">', 16).'<outline xmlUrl="https://example.com/feed"/>'.str_repeat('</outline>', 16).'</body></opml>';
    expect(fn () => $parser->parse($nested))->toThrow(InvalidArgumentException::class);
});

it('rejects documents with more than 500 feed outlines', function () {
    $outlines = '';
    for ($index = 0; $index <= OpmlParser::MAX_FEEDS; $index++) {
        $outlines .= '<outline text="Feed '.$index.'" xmlUrl="https://example.com/'.$index.'"/>';
    }

    expect(fn () => (new OpmlParser)->parse('<opml><body>'.$outlines.'</body></opml>'))
        ->toThrow(InvalidArgumentException::class);
});

it('ignores outlines outside the body and handles an empty body', function () {
    $parsed = (new OpmlParser)->parse(<<<'XML'
    <opml>
      <outline text="Outside before" xmlUrl="https://before.example/rss"/>
      <body/>
      <outline text="Outside after" xmlUrl="https://after.example/rss"/>
    </opml>
    XML);

    expect($parsed['feeds'])->toBe([])
        ->and($parsed['duplicates'])->toBe(0)
        ->and($parsed['invalid'])->toBe(0)
        ->and($parsed['unsupported'])->toBe(0);
});
