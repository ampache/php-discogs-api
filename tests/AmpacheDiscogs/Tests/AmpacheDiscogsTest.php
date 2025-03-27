<?php

namespace AmpacheDiscogs\Tests;

use AmpacheDiscogs\Discogs;
use PHPUnit\Framework\TestCase;

class AmpacheDiscogsTest extends TestCase
{
    public function testIsDiscogs(): void
    {
        static::assertTrue(
            new Discogs(
                'user',
                'password'
            ) instanceof Discogs
        );
    }
}
