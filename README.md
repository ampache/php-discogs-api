# php-discogs-api (AmpacheDiscogs)

This library is a simple Discogs query library exported from the [Ampache Discogs plugin](https://github.com/ampache/ampache/blob/develop/src/Plugin/AmpacheDiscogs.php).

The focus here is on keeping it small and simple.

All data is JSON decoded with objects converted into associative arrays.

## License

AGPL-3.0-or-later. See [LICENSE.md](LICENSE.md).

This library is an export of the Ampache Discogs plugin and carries Ampache's license. Releases
before 0.3.0 declared `MIT` in `composer.json`; that was a mistake in the metadata, not a different
license — the AGPL text and the source headers were there from the initial commit.

## Requirements

* PHP8.2+
* rmccue/requests

## Paging

Discogs paginates the list endpoints. Every method that hits one takes a trailing `int $page = 1`,
and the `pagination` block in the response says how many pages there are:

```php
$page  = $discogs->get_label_releases(1);
$total = $page['pagination']['pages'];

for ($number = 1; $number <= $total; $number++) {
    $releases = $discogs->get_label_releases(1, $number)['releases'];
    // ...
}
```

Paged: `get_artist_releases`, `get_label_releases`, `get_master_versions`,
`get_collection_items_by_folder`, `get_user_lists`, `get_wantlist`, `search_album`,
`search_artist`, `search_master`, `search_release`. Pass a page to `search()` in its parameter
array. Anything returning a single record takes no page.

Each request is paced to about a second, so walking a long list is not instant.

**`get_artist_releases()` repeats a few records across page boundaries** and returns 49 rows for a
`per_page` of 50. That is Discogs, not this library: it happens on the raw endpoint under every
`sort` option. Key on `id` when collecting pages from it:

```php
$byId = [];
for ($number = 1; $number <= $total; $number++) {
    foreach ($discogs->get_artist_releases($artistId, $number)['releases'] as $release) {
        $byId[$release['id']] = $release;
    }
}
```

The other paged endpoints — label releases, master versions, wantlists and search — return
non-overlapping pages.

## Rate limiting and errors

Discogs allows 60 authenticated requests a minute. The client paces itself to that, widens the gap
as `X-Discogs-Ratelimit-Remaining` runs down, and retries a `429` or a `5xx` up to three times,
honouring `Retry-After`. Nothing is required of the caller for that to happen.

Anything that still fails throws `AmpacheDiscogs\DiscogsException`, which extends `Exception` and
carries the HTTP status so the cases can be told apart:

```php
use AmpacheDiscogs\DiscogsException;

try {
    $album = $discogs->get_master(1234);
} catch (DiscogsException $error) {
    if ($error->getStatusCode() === 404) {
        // no such record, nothing to retry
    } elseif ($error->isRateLimited()) {
        // still limited after three attempts, come back later
    }

    print_r($error->getMessage());
}
```

## Usage Example

```php
<?php

use AmpacheDiscogs\Discogs;

require dirname(__DIR__) . '/vendor/autoload.php';

$media = [
    [
        'album' => 'The Shape',
        'albumartist' => 'Code 64',
    ],
];

echo "Checking: " . print_r($media, true) . PHP_EOL;
try {
    // your own Discogs api key and secret are required to use the Discogs API
    $api_key = 'yourApiKey';
    $secret  = 'yourApiSecret';
    $discogs = new Discogs($api_key, $secret);

    /**
     * https://api.discogs.com/database/search?type=master&release_title=The+Shape&artist=Code+64&per_page=10&key=key@secret=secret
     */
    $albums = $discogs->search_album($media['albumartist'], $media['album']);
    if (empty($albums['results'])) {
        $albums = $discogs->search_album($media['albumartist'], $media['album'], 'release');
    }

    // get the album that matches $artist - $album
    if (!empty($albums['results'])) {
        foreach ($albums['results'] as $albumSearch) {
            if ($media['albumartist'] . ' - ' . $media['album'] === $albumSearch['title']) {
                /**
                 * @var array{
                 *     country: string,
                 *     year: string,
                 *     format: string[],
                 *     label: string[],
                 *     type: string,
                 *     genre: string[],
                 *     style: string[],
                 *     id: ?int,
                 *     barcode: string[],
                 *     master_id: int,
                 *     master_url: string,
                 *     uri: string,
                 *     catno: string,
                 *     title: string,
                 *     thumb: string,
                 *     cover_image: string,
                 *     resource_url: string,
                 *     community: object,
                 *     format_quantity: ?int,
                 *     formats: ?object,
                 * } $albumSearch
                 */
                $album = $albumSearch;
                break;
            }
        }

        // look up the master release if we have one or the first release
        if (!isset($album['id'])) {
            /**
             * @var array{
             *     id: ?int,
             *     main_release: int,
             *     most_recent_release: int,
             *     uri: string,
             *     versions_uri: string,
             *     main_release_uri: string,
             *     most_recent_release_uri: string,
             *     num_for_sale: int,
             *     lowest_price: int,
             *     images: object,
             *     genres: string[],
             *     styles: string[],
             *     year: int,
             *     tracklist: object,
             *     artists: object,
             *     title: string,
             *     data-quality: string,
             *     videos: object,
             * } $album
             */
            $album = (($albums['results'][0]['master_id'] ?? 0) > 0)
                ? $discogs->get_album((int)$albums['results'][0]['master_id'])
                : $discogs->get_album((int)$albums['results'][0]['id'], 'releases');
        }

        // fallback to the initial search if we don't have a master
        if (!isset($album['id'])) {
            $album = $albums['results'][0];
        }

        print_r($album);
    }

} catch (Exception $exception) {
    print_r($exception->getMessage());
}
```

Look in the [/examples](https://github.com/ampache/php-discogs-api/tree/master/examples) folder for more.

## Testing

```shell
composer qa          # syntax, code style and the offline unit tests
composer stan        # static analysis
composer tests:live  # really calls Discogs, needs credentials
```

The unit suite never touches the network. The live suite is opt-in: copy `.env.dist` to `.env` and
fill in a key and secret from <https://www.discogs.com/settings/developers>. Without them the live
tests skip, so `composer qa` and CI stay green on a fresh checkout. `.env` is gitignored.

```ini
DISCOGS_API_KEY=yourApiKey
DISCOGS_API_SECRET=yourApiSecret
DISCOGS_TEST_USERNAME=someUserWithAPublicCollection
```

`DISCOGS_TEST_USERNAME` is optional and only gates the user endpoint tests. Environment variables
take precedence over the file, so CI can supply the same names as secrets.
