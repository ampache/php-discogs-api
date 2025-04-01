<?php

use AmpacheDiscogs\Discogs;

require dirname(__DIR__) . '/vendor/autoload.php';

// your own username and password are required to use the Discogs API
$username = null;
$password = null;
$discogs  = new Discogs($username, $password);
$username = 'discogsUsername';

try {
    $results = $discogs->get_collection_folders($username);

    print_r($results);
    $folder_id = (int)$results['folders'][0]['id'];

    $results = $discogs->get_collection_folder($username, $folder_id);

    print_r($results);

    $results = $discogs->get_collection_items_by_folder($username, $folder_id);

    print_r($results);
} catch (Exception $exception) {
    print_r($exception->getMessage());
}
