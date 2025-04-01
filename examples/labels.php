<?php

use AmpacheDiscogs\Discogs;

require dirname(__DIR__) . '/vendor/autoload.php';

// your own username and password are required to use the Discogs API
$username = null;
$password = null;
$discogs  = new Discogs($username, $password);
$label_id = 1212668;

try {
    $results = $discogs->get_label($label_id);

    print_r($results);

    $results = $discogs->get_label_releases($label_id);

    print_r($results);
} catch (Exception $exception) {
    print_r($exception->getMessage());
}
