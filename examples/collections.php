<?php

use AmpacheDiscogs\Discogs;

require dirname(__DIR__) . '/vendor/autoload.php';

// your own Discogs api key and secret are required to use the Discogs API
$api_key  = 'yourApiKey';
$secret   = 'yourApiSecret';
$discogs  = new Discogs($api_key, $secret);
$username = 'discogsUsername';

try {
    $results = $discogs->get_collection_folders($username);

    print_r($results);
    $folder_id = (int) $results['folders'][0]['id'];

    $results = $discogs->get_collection_folder($username, $folder_id);

    print_r($results);

    $results = $discogs->get_collection_items_by_folder($username, $folder_id);

    print_r($results);
} catch (Exception $exception) {
    print_r($exception->getMessage());
}
