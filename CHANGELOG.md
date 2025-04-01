# AmpacheDiscogs

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
