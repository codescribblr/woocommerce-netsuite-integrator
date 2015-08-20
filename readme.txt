=== WooCommerce NetSuite Integrator ===
Contributors: codescribblr, jwads922
Tags: woocommerce, netsuite, bitbucket
Requires at least: 4.0
Tested up to: 4.3
Stable tag: 1.1.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin provides a custom integration between NetSuite and WooCommerce developed solely for Wolfpack Wholesale.

== Description ==
This plugin provides a custom integration between NetSuite and WooCommerce developed solely for Wolfpack Wholesale. It includes customer, product, and quote integration. It also uses BitBucket to autoupdate the plugin from a private repository.

== Installation ==
1. Download the [latest tagged version zip from the BitBucket repo](https://bitbucket.org/showcase/woocommerce-netsuite-integrator/downloads#tag-downloads).
2. Upload Plugin via WordPress \"Add New\" plugin page.
3. Activate Plugin

== Frequently Asked Questions ==
= Will this work with any NetSuite account? =
While the plugin allows for any account to be connected, the functionality used requires specific custom fields and forms to function properly, and as such will not likely work with other accounts.

== Screenshots ==
1. The plugin settings menu item
2. Core NetSuite Options
3. Customer Options

== Changelog ==
= 1.1.5 =
* 50a3d0e updated update_plugins transient response with correct plugin name to allow updates to work again.

= 1.1.4 =
* b4b2a7d removed username and password for bitbucket account and netsuite account. created options for adding repo info in settings screen. cleaned up bitbucket updater slug and file info. version bump.
* e4f7c40 attempt to fix bug in slug for bitbucket api updater.

= 1.1.3 =
* 9b9fca0 version bump.
* 034003d fix bug in uninstall where recursively rmdir wasn\'t calling itself because it wasn\'t being called as a static method.

= 1.1.2 =
* 4a0e243 version bump. fixed bug with displaying plugin_info screen.

= 1.1.1 =
* cd88130 version bump.
* d098690 fix bug in PLUGIN_DIR constant.
* c79fe86 removed directory rename function. pointed plugin at correct directory.

= 1.1.0 =
* aba0337 fixed bug with plugin info not loading. added function to rename directory on initial plugin install from uploaded file.
* 2b68f80 fixed bug with directory removal on uninstall. array was being passed instead of directory name to removal function.

= 1.0.8 =
* 9f040d7 bump version. update repo & slug properties to correct values in bitbucket updater.

= 1.0.7 =
* 0645d8a modified transient settings. set bitbucket plugin updater to run on setup of main plugin __construct.

= 1.0.6 =
* 9374f42 version bump
* 7342f22 added bitbucket plugin updater to handle automatic plugin updates. added readme parser and markdown parser to help with the auto updates.

= 1.0.5 =
* b990d22 version bump for adding cron removal to uninstall hook.
* ce17876 added uninstall hook for removing cron on uninstall.

= 1.0.4 =
* 304d465 setup cron functions to begin running custom cron based on user config on netsuite settings page for customers sync interval.
* 1f066cd added custom options page inside woocommerce menu. added options for main netsuite config.
* 13d1478 cleaned up main integrator class. abstracted netsuite classes into separate classes for easy extensibility later.

= 1.0.3 =
* c62eb6e version bump.
* b13321f added acf-pro as required plugin in bundled plugins.

= 1.0.2 =
* 702122f update version number.
* b2607c8 modified plugin header to try to stop error message in plugin activation.

= 1.0.1 =
* 3da0355 version bump. and added bitbucket url and branch name.
* 1af96f7 added tgm plugin activation script to force woocommerce 2.4+ and github updater plugins. removed netsuite config file. added logic to deny direct access to netsuite clasess.
* ad7fa4a updated gitignore
* 23bba00 removed unneeded server files
* 84e594d modified .gitignnore
* 7fd59c4 added gitignore

= 1.0.0 =
* 84fd40f initial commit of plugin v1.

== Upgrade Notice ==
= 1.1.5 =
This was the first version that had a completely working auto-updater. Users should update to a minimum of this version to continue to receive updates.