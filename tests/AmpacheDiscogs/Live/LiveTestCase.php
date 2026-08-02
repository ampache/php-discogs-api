<?php

declare(strict_types=1);

namespace AmpacheDiscogs\Live;

use AmpacheDiscogs\Discogs;
use PHPUnit\Framework\TestCase;

/**
 * Base for the tests that really call Discogs. Skipped unless credentials are present,
 * so the suite stays green on a fresh checkout and in CI without secrets.
 */
abstract class LiveTestCase extends TestCase
{
    protected Discogs $discogs;

    /**
     * Read a setting from the real environment first, then from a .env file at the repo root.
     * getenv() winning means CI can supply secrets without writing a file.
     */
    protected static function config(string $name): string
    {
        $fromEnv = getenv($name);
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }

        static $file = null;
        if ($file === null) {
            $file = [];
            $path = dirname(__DIR__, 3) . '/.env';
            if (is_readable($path)) {
                /** @var array<string, string> $parsed */
                $parsed = parse_ini_file($path, false, INI_SCANNER_RAW) ?: [];
                $file   = $parsed;
            }
        }

        return trim((string) ($file[$name] ?? ''));
    }

    protected function setUp(): void
    {
        $key    = self::config('DISCOGS_API_KEY');
        $secret = self::config('DISCOGS_API_SECRET');

        if ($key === '' || $secret === '') {
            static::markTestSkipped('set DISCOGS_API_KEY and DISCOGS_API_SECRET in .env to run the live suite');
        }

        $this->discogs = new Discogs($key, $secret);
    }

    /**
     * A Discogs username whose collection and wantlist are public.
     */
    protected function username(): string
    {
        $username = self::config('DISCOGS_TEST_USERNAME');
        if ($username === '') {
            static::markTestSkipped('set DISCOGS_TEST_USERNAME in .env to run the user endpoint tests');
        }

        return $username;
    }
}
