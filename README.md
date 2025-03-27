# php-discogs-api (AmpacheDiscogs)

This library is a simple Discogs query library exported from the [Ampache Discogs plugin](https://github.com/ampache/ampache/blob/develop/src/Plugin/AmpacheDiscogs.php).

The focus here is on keeping it small and simple.

All data is JSON decoded with objects converted into associative arrays.

## Requirements

* PHP8.2+
* rmccue/requests

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
    // your own username and password are required to use the Discogs API
    $username = 'username';
    $password = 'password';
    $discogs  = new Discogs($username, $password);

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
