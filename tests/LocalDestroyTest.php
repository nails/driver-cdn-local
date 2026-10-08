<?php

namespace Tests\Cdn\Driver;

use Nails\Cdn\Driver\Local;
use PHPUnit\Framework\TestCase;

class TestableLocal extends Local
{
    public function __construct(private readonly string $sPath)
    {
        parent::__construct();
    }

    protected function getPath(): string
    {
        return rtrim($this->sPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}

/**
 * @covers \Nails\Cdn\Driver\Local
 */
class LocalDestroyTest extends TestCase
{
    private string $sDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sDir = sys_get_temp_dir() . '/nails-cdn-local-' . uniqid('', true);
        mkdir($this->sDir . '/bucket', 0777, true);
    }

    protected function tearDown(): void
    {
        $sFile = $this->sDir . '/bucket/keep.mp3';
        if (is_file($sFile)) {
            unlink($sFile);
        }
        if (is_dir($this->sDir . '/bucket')) {
            rmdir($this->sDir . '/bucket');
        }
        if (is_dir($this->sDir)) {
            rmdir($this->sDir);
        }
        parent::tearDown();
    }

    public function test_object_destroy_returns_true_when_the_file_is_already_gone(): void
    {
        $oDriver = new TestableLocal($this->sDir);

        self::assertTrue($oDriver->objectDestroy('missing.mp3', 'bucket'));
        self::assertFalse($oDriver->lastError());
    }

    public function test_object_destroy_unlinks_an_existing_file(): void
    {
        $sFile = $this->sDir . '/bucket/keep.mp3';
        file_put_contents($sFile, 'audio');
        $oDriver = new TestableLocal($this->sDir);

        self::assertTrue($oDriver->objectDestroy('keep.mp3', 'bucket'));
        self::assertFileDoesNotExist($sFile);
    }
}
