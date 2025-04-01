<?php

declare(strict_types=1);

/**
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright Ampache.org, 2001-2024
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace AmpacheDiscogs;

use Exception;
use WpOrg\Requests\Requests;

class Discogs
{
    public const VERSION = '0.1.0';

    private const DISCOGS_URL = 'https://api.discogs.com/';

    private readonly string $api_key;

    private readonly string $secret;

    private readonly string $userAgent;

    /**
     * Constructor
     * This function does nothing
     */
    public function __construct(
        string $discogs_api_key,
        string $discogs_api_secret
    ) {
        $this->api_key   = trim($discogs_api_key);
        $this->secret    = trim($discogs_api_secret);
        $this->userAgent = 'AmpacheDiscogs/' . self::VERSION;
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    private function _query_discogs(string $path_str, string $query_str = ''): array
    {
        $url = (!empty($query_str))
            ? self::DISCOGS_URL . $path_str . '?key=' . $this->api_key . '&secret=' . $this->secret . '&' . $query_str
            : self::DISCOGS_URL . $path_str . '?key=' . $this->api_key . '&secret=' . $this->secret;

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent
        ];

        $request = Requests::get($url, $headers);

        // sleep for 0.5s
        usleep(500000);

        $response = json_decode($request->body, true);

        return ($request->success && is_array($response))
            ? $response
            : throw new Exception("Bad response from Discogs\n" . $request->body . "\n");
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-search-get
     * @param array<string, string|int> $parameters
     * @return array<string, mixed>
     * @throws Exception
     */
    public function search(array $parameters): array
    {
        if (!isset($parameters['per_page'])) {
            $parameters['per_page'] = 10;
        }

        $query = http_build_query($parameters);

        return $this->_query_discogs('database/search', $query);
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public function search_album(string $artist, string $album, string $type = 'master'): array
    {
        $parameters = [
            'type' => $type,
            'release_title' => $album,
            'artist' => $artist,
            'per_page' => 10,
        ];

        return $this->search($parameters);
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public function search_release(string $artist, string $album): array
    {
        return $this->search_album($artist, $album, 'releases');
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public function search_master(string $artist, string $album): array
    {
        return $this->search_album($artist, $album);
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public function search_artist(string $artist): array
    {
        $parameters = [
            'type' => 'artist',
            'title' => $artist,
            'per_page' => 10,
        ];

        return $this->search($parameters);
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public function get_album(int $object_id, string $release_type = 'masters'): array
    {
        return $this->_query_discogs($release_type . '/' . $object_id);
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-master-release-get
     * @return array<string, mixed>
     * @throws Exception
     */
    public function get_release(int $object_id): array
    {
        return $this->get_album($object_id, 'releases');
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-release-get
     * @return array<string, mixed>
     * @throws Exception
     */
    public function get_master(int $object_id): array
    {
        return $this->get_album($object_id);
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-artist-get
     * @return array<string, mixed>
     * @throws Exception
     */
    public function get_artist(int $object_id): array
    {
        $query = sprintf("artists/%d", $object_id);

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-artist-releases-get
     * @return array<string, mixed>
     * @throws Exception
     */
    public function get_artist_releases(int $artist_id): array
    {
        $query = sprintf("artists/%d/releases", $artist_id);

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-master-release-versions-get
     * @return array<string, mixed>
     * @throws Exception
     */
    public function get_master_versions(int $master_id): array
    {
        $query = sprintf("masters/%d/versions", $master_id);

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-label-get
     * @return array<string, mixed>
     * @throws Exception
     */
    public function get_label(int $label_id): array
    {
        $query = sprintf("labels/%d", $label_id);

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-all-label-releases-get
     * @return array<string, mixed>
     * @throws Exception
     */
    public function get_label_releases(int $label_id): array
    {
        $query = sprintf("labels/%d/releases", $label_id);

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/#page:user-identity,header:user-identity-profile-get
     * @return array<string, mixed>
     * @throws Exception
     */
    public function get_profile(string $username): array
    {
        $query = sprintf("users/%s", $username);

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/#page:user-collection,header:user-collection-collection-get
     * @return array<string, mixed>
     * @throws Exception
     */
    public function get_collection_folders(string $username): array
    {
        $query = sprintf("users/%s/collection/folders", $username);

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/#page:user-collection,header:user-collection-collection-folder-get
     * @return array<string, mixed>
     * @throws Exception
     */
    public function get_collection_folder(string $username, int $folder_id): array
    {
        $query = sprintf("users/%s/collection/folders/%s", $username, $folder_id);

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/#page:user-collection,header:user-collection-collection-items-by-folder-get
     * @return array<string, mixed>
     * @throws Exception
     */
    public function get_collection_items_by_folder(string $username, int $folder_id): array
    {
        $query = sprintf("users/%s/collection/folders/%s/releases", $username, $folder_id);

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/index.html#page:user-lists,header:user-lists-user-lists
     * @return array<string, mixed>
     * @throws Exception
     */
    public function get_user_lists(string $username): array
    {
        $query = sprintf("users/%s/lists", $username);

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/index.html#page:user-lists,header:user-lists-list
     * @return array<string, mixed>
     * @throws Exception
     */
    public function get_list(int $list_id): array
    {
        $query = sprintf("lists/%d", $list_id);

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/#page:user-wantlist,header:user-wantlist-wantlist
     * @return array<string, mixed>
     * @throws Exception
     */
    public function get_wantlist(string $username): array
    {
        $query = sprintf("users/%s/wants", $username);

        return $this->_query_discogs($query);
    }
}
