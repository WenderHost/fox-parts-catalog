=== Fox Electronics Parts Catalog ===
Contributors: thewebist
Tags: custom post type
Requires at least: 4.4
Tested up to: 5.5.1
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sets up custom post types for the Fox Parts Catalog

== Description ==

In addition to setting up the CPTs for the Fox Parts Catalog, this plugin includes a WP-CLI command for importing the catalog from a CSV:

`wp foxparts import <file> [--part_type=<part_type>] [--delete]`

* `<file>` (string) - file you wish to import
* `[--part_type=<part_type>]` (string) - The post type of the part we're importing (e.g. `crystal`).
* `[--delete]` (flag) - If set, delete all posts of `part_type` before importing.

== Changelog ==

= 1.4.0 =
* Adding `[reps /]` shortcode.

= 1.3.0 =
* Adding `[productchangenotices /]` shortcode.

= 1.2.2 =
* Updating `product-list.html` "line" variables to work with new Sheety.co API.

= 1.2.1 =
* Updating `product-list.js` to work with new Sheety.co API.

= 1.2.0 =
* Adding part `load` value to Crystals part display.

= 1.1.3 =
* Displaying extended results for Crystal searches via `datatables-init.js`.

= 1.1.2 =
* Updating FoxSelect to work with `/fox-parts-catalog/` in the plugin path.

= 1.1.1 =
* Renaming root file from `fox-electronics-parts-catalog.php` to `fox-parts-catalog.php`.

= 1.1.0 =
* Accounting for K121 and K124 parts in the API.
* Updating `wp foxparts import` to 1) delete a part series, and 2) import that part series based on the assumption the complete series is in the CSV.

= 1.0.0 =
* DataTables.js search results
* Adding `(g)` to "Individual Part Weight" label

= 0.1.0 =
* Initial release
