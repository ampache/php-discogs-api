<?php

declare(strict_types=1);

namespace AmpacheDiscogs\Live;

use AmpacheDiscogs\DiscogsException;

/**
 * Assertions that only the real api can settle: that credentials are accepted, that paging
 * returns different records, and that Discogs still treats a search type the way the client assumes.
 * Each request is throttled to a second, so keep this suite short.
 */
class DiscogsLiveTest extends LiveTestCase
{
    private const ARTIST_ID = 1;

    private const LABEL_ID = 1;

    public function testCredentialsAreAccepted(): void
    {
        $artist = $this->discogs->get_artist(self::ARTIST_ID);

        static::assertSame(self::ARTIST_ID, $artist['id'] ?? null);
        static::assertArrayHasKey('name', $artist);
    }

    public function testGetAlbumReachesBothMastersAndReleases(): void
    {
        $search = $this->discogs->search_master('Code 64', 'The Shape');
        $master = $this->discogs->get_master((int) $search['results'][0]['id']);

        static::assertArrayHasKey('main_release', $master, 'a master carries the release it points at');

        $release = $this->discogs->get_release((int) $master['main_release']);
        static::assertSame((int) $master['main_release'], $release['id']);
    }

    /**
     * Label releases page cleanly, so hold that one to the strict rule and let it catch a real
     * paging regression that the artist endpoint's own sloppiness would otherwise mask.
     */
    public function testLabelPagesDoNotOverlap(): void
    {
        $first  = $this->discogs->get_label_releases(self::LABEL_ID, 1);
        $second = $this->discogs->get_label_releases(self::LABEL_ID, 2);

        $firstIds  = array_column($first['releases'], 'id');
        $secondIds = array_column($second['releases'], 'id');

        static::assertNotEmpty($firstIds);
        static::assertNotEmpty($secondIds);
        static::assertSame([], array_values(array_intersect($firstIds, $secondIds)));
    }

    /**
     * A missing record must arrive as a 404 and must not be retried as though it were transient.
     */
    public function testMissingRecordReportsNotFound(): void
    {
        try {
            $this->discogs->get_master(999999999);
            static::fail('a missing master should throw');
        } catch (DiscogsException $error) {
            static::assertSame(404, $error->getStatusCode());
            static::assertFalse($error->isRateLimited());
        }
    }

    public function testPagingReachesEverythingTheLabelReports(): void
    {
        $page  = $this->discogs->get_label_releases(self::LABEL_ID, 1);
        $pages = (int) $page['pagination']['pages'];
        $total = (int) $page['pagination']['items'];

        static::assertGreaterThan(count($page['releases']), $total, 'page 1 alone should not hold everything');

        $last = $this->discogs->get_label_releases(self::LABEL_ID, $pages);
        static::assertSame($pages, $last['pagination']['page']);
        static::assertNotEmpty($last['releases'], 'the final page should still carry records');
    }

    /**
     * The whole reason the page parameter exists: page 2 has to hold records page 1 did not.
     *
     * Not asserted as disjoint. Discogs repeats a handful of releases across the page boundary of
     * this endpoint and returns 49 rows for a per_page of 50, under every sort and with no client
     * involved, so a caller walking the pages has to key on id. Only artists/{id}/releases behaves
     * this way; labels, master versions, wantlists and search all page cleanly.
     */
    public function testPagingReturnsDifferentReleases(): void
    {
        $first  = $this->discogs->get_artist_releases(self::ARTIST_ID, 1);
        $second = $this->discogs->get_artist_releases(self::ARTIST_ID, 2);

        static::assertSame(1, $first['pagination']['page']);
        static::assertSame(2, $second['pagination']['page']);
        static::assertGreaterThan(1, $first['pagination']['pages'], 'pick an artist with more than one page');

        $firstIds  = array_column($first['releases'], 'id');
        $secondIds = array_column($second['releases'], 'id');

        static::assertNotEmpty($firstIds);
        static::assertNotEmpty($secondIds);

        $fresh = array_diff($secondIds, $firstIds);
        static::assertNotEmpty($fresh, 'page 2 repeated page 1 entirely, so the page never reached Discogs');
        static::assertGreaterThan(
            count(array_intersect($firstIds, $secondIds)),
            count($fresh),
            'most of page 2 should be records page 1 did not carry'
        );
    }

    public function testSearchMasterReturnsOnlyMasters(): void
    {
        $results = $this->discogs->search_master('Code 64', 'The Shape');

        static::assertNotEmpty($results['results']);
        static::assertSame(['master'], array_values(array_unique(array_column($results['results'], 'type'))));
    }

    /**
     * Discogs ignores an unrecognised search type rather than rejecting it, which is how
     * search_release() used to return masters. Guard the assumption, not just our own string.
     */
    public function testSearchReleaseReturnsOnlyReleases(): void
    {
        $results = $this->discogs->search_release('Code 64', 'The Shape');

        static::assertNotEmpty($results['results']);
        static::assertSame(['release'], array_values(array_unique(array_column($results['results'], 'type'))));
    }

    public function testWantlistPagesForTheConfiguredUser(): void
    {
        $wantlist = $this->discogs->get_wantlist($this->username(), 1);

        static::assertArrayHasKey('pagination', $wantlist);
        static::assertSame(1, $wantlist['pagination']['page']);
        static::assertArrayHasKey('wants', $wantlist);
    }
}
