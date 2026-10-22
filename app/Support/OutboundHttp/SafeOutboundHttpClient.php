<?php

namespace App\Support\OutboundHttp;

use Closure;
use Throwable;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\UriInterface;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;

/** HTTP transport that validates and pins every destination before connecting. */
final class SafeOutboundHttpClient
{
    public function __construct(
        private readonly OutboundHostResolver $resolver,
        private readonly OutboundPeerAddressVerifier $peerAddressVerifier,
        private readonly OutboundHttpHandlerFactory $handlerFactory,
    ) {}

    /** @param array<string, string> $headers @param null|Closure(string, string, int, list<string>): void $beforeRedirect @param list<string> $visited */
    public function get(string $url, array $headers = [], int $maxBytes = 10_485_760, ?Closure $beforeRedirect = null, int $redirects = 0, array $visited = []): SafeOutboundResponse
    {
        return $this->send('GET', $url, $headers, [], $maxBytes, $beforeRedirect, false, $redirects, $visited);
    }

    /** @param array<string, string> $form @param null|Closure(string, string, int, list<string>): void $beforeRedirect @param list<string> $visited */
    public function postForm(string $url, array $form, int $maxBytes = 1_048_576, ?Closure $beforeRedirect = null, int $redirects = 0, array $visited = []): SafeOutboundResponse
    {
        return $this->send('POST', $url, ['Content-Type' => 'application/x-www-form-urlencoded'], $form, $maxBytes, $beforeRedirect, true, $redirects, $visited);
    }

    /** @param array<string, string> $headers @param array<string, string> $form @param null|Closure(string, string, int, list<string>): void $beforeRedirect @param list<string> $visited */
    private function send(string $method, string $url, array $headers, array $form, int $maxBytes, ?Closure $beforeRedirect, bool $sameOriginRedirectsOnly = false, int $redirects = 0, array $visited = []): SafeOutboundResponse
    {
        if (! extension_loaded('curl')) {
            throw new OutboundRequestRejected('curl_transport_unavailable');
        }

        $current = $this->validateUri($url);
        if ($redirects < 0 || $redirects > 5) {
            throw new OutboundRequestRejected('redirect_limit_or_location_invalid');
        }
        if ($visited === []) {
            $visited[] = (string) $current;
        }
        $initialOrigin = $this->origin($current);

        while (true) {
            $host = trim(strtolower((string) $current->getHost()), '[]');
            $port = $current->getPort() ?? ($current->getScheme() === 'https' ? 443 : 80);
            $addresses = $this->resolveAndValidate($host);
            $pinned = $addresses[0];
            $sink = new LimitedResponseStream($maxBytes);
            $peer = null;
            $pending = Http::withHeaders($headers)
                ->connectTimeout(15)
                ->timeout(30)
                ->withoutRedirecting()
                ->withOptions($this->requestOptions($host, $port, $pinned, $sink, $peer));

            try {
                $response = $method === 'POST'
                    ? $pending->asForm()->post((string) $current, $form)
                    : $pending->get((string) $current);
            } catch (ConnectionException|RequestException|TransferException) {
                if ($sink->exceededLimit()) {
                    throw new OutboundResponseTooLarge;
                }
                throw new ConnectionException('Outbound HTTP request failed.');
            }

            if ($sink->exceededLimit()) {
                throw new OutboundResponseTooLarge;
            }
            $this->peerAddressVerifier->assertPinned($peer, $pinned);
            $body = $sink->contents();
            if ($body === '' && $response->body() !== '') { // Http::fake does not use Guzzle's sink.
                $body = $response->body();
                if (strlen($body) > $maxBytes) {
                    throw new OutboundResponseTooLarge;
                }
            }
            $safeResponse = new SafeOutboundResponse($response, (string) $current, $body);

            if (! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                return $safeResponse;
            }

            $location = $response->header('Location');
            if (! $location || $redirects >= 5) {
                throw new OutboundRequestRejected('redirect_limit_or_location_invalid');
            }
            try {
                $redirectUri = UriResolver::resolve($current, new Uri($location));
            } catch (Throwable) {
                throw new OutboundRequestRejected('invalid_redirect');
            }
            $next = $this->validateUri((string) $redirectUri);
            if ($current->getScheme() === 'https' && $next->getScheme() !== 'https') {
                throw new OutboundRequestRejected('https_downgrade');
            }
            if ($sameOriginRedirectsOnly && $this->origin($next) !== $initialOrigin) {
                throw new OutboundRequestRejected('cross_origin_post_redirect');
            }
            $nextUrl = (string) $next;
            if (in_array($nextUrl, $visited, true)) {
                throw new OutboundRequestRejected('redirect_loop');
            }
            $redirects++;
            $visited[] = $nextUrl;
            $nextMethod = $response->status() === 303 || (($response->status() === 301 || $response->status() === 302) && $method === 'POST')
                ? 'GET'
                : $method;
            if ($beforeRedirect !== null) {
                $beforeRedirect($nextUrl, $nextMethod, $redirects, $visited);
            }
            if ($this->origin($next) !== $this->origin($current)) {
                $headers = array_intersect_key($headers, array_flip(['User-Agent', 'Accept']));
            }
            if ($response->status() === 303 || (($response->status() === 301 || $response->status() === 302) && $method === 'POST')) {
                $method = 'GET';
                $form = [];
                $headers = array_diff_key($headers, ['Content-Type' => true]);
                $sameOriginRedirectsOnly = false;
            }
            $current = $next;
        }
    }

    private function validateUri(string $url): UriInterface
    {
        if ($url === '' || preg_match('/[\x00-\x20\\\\]/', $url) || str_contains($url, '#') || preg_match('~^[a-z][a-z0-9+.-]*://[^/?#]*@~i', $url)) {
            throw new OutboundRequestRejected('ambiguous_url');
        }
        try {
            $uri = new Uri($url);
        } catch (Throwable) {
            throw new OutboundRequestRejected('invalid_url');
        }
        $scheme = strtolower($uri->getScheme());
        $uriHost = $uri->getHost();
        $host = trim($uriHost, '[]');
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || $uri->getUserInfo() !== '' || $uri->getFragment() !== '') {
            throw new OutboundRequestRejected('unsupported_url');
        }
        if ($uri->getPort() !== null && ($uri->getPort() < 1 || $uri->getPort() > 65535)) {
            throw new OutboundRequestRejected('invalid_port');
        }
        if (preg_match('/%(?![0-9a-f]{2})|%/i', $host) || str_contains($host, '%') || str_ends_with($host, '.')) {
            throw new OutboundRequestRejected('ambiguous_host');
        }
        if (! filter_var($host, FILTER_VALIDATE_IP) && preg_match('/^(?:[0-9.]+|0x[0-9a-f]+)$/i', $host)) {
            throw new OutboundRequestRejected('ambiguous_numeric_host');
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPublicIp($host);
        } elseif (! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $host)) {
            throw new OutboundRequestRejected('invalid_hostname');
        }

        return filter_var($host, FILTER_VALIDATE_IP)
            ? $uri->withScheme($scheme)
            : $uri->withScheme($scheme)->withHost(strtolower($host));
    }

    /** @return list<string> */
    private function resolveAndValidate(string $host): array
    {
        $answers = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolver->resolve($host);
        if ($answers === []) {
            throw new OutboundRequestRejected('host_resolution_failed');
        }
        foreach ($answers as $answer) {
            if (! filter_var($answer, FILTER_VALIDATE_IP)) {
                throw new OutboundRequestRejected('invalid_dns_answer');
            }
            $this->assertPublicIp($answer);
        }

        return array_values(array_unique($answers));
    }

    private function assertPublicIp(string $ip): void
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            throw new OutboundRequestRejected('invalid_ip');
        }
        if (strlen($packed) === 4) {
            $ranges = ['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'];
            foreach ($ranges as $range) {
                if ($this->inCidr($packed, $range)) {
                    throw new OutboundRequestRejected('non_public_ip');
                }
            }

            return;
        }
        $mappedPrefix = str_repeat("\0", 10)."\xff\xff";
        if (str_starts_with($packed, $mappedPrefix) || (ord($packed[0]) & 0xE0) !== 0x20 || $this->inCidr($packed, '2001::/23') || $this->inCidr($packed, '2001:db8::/32') || $this->inCidr($packed, '2002::/16') || $this->inCidr($packed, '3fff::/20')) {
            throw new OutboundRequestRejected('non_public_ip');
        }
    }

    private function inCidr(string $packed, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr, 2);
        $network = inet_pton($network);
        if ($network === false || strlen($network) !== strlen($packed)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;
        if (substr($packed, 0, $bytes) !== substr($network, 0, $bytes)) {
            return false;
        }

        return $remainder === 0 || ((ord($packed[$bytes]) ^ ord($network[$bytes])) & (0xFF << (8 - $remainder))) === 0;
    }

    private function origin(UriInterface $uri): string
    {
        return strtolower($uri->getScheme().'://'.$uri->getHost().':'.($uri->getPort() ?? ($uri->getScheme() === 'https' ? 443 : 80)));
    }

    /** @return array<int, mixed> */
    private function pinnedCurlOptions(string $host, int $port, string $address): array
    {
        $options = [
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            $options[CURLOPT_RESOLVE] = [$host.':'.$port.':'.(str_contains($address, ':') ? '['.$address.']' : $address)];
        }
        if (defined('CURLOPT_PROTOCOLS_STR') && defined('CURLOPT_REDIR_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'http,https';
            $options[CURLOPT_REDIR_PROTOCOLS_STR] = 'http,https';
        } else {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }

        return $options;
    }

    /** @return array<string, mixed> */
    private function requestOptions(string $host, int $port, string $pinned, LimitedResponseStream $sink, ?string &$peer): array
    {
        $options = [
            'allow_redirects' => false,
            'proxy' => '',
            'stream' => false,
            'http_errors' => false,
            'curl' => $this->pinnedCurlOptions($host, $port, $pinned),
            'sink' => $sink,
            'on_stats' => static function ($stats) use (&$peer): void {
                $peer = $stats->getHandlerStats()['primary_ip'] ?? null;
            },
        ];
        $handler = $this->handlerFactory->create();
        if ($handler !== null) {
            $options['handler'] = $handler;
        }

        return $options;
    }
}
