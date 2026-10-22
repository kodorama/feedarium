<?php

namespace App\Support\OutboundHttp;

final class SystemOutboundPeerAddressVerifier implements OutboundPeerAddressVerifier
{
    public function assertPinned(?string $peerAddress, string $expectedAddress): void
    {
        if ($peerAddress === null || @inet_pton($peerAddress) === false || @inet_pton($peerAddress) !== @inet_pton($expectedAddress)) {
            throw new OutboundRequestRejected('connected_peer_unverified');
        }
    }
}
