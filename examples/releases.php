<?php

use AmpacheDiscogs\Discogs;

require dirname(__DIR__) . '/vendor/autoload.php';

// your own username and password are required to use the Discogs API
$username = null;
$password = null;
$discogs  = new Discogs($username, $password);

$artist    = 'Code 64';
$album     = 'The Shape';
$artistId  = 129150;
$masterId  = 2871442;
$releaseId = 25201483;

try {
    $results = $discogs->search_release($artist, $album);

    print_r($results);

    $results = $discogs->search_master($artist, $album);

    print_r($results);

    $results = $discogs->get_artist_releases($artistId);

    print_r($results);

    $results = $discogs->get_release($releaseId);

    print_r($results);

    $results = $discogs->get_master($masterId);

    print_r($results);

    $results = $discogs->get_master_versions($masterId);

    print_r($results);
} catch (Exception $exception) {
    print_r($exception->getMessage());
}
