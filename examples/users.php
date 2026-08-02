<?php

use AmpacheDiscogs\Discogs;

require dirname(__DIR__) . '/vendor/autoload.php';

// your own Discogs api key and secret are required to use the Discogs API
$api_key  = 'yourApiKey';
$secret   = 'yourApiSecret';
$discogs  = new Discogs($api_key, $secret);
$username = 'discogsUsername';
$list_id  = 1596537;

try {
    $results = $discogs->get_profile($username);

    print_r($results);

    $results = $discogs->get_user_lists($username);

    print_r($results);

    $results = $discogs->get_wantlist($username);

    print_r($results);

    $results = $discogs->get_list($list_id);

    print_r($results);
} catch (Exception $exception) {
    print_r($exception->getMessage());
}
