<?php

declare(strict_types=1);

namespace AmpacheDiscogs\Tests;

use AmpacheDiscogs\DiscogsException;
use PHPUnit\Framework\TestCase;

/**
 * Discogs allows 60 authenticated requests a minute and answers 429 once that is exceeded.
 * These cover the throttle that avoids it and the retry that recovers when it happens anyway.
 */
class DiscogsRateLimitTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    private static function response(int $status, string $body = '{"id":1}', array $headers = []): array
    {
        return ['status' => $status, 'body' => $body, 'headers' => $headers];
    }

    public function testDoesNotRetryANotFound(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET', [
            self::response(404, '{"message":"not found"}'),
        ]);

        try {
            $discogs->get_artist(45);
            static::fail('a 404 should surface as a DiscogsException');
        } catch (DiscogsException $error) {
            static::assertSame(404, $error->getStatusCode());
            static::assertFalse($error->isRateLimited());
        }

        static::assertCount(1, $discogs->urls, 'a 404 is the caller\'s answer, not a transient failure');
    }

    public function testDoesNotWaitBeforeTheFirstRequest(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->get_artist(45);

        static::assertSame([], $discogs->sleeps);
    }

    public function testGivesUpAfterThreeAttemptsAndReportsTheStatus(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET', [
            self::response(429, '{"message":"too quickly"}'),
        ]);

        try {
            $discogs->get_artist(45);
            static::fail('a persistent 429 should surface as a DiscogsException');
        } catch (DiscogsException $error) {
            static::assertSame(429, $error->getStatusCode());
            static::assertTrue($error->isRateLimited());
        }

        static::assertCount(3, $discogs->urls);
    }

    public function testHonoursRetryAfterWhenDiscogsSendsOne(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET', [
            self::response(429, '{"message":"too quickly"}', ['Retry-After' => '3']),
            self::response(200),
        ]);
        $discogs->get_artist(45);

        static::assertSame(3000000, $discogs->sleeps[0]);
    }

    public function testKeepsASecondBetweenConsecutiveRequests(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->get_artist(45);
        $discogs->get_artist(46);

        static::assertCount(1, $discogs->sleeps);
        static::assertGreaterThan(900000, $discogs->sleeps[0]);
        static::assertLessThanOrEqual(1000000, $discogs->sleeps[0]);
    }

    public function testRetriesAfterARateLimitAndReturnsTheRetriedBody(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET', [
            self::response(429, '{"message":"too quickly"}'),
            self::response(200, '{"id":42}'),
        ]);

        static::assertSame(['id' => 42], $discogs->get_artist(45));
        static::assertCount(2, $discogs->urls);
    }

    public function testRetriesAGatewayFailure(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET', [
            self::response(503, 'unavailable'),
            self::response(200, '{"id":7}'),
        ]);

        static::assertSame(['id' => 7], $discogs->get_artist(45));
    }

    public function testSlowsDownWhenTheReportedBudgetRunsLow(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET', [
            self::response(200, '{"id":1}', ['X-Discogs-Ratelimit-Remaining' => '2']),
        ]);
        $discogs->get_artist(45);
        $discogs->get_artist(46);

        static::assertGreaterThan(1900000, $discogs->sleeps[0]);
    }
}
