<?php

namespace App\Support\OutboundHttp;

final class SystemOutboundHostResolver implements OutboundHostResolver
{
    public function resolve(string $hostname): array
    {
        $addresses = [];
        $this->resolveName($hostname, [], $addresses, 0);

        if ($addresses === []) {
            throw new OutboundRequestRejected('host_resolution_failed');
        }

        return array_values(array_unique($addresses));
    }

    /** @param list<string> $visited @param list<string> $addresses */
    private function resolveName(string $hostname, array $visited, array &$addresses, int $depth): void
    {
        if ($depth >= 8 || in_array($hostname, $visited, true)) {
            throw new OutboundRequestRejected('host_resolution_failed');
        }

        $records = @dns_get_record($hostname, DNS_A | DNS_AAAA | DNS_CNAME);
        if (! is_array($records) || $records === []) {
            throw new OutboundRequestRejected('host_resolution_failed');
        }

        $visited[] = $hostname;
        $aliases = [];

        foreach ($records as $record) {
            if (($record['type'] ?? null) === 'A' && isset($record['ip'])) {
                $addresses[] = $record['ip'];
            } elseif (($record['type'] ?? null) === 'AAAA' && isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            } elseif (($record['type'] ?? null) === 'CNAME' && isset($record['target'])) {
                $aliases[] = strtolower(rtrim($record['target'], '.'));
            }
        }

        foreach (array_unique($aliases) as $alias) {
            $this->resolveName($alias, $visited, $addresses, $depth + 1);
        }
    }
}
