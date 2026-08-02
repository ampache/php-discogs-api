<?php

declare(strict_types=1);

namespace AmpacheDiscogs\Tests;

use AmpacheDiscogs\Discogs;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers the url the client builds and how it reacts to a bad response.
 * Every public method funnels through _query_discogs, so these assert the one thing they all share.
 */
class DiscogsRequestTest extends TestCase
{
    /**
     * @return list<array{string, string}>
     */
    public static function awkwardUsernameProvider(): array
    {
        return [
            ['plain', '/users/plain'],
            ['with space', '/users/with%20space'],
            ['a/b', '/users/a%2Fb'],
            ['x?type=artist', '/users/x%3Ftype%3Dartist'],
            ['e&secret=leak', '/users/e%26secret%3Dleak'],
            ['d#frag', '/users/d%23frag'],
        ];
    }

    /**
     * @return list<array{int}>
     */
    public static function clampedPageProvider(): array
    {
        return [[0], [-1], [PHP_INT_MIN]];
    }

    /**
     * @return list<array{string, list<int|string>, string}>
     */
    public static function paginatedCallProvider(): array
    {
        return [
            ['get_artist_releases', [45, 3], '/artists/45/releases'],
            ['get_label_releases', [3, 4], '/labels/3/releases'],
            ['get_master_versions', [7, 2], '/masters/7/versions'],
            ['get_collection_items_by_folder', ['someone', 0, 5], '/users/someone/collection/folders/0/releases'],
            ['get_user_lists', ['someone', 6], '/users/someone/lists'],
            ['get_wantlist', ['someone', 7], '/users/someone/wants'],
        ];
    }

    /**
     * @return list<array{string, int|string, string}>
     */
    public static function pathProvider(): array
    {
        return [
            ['get_artist', 45, '/artists/45'],
            ['get_artist_releases', 45, '/artists/45/releases'],
            ['get_master', 7, '/masters/7'],
            ['get_master_versions', 7, '/masters/7/versions'],
            ['get_release', 9, '/releases/9'],
            ['get_label', 3, '/labels/3'],
            ['get_label_releases', 3, '/labels/3/releases'],
            ['get_list', 11, '/lists/11'],
            ['get_profile', 'someone', '/users/someone'],
            ['get_collection_folders', 'someone', '/users/someone/collection/folders'],
            ['get_user_lists', 'someone', '/users/someone/lists'],
            ['get_wantlist', 'someone', '/users/someone/wants'],
        ];
    }

    public function testAppendsCredentialsToEveryRequest(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->get_artist(45);

        $request = $discogs->lastRequest();
        static::assertSame('KEY', $request['query']['key']);
        static::assertSame('SECRET', $request['query']['secret']);
    }

    #[DataProvider('pathProvider')]
    public function testBuildsTheDocumentedPath(string $method, int|string $argument, string $expected): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->{$method}($argument);

        static::assertSame($expected, $discogs->lastRequest()['path']);
    }

    /**
     * Discogs numbers pages from 1 and errors on anything lower, so a bad page is clamped rather than sent.
     */
    #[DataProvider('clampedPageProvider')]
    public function testClampsAPageBelowOne(int $page): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->get_label_releases(3, $page);

        static::assertSame('1', $discogs->lastRequest()['query']['page']);
    }

    public function testCredentialsAreTrimmed(): void
    {
        $discogs = new RecordingDiscogs("  KEY\n", "\tSECRET ");
        $discogs->get_artist(45);

        $request = $discogs->lastRequest();
        static::assertSame('KEY', $request['query']['key']);
        static::assertSame('SECRET', $request['query']['secret']);
    }

    public function testDefaultsToTheFirstPage(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->get_label_releases(3);

        static::assertSame('1', $discogs->lastRequest()['query']['page']);
    }

    /**
     * An unencoded username could add path segments or start the query string early,
     * which pushed the real key and secret into a parameter value and broke authentication.
     */
    #[DataProvider('awkwardUsernameProvider')]
    public function testEncodesUsernamesIntoASinglePathSegment(string $username, string $expected): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->get_profile($username);

        $request = $discogs->lastRequest();
        static::assertSame($expected, $request['path']);
        static::assertSame('KEY', $request['query']['key']);
        static::assertSame('SECRET', $request['query']['secret']);
    }

    public function testGetAlbumDefaultsToTheMasterRelease(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->get_album(7);

        static::assertSame('/masters/7', $discogs->lastRequest()['path']);
    }

    public function testGetAlbumHonoursTheReleaseType(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->get_album(9, 'releases');

        static::assertSame('/releases/9', $discogs->lastRequest()['path']);
    }

    public function testSearchAlbumSendsTheDocumentedParameters(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->search_album('Code 64', 'The Shape');

        $request = $discogs->lastRequest();
        static::assertSame('/database/search', $request['path']);
        static::assertSame('master', $request['query']['type']);
        static::assertSame('Code 64', $request['query']['artist']);
        static::assertSame('The Shape', $request['query']['release_title']);
        static::assertSame('10', $request['query']['per_page']);
    }

    public function testSearchDefaultsPerPageButLetsCallersOverrideIt(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->search(['type' => 'artist', 'per_page' => 50]);

        static::assertSame('50', $discogs->lastRequest()['query']['per_page']);
    }

    public function testSearchEncodesParametersThatNeedIt(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->search_album('AC/DC', 'Back in Black & Blue');

        $request = $discogs->lastRequest();
        static::assertSame('AC/DC', $request['query']['artist']);
        static::assertSame('Back in Black & Blue', $request['query']['release_title']);
        static::assertSame('SECRET', $request['query']['secret']);
    }

    public function testSearchMasterFiltersToMasters(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->search_master('Code 64', 'The Shape');

        static::assertSame('master', $discogs->lastRequest()['query']['type']);
    }

    public function testSearchPagesThroughResults(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->search_album('Ladytron', 'Witching Hour', 'release', 4);

        $request = $discogs->lastRequest();
        static::assertSame('4', $request['query']['page']);
        static::assertSame('release', $request['query']['type']);
        static::assertSame('10', $request['query']['per_page']);
    }

    /**
     * Discogs silently ignores an unknown search type and returns everything, so the plural
     * spelling used to make search_release() hand back masters mixed in with the releases.
     */
    public function testSearchReleaseFiltersToReleases(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->search_release('Code 64', 'The Shape');

        static::assertSame('release', $discogs->lastRequest()['query']['type']);
    }

    public function testSearchWrappersForwardThePage(): void
    {
        $release = new RecordingDiscogs('KEY', 'SECRET');
        $release->search_release('Ladytron', 'Witching Hour', 2);
        static::assertSame('2', $release->lastRequest()['query']['page']);
        static::assertSame('release', $release->lastRequest()['query']['type']);

        $master = new RecordingDiscogs('KEY', 'SECRET');
        $master->search_master('Ladytron', 'Witching Hour', 3);
        static::assertSame('3', $master->lastRequest()['query']['page']);
        static::assertSame('master', $master->lastRequest()['query']['type']);

        $artist = new RecordingDiscogs('KEY', 'SECRET');
        $artist->search_artist('Ladytron', 5);
        static::assertSame('5', $artist->lastRequest()['query']['page']);
    }

    public function testSendsIdentifyingUserAgent(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->get_artist(45);

        static::assertSame('AmpacheDiscogs/' . Discogs::VERSION, $discogs->headers[0]['User-Agent']);
        static::assertSame('application/json', $discogs->headers[0]['Accept']);
    }

    /**
     * @param list<int|string> $arguments the last of which is the page
     */
    #[DataProvider('paginatedCallProvider')]
    public function testSendsTheRequestedPage(string $method, array $arguments, string $expectedPath): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET');
        $discogs->{$method}(...$arguments);

        $request = $discogs->lastRequest();
        static::assertSame($expectedPath, $request['path']);
        static::assertSame((string) $arguments[count($arguments) - 1], $request['query']['page']);
        static::assertSame('KEY', $request['query']['key'], 'the page must not displace the credentials');
    }

    public function testThrowsWhenDiscogsReportsFailure(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET', [
            ['status' => 404, 'body' => '{"message":"Release not found"}', 'headers' => []],
        ]);

        static::expectException(Exception::class);
        static::expectExceptionMessageMatches('/Release not found/');

        $discogs->get_artist(45);
    }

    public function testThrowsWhenResponseIsNotJson(): void
    {
        $discogs = new RecordingDiscogs('KEY', 'SECRET', [
            ['status' => 200, 'body' => '<html>Gateway</html>', 'headers' => []],
        ]);

        static::expectException(Exception::class);

        $discogs->get_artist(45);
    }
}
