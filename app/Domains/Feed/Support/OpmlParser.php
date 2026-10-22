<?php

namespace App\Domains\Feed\Support;

use XMLReader;
use InvalidArgumentException;

/**
 * Parses bounded OPML documents without loading external entities or resources.
 */
final class OpmlParser
{
    public const MAX_BYTES = 2_097_152;

    public const MAX_FEEDS = 500;

    private const MAX_DEPTH = 16;

    /**
     * @return array{feeds: list<array{name: string, url: string, description: ?string, category: ?string}>, duplicates: int, invalid: int, unsupported: int}
     */
    public function parse(string $xml): array
    {
        if ($xml === '' || strlen($xml) > self::MAX_BYTES) {
            throw new InvalidArgumentException('The OPML file is empty or exceeds the 2 MB limit.');
        }

        $previousErrorMode = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $reader = XMLReader::XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);

        if ($reader === false) {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);

            throw new InvalidArgumentException('The uploaded file is not valid XML.');
        }

        $reader->setParserProperty(XMLReader::LOADDTD, false);
        $reader->setParserProperty(XMLReader::SUBST_ENTITIES, false);
        $reader->setParserProperty(XMLReader::VALIDATE, false);

        $rootSeen = false;
        $bodySeen = false;
        $bodyDepth = null;
        $folderStack = [];
        $feeds = [];
        $seenUrls = [];
        $duplicates = 0;
        $invalid = 0;
        $unsupported = 0;
        $outlineCount = 0;

        try {
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::DOC_TYPE) {
                    throw new InvalidArgumentException('OPML files with document type declarations are not accepted.');
                }

                if ($reader->nodeType === XMLReader::ELEMENT) {
                    if (! $rootSeen) {
                        $rootSeen = true;
                        if (strtolower($reader->localName) !== 'opml') {
                            throw new InvalidArgumentException('The uploaded XML document must have an OPML root element.');
                        }
                    }

                    if (strtolower($reader->localName) === 'body') {
                        if ($reader->depth !== 1 || $bodySeen) {
                            throw new InvalidArgumentException('The OPML document must contain one body element directly under its root.');
                        }

                        $bodySeen = true;
                        $bodyDepth = $reader->isEmptyElement ? null : $reader->depth;

                        continue;
                    }

                    if ($bodyDepth === null || $reader->depth <= $bodyDepth || strtolower($reader->localName) !== 'outline') {
                        continue;
                    }

                    if ($reader->depth > self::MAX_DEPTH) {
                        throw new InvalidArgumentException('The OPML outline nesting exceeds the 16-level limit.');
                    }

                    $outlineCount++;
                    if ($outlineCount > self::MAX_FEEDS * 4) {
                        throw new InvalidArgumentException('The OPML file contains too many outline elements.');
                    }

                    while ($folderStack !== [] && $folderStack[array_key_last($folderStack)]['depth'] >= $reader->depth) {
                        array_pop($folderStack);
                    }

                    $attributes = [
                        'xml_url' => $reader->getAttribute('xmlUrl'),
                        'text' => $reader->getAttribute('text'),
                        'title' => $reader->getAttribute('title'),
                        'description' => $reader->getAttribute('description'),
                        'category' => $reader->getAttribute('category'),
                    ];
                    $folderPath = $folderStack === [] ? [] : $folderStack[array_key_last($folderStack)]['path'];
                    $label = $this->cleanLabel($attributes['title'] ?? '') ?: $this->cleanLabel($attributes['text'] ?? '');

                    if ($attributes['xml_url'] !== null && trim($attributes['xml_url']) !== '') {
                        $url = trim($attributes['xml_url']);

                        $rejection = $this->feedUrlRejection($url);
                        if ($rejection !== null) {
                            if ($rejection === 'unsupported') {
                                $unsupported++;
                            } else {
                                $invalid++;
                            }

                            continue;
                        }

                        $key = $url;
                        if (isset($seenUrls[$key])) {
                            $duplicates++;

                            continue;
                        }

                        $seenUrls[$key] = true;
                        if (count($feeds) >= self::MAX_FEEDS) {
                            throw new InvalidArgumentException('The OPML file contains more than 500 feed outlines.');
                        }

                        $category = $folderPath !== []
                            ? implode(' / ', $folderPath)
                            : ($this->cleanLabel($attributes['category'] ?? '') ?: null);

                        if ($category !== null && mb_strlen($category) > 255) {
                            $invalid++;

                            continue;
                        }

                        $name = $label ?: $url;
                        $feeds[] = [
                            'name' => mb_substr($name, 0, 255),
                            'url' => $url,
                            'description' => trim($attributes['description'] ?? '') ?: null,
                            'category' => $category,
                        ];

                        continue;
                    }

                    if ($label !== '') {
                        $folderStack[] = [
                            'depth' => $reader->depth,
                            'path' => [...$folderPath, mb_substr($label, 0, 255)],
                        ];
                    }
                } elseif ($reader->nodeType === XMLReader::END_ELEMENT && strtolower($reader->localName) === 'body') {
                    $bodyDepth = null;
                    $folderStack = [];
                }
            }

            if (! $rootSeen || ! $bodySeen || libxml_get_errors() !== []) {
                throw new InvalidArgumentException('The uploaded file is not valid OPML XML.');
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }

        return [
            'feeds' => $feeds,
            'duplicates' => $duplicates,
            'invalid' => $invalid,
            'unsupported' => $unsupported,
        ];
    }

    private function cleanLabel(string $label): string
    {
        return trim($label);
    }

    private function feedUrlRejection(string $url): ?string
    {
        if (strlen($url) > 255 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return 'invalid';
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return 'invalid';
        }

        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return 'unsupported';
        }

        $host = strtolower(rtrim(trim($parts['host'], '[]'), '.'));
        if (in_array($host, ['localhost', 'localhost.localdomain'], true)
            || preg_match('/\.(?:localhost|local|internal|test)$/', $host) === 1) {
            return 'invalid';
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return 'invalid';
            }
        } elseif (preg_match('/^(?:(?:0x[0-9a-f]+|[0-9]+)\.)*(?:0x[0-9a-f]+|[0-9]+)$/i', $host) === 1) {
            // Reject non-canonical numeric hosts that HTTP clients may interpret as IP literals.
            return 'invalid';
        }

        return null;
    }
}
