<?php

namespace Tests\Support;

use App\Support\OutboundHttp\OutboundPeerAddressVerifier;

final class FakeOutboundPeerAddressVerifier implements OutboundPeerAddressVerifier
{
    public function assertPinned(?string $peerAddress, string $expectedAddress): void {}
}
