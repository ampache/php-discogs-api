<?php

use AmpacheDiscogs\Discogs;

require dirname(__DIR__) . '/vendor/autoload.php';

// your own username and password are required to use the Discogs API
$username = null;
$password = null;
$discogs  = new Discogs($username, $password);

$media_info = [
    [
        'album' => 'The Shape',
        'albumartist' => 'Code 64',
    ],
    [
        'album' => null,
        'artist' => 'Code 64',
    ],
    [
        'album' => 'nothingishereandneverwillbe',
        'artist' => 'nothingishereandneverwillbe',
    ],
];

foreach ($media_info as $media) {
    echo "Checking: " . print_r($media, true) . PHP_EOL;
    $results = [];
    try {
        if (isset($media['artist']) && ($media['artist'] !== '' && $media['artist'] !== '0') && !in_array('album', $media)) {
            $artists = $discogs->search_artist($media['artist']);
            if (isset($artists['results']) && count($artists['results']) > 0) {
                foreach ($artists['results'] as $result) {
                    if ($result['title'] === $media['artist']) {
                        $artist = $discogs->get_artist((int)$result['id']);
                        if (isset($artist['images']) && count($artist['images']) > 0) {
                            $results['art'] = $artist['images'][0]['uri'];
                        }

                        if (!empty($artist['cover_image'])) {
                            $results['art'] = $artist['cover_image'];
                        }

                        // add in the data response as well
                        $results['data'] = $artist;
                        break;
                    }
                }
            }
        }

        if (!empty($media['albumartist']) && !empty($media['album'])) {
            /**
             * https://api.discogs.com/database/search?type=master&release_title=Ghosts&artist=Ladytron&per_page=10&key=key@secret=secret
             */
            $albums = $discogs->search_album($media['albumartist'], $media['album']);
            if (empty($albums['results'])) {
                $albums = $discogs->search_album($media['albumartist'], $media['album'], 'release');
            }

            // get the album that matches $artist - $album
            if (!empty($albums['results'])) {
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
                foreach ($albums['results'] as $albumSearch) {
                    if ($media['albumartist'] . ' - ' . $media['album'] === $albumSearch['title']) {
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

                if (isset($album['images']) && count($album['images']) > 0) {
                    $results['art'] = $album['images'][0]['uri'];
                }

                if (!empty($album['cover_image'])) {
                    $results['art'] = $album['cover_image'];
                }

                $genres = [];
                foreach ($albums['results'] as $release) {
                    if (!empty($release['genre'])) {
                        $genres = array_merge($genres, $release['genre']);
                    }
                }

                if (!empty($release['style'])) {
                    $genres = array_merge($genres, $release['style']);
                }

                if ($genres !== []) {
                    $results['genre'] = array_unique($genres);
                }

                // add in the data response as well
                $results['data'] = $album;
            }
        }

        print_r($results);
    } catch (Exception $exception) {
        print_r($exception->getMessage());
    }
}
