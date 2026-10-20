<?php

namespace App\Domains\News\Support;

/**
 * Extracts article body and preview image metadata from one publisher response.
 */
final class ArticleHtmlExtractor
{
    /**
     * @return array{body: ?string, image: ?string}
     */
    public function extract(string $html): array
    {
        return [
            'body' => $this->extractBody($html),
            'image' => $this->extractImage($html),
        ];
    }

    private function extractBody(string $html): ?string
    {
        if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $matches)) {
            return $this->cleanHtml($matches[1]);
        }

        if (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $matches)) {
            return $this->cleanHtml($matches[1]);
        }

        if (preg_match('/<div[^>]+class=["\'][^"\']*(?:content|post-body|entry-content)[^"\']*["\'][^>]*>(.*?)<\/div>/is', $html, $matches)) {
            return $this->cleanHtml($matches[1]);
        }

        return null;
    }

    private function extractImage(string $html): ?string
    {
        if (! preg_match_all('/<meta\b[^>]*>/i', $html, $tags)) {
            return null;
        }

        foreach (['og:image', 'twitter:image'] as $wanted) {
            foreach ($tags[0] as $tag) {
                if (! preg_match('/\b(?:property|name)\s*=\s*(["\'])'.preg_quote($wanted, '/').'\1/i', $tag)) {
                    continue;
                }

                if (! preg_match('/\bcontent\s*=\s*(["\'])(.*?)\1/i', $tag, $content)) {
                    continue;
                }

                $url = html_entity_decode(trim($content[2]), ENT_QUOTES | ENT_HTML5);
                if (filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                    return $url;
                }
            }
        }

        return null;
    }

    private function cleanHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/\s+on\w+=(?:["\'][^"\']*["\']|[^\s>]+)/i', '', $html) ?? $html;

        return trim($html);
    }
}
