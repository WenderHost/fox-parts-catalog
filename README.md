# Fox Electronics Parts Catalog #
**Contributors:** thewebist  
**Tags:** custom post type  
**Requires at least:** 4.4  
**Tested up to:** 5.3.2  
**Stable tag:** 1.0.0  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

Sets up custom post types for the Fox Parts Catalog

## Description ##

In addition to setting up the CPTs for the Fox Parts Catalog, this plugin includes a WP-CLI command for importing the catalog from a CSV:

`wp foxparts import <file> [--part_type=<part_type>] [--delete]`

* `<file>` (string) - file you wish to import
* `[--part_type=<part_type>]` (string) - The post type of the part we're importing (e.g. `crystal`).
* `[--delete]` (flag) - If set, delete all posts of `part_type` before importing.

## Installation ##

This section describes how to install the plugin and get it working.

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

## Changelog ##

### 1.0.0 ###
* DataTables.js search results
* Adding `(g)` to "Individual Part Weight" label

### 0.1.0 ###
* Initial release
