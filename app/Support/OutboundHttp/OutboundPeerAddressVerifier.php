<?php

namespace App\Support\OutboundHttp;

interface OutboundPeerAddressVerifier
{
    public function assertPinned(?string $peerAddress, string $expectedAddress): void;
}
