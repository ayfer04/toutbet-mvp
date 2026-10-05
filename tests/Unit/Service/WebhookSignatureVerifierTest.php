<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Payment\WebhookSignatureVerifier;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureVerifierTest extends TestCase
{
    private WebhookSignatureVerifier $verifier;

    protected function setUp(): void
    {
        $this->verifier = new WebhookSignatureVerifier('whsec_unit_test_secret');
    }

    public function testValidSignatureIsAccepted(): void
    {
        self::assertTrue($this->verifier->isValid('{"a":1}', $this->verifier->sign('{"a":1}', 1000), 1000));
    }

    public function testTamperedPayloadIsRejected(): void
    {
        self::assertFalse($this->verifier->isValid('{"a":2}', $this->verifier->sign('{"a":1}', 1000), 1000));
    }

    public function testReplayedOldSignatureIsRejected(): void
    {
        self::assertFalse($this->verifier->isValid('{"a":1}', $this->verifier->sign('{"a":1}', 1000), 1000 + 301));
    }

    public function testMissingSignatureIsRejected(): void
    {
        self::assertFalse($this->verifier->isValid('{"a":1}', '', 1000));
    }
}
