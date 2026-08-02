# AmpacheDiscogs

## 0.3.0

Requests are now paced to the documented Discogs rate limit and retried when Discogs pushes back.

**NOTE** Sustained lookups are slower than 0.2.0 by design. The old fixed 0.5s pause allowed about
120 requests a minute against a 60 a minute limit, so a long run earned a 429 and gave up.

### Added (0.3.0)

* A trailing `int $page = 1` on every endpoint Discogs paginates, so results past the first page
  are reachable at all — `get_artist_releases`, `get_label_releases`, `get_master_versions`,
  `get_collection_items_by_folder`, `get_user_lists`, `get_wantlist`, `search_album`,
  `search_artist`, `search_master` and `search_release`
  * previously only page 1 was ever requested, so `get_label_releases(1)` reached 50 of 592
    releases and `search_album()` reached 10 of 487 matches, with no way to ask for the rest
  * the `pagination` block Discogs returns tells you how many pages there are
  * **NOTE** `get_artist_releases()` repeats a few records across page boundaries and returns 49
    rows for a page size of 50. That is Discogs itself, on the raw endpoint, under every sort
    option — collect its pages keyed on `id`. The other paged endpoints do not do this
  * a page below 1 is clamped to 1 rather than sent, since Discogs rejects it
  * endpoints that return a single object are unchanged: `get_list`, `get_profile`,
    `get_collection_folders` and the individual artist, label, master and release lookups
* `DiscogsException`, thrown in place of `Exception`, carrying the HTTP status
  * `getStatusCode()` tells a missing record (404) apart from a rate limit (429) or an outage (5xx)
  * `isRateLimited()` for the 429 case
  * extends `Exception`, so an existing `catch (Exception $error)` still works
* Automatic retry for `429`, `500`, `502`, `503` and `504`, up to three attempts per call
  * honours the `Retry-After` header when Discogs sends one
  * `X-Discogs-Ratelimit-Remaining` widens the gap between requests as the window is used up

### Changed (0.3.0)

* Requests are spaced by elapsed time rather than a fixed pause after each one, so a slow response
  no longer adds its duration to the next wait
* Usernames are URL encoded, so one containing `/`, `?`, `#` or `&` reaches the intended endpoint
  instead of altering the request
* Tested against PHP 8.2, 8.3, 8.4 and 8.5, all of them gated
  * 8.5 is the version Ampache 8 runs, and nothing previously covered it
* Only `src/`, the docs and `composer.json` are published now
  * `phpstan.neon`, `rector.php`, `phpunit.xml`, `.php-cs-fixer.php` and `.github/` are marked
    `export-ignore`, so they no longer land in a consumer's `vendor/` directory

### Fixed (0.3.0)

* **The declared license was wrong.** `composer.json` has said `MIT` since the initial commit,
  contradicting the AGPL-3.0 text in `LICENSE.md` and the AGPL-3.0-or-later header on every source
  file. It now reads `AGPL-3.0-or-later`, which is what this library has always been under — it is
  an export of the AGPL-licensed Ampache Discogs plugin, so MIT was never a license it could offer.
  * this corrects the metadata to match the license that already applied; it does not relicense
    anything and does not change what you may do with the code
  * anyone who relied on the `MIT` declaration — an automated license audit, a dependency policy
    check — was given the wrong answer and should re-run it
  * the `-or-later` suffix matches the header wording; a bare `AGPL-3.0` is a deprecated SPDX
    identifier that `composer validate --strict` rejects
* `search_release()` returned masters mixed in with the releases
  * it sent `type=releases`, which Discogs does not recognise, and an unknown type is ignored rather
    than rejected, so the search came back unfiltered
* The scripts in `examples/` stopped before their first request, passing `null` where the
  constructor requires a string

## 0.2.0

Missing functions and examples have been added.

This is probably the most that the library will need to do but if there's more that you need open an issue.

### Added

* New Functions
  * [get_artist_releases](https://www.discogs.com/developers/#page:database,header:database-artist-releases-get)
  * [get_master_versions](https://www.discogs.com/developers/#page:database,header:database-master-release-versions-get)
  * [get_label](https://www.discogs.com/developers/#page:database,header:database-label-get)
  * [get_label_releases](https://www.discogs.com/developers/#page:database,header:database-all-label-releases-get)
  * [get_profile](https://www.discogs.com/developers/#page:user-identity,header:user-identity-profile-get)
  * [get_collection_folders](https://www.discogs.com/developers/#page:user-collection,header:user-collection-collection-get)
  * [get_collection_folder](https://www.discogs.com/developers/#page:user-collection,header:user-collection-collection-folder-get)
  * [get_collection_items_by_folder](https://www.discogs.com/developers/#page:user-collection,header:user-collection-collection-items-by-folder-get)
  * [get_user_lists](https://www.discogs.com/developers/index.html#page:user-lists,header:user-lists-user-lists)
  * [get_list](https://www.discogs.com/developers/index.html#page:user-lists,header:user-lists-list)
  * [get_wantlist](https://www.discogs.com/developers/#page:user-wantlist,header:user-wantlist-wantlist)

## 0.1.0

Export of data functions from the [Ampache Discogs plugin](https://github.com/ampache/ampache/blob/develop/src/Plugin/AmpacheDiscogs.php).

The code will be expanded to make it more usable for other projects but the focus here is on keeping it small and simple.

All data is JSON decoded with objects converted into associative arrays.
