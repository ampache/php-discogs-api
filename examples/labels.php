<?php

use AmpacheDiscogs\Discogs;

require dirname(__DIR__) . '/vendor/autoload.php';

// your own Discogs api key and secret are required to use the Discogs API
$api_key  = 'yourApiKey';
$secret   = 'yourApiSecret';
$discogs  = new Discogs($api_key, $secret);
$label_id = 1212668;

try {
    $results = $discogs->get_label($label_id);

    print_r($results);

    $results = $discogs->get_label_releases($label_id);

    print_r($results);
} catch (Exception $exception) {
    print_r($exception->getMessage());
}
