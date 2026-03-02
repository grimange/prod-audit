<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Rules\Evidence;
use ProdAudit\Utils\Fingerprint;

final class FingerprintTest extends TestCase
{
    public function testFingerprintIsDeterministicAndOrderIndependent(): void
    {
        $evidenceOne = Evidence::create('file_snippet', 'src/Foo.php', 10, 10, 'while (true) {}');
        $evidenceTwo = Evidence::create('grep_match', 'src/Bar.php', 20, 20, 'catch (Throwable $e) {}');

        $fingerprintA = Fingerprint::fromEvidence('PR-HANG-001', [$evidenceOne, $evidenceTwo]);
        $fingerprintB = Fingerprint::fromEvidence('PR-HANG-001', [$evidenceTwo, $evidenceOne]);

        self::assertSame($fingerprintA, $fingerprintB);
    }

    public function testFingerprintChangesWhenEvidenceChanges(): void
    {
        $base = Evidence::create('file_snippet', 'src/Foo.php', 10, 10, 'while (true) {}');
        $changed = Evidence::create('file_snippet', 'src/Foo.php', 11, 11, 'while (true) {}');

        $fingerprintA = Fingerprint::fromEvidence('PR-HANG-001', [$base]);
        $fingerprintB = Fingerprint::fromEvidence('PR-HANG-001', [$changed]);

        self::assertNotSame($fingerprintA, $fingerprintB);
    }
}
