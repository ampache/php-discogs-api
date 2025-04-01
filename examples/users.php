<?php

use AmpacheDiscogs\Discogs;

require dirname(__DIR__) . '/vendor/autoload.php';

// your own username and password are required to use the Discogs API
$username = null;
$password = null;
$discogs  = new Discogs($username, $password);
$username = 'discogsUsername';

try {
    $results = $discogs->get_profile($username);

    print_r($results);

    $results = $discogs->get_user_lists($username);

    print_r($results);

    $results = $discogs->get_wantlist($username);

    print_r($results);
} catch (Exception $exception) {
    print_r($exception->getMessage());
}
