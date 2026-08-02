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

use WpOrg\Requests\Requests;
use WpOrg\Requests\Response;

class Discogs
{
    public const VERSION = '0.3.0';

    private const DISCOGS_URL = 'https://api.discogs.com/';

    private const LOW_BUDGET = 5;

    private const MAX_ATTEMPTS = 3;

    // Discogs allows 60 authenticated requests a minute, so a request every second is the sustainable rate
    private const REQUEST_INTERVAL = 1000000;

    private const RETRY_STATUSES = [429, 500, 502, 503, 504];

    // used instead once Discogs reports the remaining budget for this window is nearly spent
    private const SLOW_INTERVAL = 2000000;

    private readonly string $api_key;
    private float $lastRequest = 0.0;
    private int $remaining     = -1;
    private readonly string $secret;
    private readonly string $userAgent;

    public function __construct(
        string $discogs_api_key,
        string $discogs_api_secret,
    ) {
        $this->api_key   = trim($discogs_api_key);
        $this->secret    = trim($discogs_api_secret);
        $this->userAgent = 'AmpacheDiscogs/' . self::VERSION;
    }

    /**
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function get_album(int $object_id, string $release_type = 'masters'): array
    {
        return $this->_query_discogs(rawurlencode($release_type) . '/' . $object_id);
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-artist-get
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function get_artist(int $object_id): array
    {
        $query = sprintf("artists/%d", $object_id);

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-artist-releases-get
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function get_artist_releases(int $artist_id, int $page = 1): array
    {
        $query = sprintf("artists/%d/releases", $artist_id);

        return $this->_query_discogs($query, $this->_page_query($page));
    }

    /**
     * https://www.discogs.com/developers/#page:user-collection,header:user-collection-collection-folder-get
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function get_collection_folder(string $username, int $folder_id): array
    {
        $query = sprintf("users/%s/collection/folders/%d", rawurlencode($username), $folder_id);

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/#page:user-collection,header:user-collection-collection-get
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function get_collection_folders(string $username): array
    {
        $query = sprintf("users/%s/collection/folders", rawurlencode($username));

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/#page:user-collection,header:user-collection-collection-items-by-folder-get
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function get_collection_items_by_folder(string $username, int $folder_id, int $page = 1): array
    {
        $query = sprintf("users/%s/collection/folders/%d/releases", rawurlencode($username), $folder_id);

        return $this->_query_discogs($query, $this->_page_query($page));
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-label-get
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function get_label(int $label_id): array
    {
        $query = sprintf("labels/%d", $label_id);

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-all-label-releases-get
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function get_label_releases(int $label_id, int $page = 1): array
    {
        $query = sprintf("labels/%d/releases", $label_id);

        return $this->_query_discogs($query, $this->_page_query($page));
    }

    /**
     * https://www.discogs.com/developers/index.html#page:user-lists,header:user-lists-list
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function get_list(int $list_id): array
    {
        $query = sprintf("lists/%d", $list_id);

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-release-get
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function get_master(int $object_id): array
    {
        return $this->get_album($object_id);
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-master-release-versions-get
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function get_master_versions(int $master_id, int $page = 1): array
    {
        $query = sprintf("masters/%d/versions", $master_id);

        return $this->_query_discogs($query, $this->_page_query($page));
    }

    /**
     * https://www.discogs.com/developers/#page:user-identity,header:user-identity-profile-get
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function get_profile(string $username): array
    {
        $query = sprintf("users/%s", rawurlencode($username));

        return $this->_query_discogs($query);
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-master-release-get
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function get_release(int $object_id): array
    {
        return $this->get_album($object_id, 'releases');
    }

    /**
     * https://www.discogs.com/developers/index.html#page:user-lists,header:user-lists-user-lists
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function get_user_lists(string $username, int $page = 1): array
    {
        $query = sprintf("users/%s/lists", rawurlencode($username));

        return $this->_query_discogs($query, $this->_page_query($page));
    }

    /**
     * https://www.discogs.com/developers/#page:user-wantlist,header:user-wantlist-wantlist
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function get_wantlist(string $username, int $page = 1): array
    {
        $query = sprintf("users/%s/wants", rawurlencode($username));

        return $this->_query_discogs($query, $this->_page_query($page));
    }

    /**
     * https://www.discogs.com/developers/#page:database,header:database-search-get
     * @param array<string, string|int> $parameters
     * @return array<string, mixed>
     * @throws DiscogsException
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
     * @throws DiscogsException
     */
    public function search_album(string $artist, string $album, string $type = 'master', int $page = 1): array
    {
        $parameters = [
            'type' => $type,
            'release_title' => $album,
            'artist' => $artist,
            'per_page' => 10,
            'page' => max(1, $page),
        ];

        return $this->search($parameters);
    }

    /**
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function search_artist(string $artist, int $page = 1): array
    {
        $parameters = [
            'type' => 'artist',
            'title' => $artist,
            'per_page' => 10,
            'page' => max(1, $page),
        ];

        return $this->search($parameters);
    }

    /**
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function search_master(string $artist, string $album, int $page = 1): array
    {
        return $this->search_album($artist, $album, 'master', $page);
    }

    /**
     * @return array<string, mixed>
     * @throws DiscogsException
     */
    public function search_release(string $artist, string $album, int $page = 1): array
    {
        return $this->search_album($artist, $album, 'release', $page);
    }

    /**
     * Perform the request. This is the only method that touches the network, so tests override it.
     * @param array<string, string> $headers
     */
    protected function _http_get(string $url, array $headers): Response
    {
        return Requests::get($url, $headers);
    }

    /**
     * Wait out a throttle or a backoff. Overridden in tests so the suite never actually sleeps.
     */
    protected function _sleep(int $microseconds): void
    {
        if ($microseconds > 0) {
            usleep($microseconds);
        }
    }

    /**
     * The query string for a paginated endpoint. Discogs numbers pages from 1, so anything lower is clamped.
     */
    private function _page_query(int $page): string
    {
        return http_build_query(['page' => max(1, $page)]);
    }

    /**
     * @return array<string, mixed>
     * @throws DiscogsException
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

        for ($attempt = 1;; $attempt++) {
            $this->_throttle();

            $request = $this->_http_get($url, $headers);
            $status  = is_int($request->status_code) ? $request->status_code : 0;
            $this->_readRateLimit($request);

            if ($request->success) {
                $response = json_decode($request->body, true);

                return is_array($response)
                    ? $response
                    : throw new DiscogsException("Bad response from Discogs\n" . $request->body . "\n", $status);
            }

            // a rate limit or a gateway hiccup is worth another go, anything else is the caller's problem
            if ($attempt >= self::MAX_ATTEMPTS || !in_array($status, self::RETRY_STATUSES, true)) {
                throw new DiscogsException("Bad response from Discogs\n" . $request->body . "\n", $status);
            }

            $this->_sleep($this->_retryDelay($request, $attempt));
        }
    }

    /**
     * Remember what Discogs last reported about the remaining budget in the current window.
     */
    private function _readRateLimit(Response $request): void
    {
        $remaining = $request->headers['x-discogs-ratelimit-remaining'] ?? null;

        $this->remaining = is_numeric($remaining) ? (int) $remaining : -1;
    }

    /**
     * How long to wait before retrying, honouring Retry-After when Discogs sends one.
     */
    private function _retryDelay(Response $request, int $attempt): int
    {
        $retryAfter = $request->headers['retry-after'] ?? null;
        if (is_numeric($retryAfter) && (int) $retryAfter > 0) {
            return (int) $retryAfter * 1000000;
        }

        return self::SLOW_INTERVAL * $attempt;
    }

    /**
     * Keep enough distance from the previous request to stay inside the Discogs rate limit.
     * Discogs allows 60 requests a minute, so the gap widens once the reported budget runs low.
     */
    private function _throttle(): void
    {
        $interval = ($this->remaining > -1 && $this->remaining <= self::LOW_BUDGET)
            ? self::SLOW_INTERVAL
            : self::REQUEST_INTERVAL;

        if ($this->lastRequest > 0.0) {
            $elapsed = (int) ((microtime(true) - $this->lastRequest) * 1000000);
            $this->_sleep($interval - $elapsed);
        }

        $this->lastRequest = microtime(true);
    }
}
