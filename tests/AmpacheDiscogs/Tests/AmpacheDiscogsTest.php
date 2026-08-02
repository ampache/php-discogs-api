<?php

declare(strict_types=1);

namespace AmpacheDiscogs\Tests;

use AmpacheDiscogs\Discogs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * The offline test suite works by subclassing Discogs and replacing its two seams.
 * Sealing the class or renaming either method breaks every other test at once, so name that here.
 */
class AmpacheDiscogsTest extends TestCase
{
    /**
     * @return list<array{string}>
     */
    public static function seamProvider(): array
    {
        return [['_http_get'], ['_sleep']];
    }

    #[DataProvider('seamProvider')]
    public function testKeepsItsSeamsOverridable(string $seam): void
    {
        static::assertTrue(method_exists(Discogs::class, $seam), $seam . ' is overridden by RecordingDiscogs');
        static::assertTrue(
            (new ReflectionMethod(Discogs::class, $seam))->isProtected(),
            $seam . ' must stay protected for a subclass to replace it'
        );
    }

    public function testStaysExtensibleForTesting(): void
    {
        static::assertFalse(
            (new ReflectionClass(Discogs::class))->isFinal(),
            'RecordingDiscogs extends Discogs to keep the suite offline'
        );
    }
}
