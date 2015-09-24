=== WooCommerce NetSuite Integrator ===
Contributors: codescribblr, jwads922
Tags: woocommerce, netsuite, bitbucket
Requires at least: 4.0
Tested up to: 4.3.1
Stable tag: 1.2.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin provides a custom integration between NetSuite and WooCommerce developed solely for Wolfpack Wholesale.

== Description ==

This plugin provides a custom integration between NetSuite and WooCommerce developed solely for Wolfpack Wholesale. It includes customer, product, and quote integration. It also uses BitBucket to autoupdate the plugin from a private repository.

## WooCommerce NetSuite Integrator ##
##### by Showcase Marketing (codescribblr) #####

### Customer Integration ###
Customers will be fully integrated. Wolfpack will be able to create customers in NetSuite and give them a username, email, and password for the website. This data will be automatically pulled into the webstore to create the customer in the webstore. We will use the default billing and shipping address from the customer record in NetSuite to autofill the billing and shipping fields on the webstore, but will allow the customer to override those addresses before sending a quote through to NetSuite. We will be setup to automatically pull this data every hour (it will be set in minutes, and the number of minutes will be configurable by Wolfpack). We will only pull the customers that have a modified flag set. This modified flag will be based on the custom fields mentioned above and any other fields that Wolfpack deems appropriate to trigger the modified flag.

### Product Integration ###
Products will be integrated. Any product that is created or edited on the webstore (with the exception of bulk-order products like pack juice) will compare the SKU in the webstore with the SKU in NetSuite to ensure that the product exists in NetSuite. This will help cut down on quotes having products that are not in NetSuite.

### Quote Integration ###
Quotes will be integrated. At this point, an order on the webstore will create a quote in NetSuite. There will be 3 custom fields that could potentially be filled with each quote. If a quote contains an item that has a configurable product (e.g. pack juice), there will be a "configurable item" flag checked. If a quote has a missing product (i.e. the product doesn't exist/match in NetSuite), a "missing item" flag will be checked. Along with this, a "missing item quantity" field will be filled to let you know how many items were missing on this quote so your team can be sure that they get everything on the order correct.

************

### WordPress Hooks ###
There are many hooks available for developers to connect into and customize the input/output of the plugin's functions. They are all listed below.

#### Customer Hooks ####

The hook `wni_before_get_customer` runs before the get_customer function does anything.

	add_action('wni_before_get_customer', 'my_wni_before_get_customer', 10, 2);
	function my_wni_before_get_customer($customer_id, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
	}

The hook `wni_after_get_customer` runs after the get_customer function does everything but before it returns.

	add_action('wni_after_get_customer', 'my_wni_after_get_customer', 10, 2);
	function my_wni_after_get_customer($get_customer_response, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
	}

The hook `wni_before_customer_search_by_email` runs before the customer_search_by_email function does anything.

	add_action('wni_before_customer_search_by_email', 'my_wni_before_customer_search_by_email', 10, 2);
	function my_wni_before_customer_search_by_email($email, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
	}

The hook `wni_after_customer_search_by_email` runs after the customer_search_by_email function does everything but before it returns.

	add_action('wni_after_customer_search_by_email', 'my_wni_after_customer_search_by_email', 10, 2);
	function my_wni_after_customer_search_by_email($customer_search_response, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
	}

The hook `wni_before_modified_flag_customer_search` runs before the modified_flag_customer_search function does anything.

	add_action('wni_before_modified_flag_customer_search', 'my_wni_before_modified_flag_customer_search', 10, 1);
	function my_wni_before_modified_flag_customer_search($SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
	}

The hook `wni_after_modified_flag_customer_search` runs after the modified_flag_customer_search function does everything but before it returns.

	add_action('wni_after_modified_flag_customer_search', 'my_wni_after_modified_flag_customer_search', 10, 2);
	function my_wni_after_modified_flag_customer_search($customer_search_response, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
	}

The hook `wni_before_update_modified_flag` runs before the update_modified_flag function does anything.

	add_action('wni_before_update_modified_flag', 'my_wni_before_update_modified_flag', 10, 3);
	function my_wni_before_update_modified_flag($customer_internal_id, (bool)$modified_flag_value, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
	}

The hook `wni_after_update_modified_flag` runs after the update_modified_flag function does everything but before it returns.

	add_action('wni_after_update_modified_flag', 'my_wni_after_update_modified_flag', 10, 3);
	function my_wni_after_update_modified_flag($customer_internal_id, $update_customer_response, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
	}

The hook `wni_before_upsert_customer` runs before the upsert_customer function does anything.

	add_action('wni_before_upsert_customer', 'my_wni_before_upsert_customer', 10, 2);
	function my_wni_before_upsert_customer($customer_args, $SCM_WC_Netsuite_Integrator_Customer_object) {
		/* 
		$customer_args contains an array with these values:
		array(
			'username',
			'email',
			'password',
			'first_name',
			'last_name',
			'billing_address_array',
			'shipping_address_array',
			'netsuite_id'
		);
		*/
		//do stuff here

	}

The hook `wni_after_upsert_customer` runs after the upsert_customer function does everything but before it returns.

	add_action('wni_after_upsert_customer', 'my_wni_after_upsert_customer', 10, 3);
	function my_wni_after_upsert_customer($customer_object, $customer_args, $SCM_WC_Netsuite_Integrator_Customer_object) {
		/* 
		$customer_args contains an array with these values:
		array(
			'username',
			'email',
			'password',
			'first_name',
			'last_name',
			'billing_address_array',
			'shipping_address_array',
			'netsuite_id'
		);
		*/
		//do stuff here
	}

The hook `wni_before_create_customer` runs before the customer is created in WordPress.

	add_action('wni_before_create_customer', 'my_wni_before_create_customer', 10, 2);
	function my_wni_before_create_customer($customer_args, $SCM_WC_Netsuite_Integrator_Customer_object) {
		/* 
		$customer_args contains an array with these values:
		array(
			'username',
			'email',
			'password',
			'first_name',
			'last_name',
			'billing_address_array',
			'shipping_address_array',
			'netsuite_id'
		);
		*/
		//do stuff here

	}

The hook `wni_after_create_customer` runs after the customer is created in WordPress.

	add_action('wni_after_create_customer', 'my_wni_after_create_customer', 10, 3);
	function my_wni_after_create_customer($user_id, $customer_args, $SCM_WC_Netsuite_Integrator_Customer_object) {
		/* 
		$customer_args contains an array with these values:
		array(
			'username',
			'email',
			'password',
			'first_name',
			'last_name',
			'billing_address_array',
			'shipping_address_array',
			'netsuite_id'
		);
		*/
		//do stuff here
	}

The hook `wni_before_update_customer` runs before the customer is created in WordPress.

	add_action('wni_before_update_customer', 'my_wni_before_update_customer', 10, 3);
	function my_wni_before_update_customer($user_object, $customer_args, $SCM_WC_Netsuite_Integrator_Customer_object) {
		/* 
		$customer_args contains an array with these values:
		array(
			'username',
			'email',
			'password',
			'first_name',
			'last_name',
			'billing_address_array',
			'shipping_address_array',
			'netsuite_id'
		);
		*/
		//do stuff here

	}

The hook `wni_after_update_customer` runs after the customer is created in WordPress.

	add_action('wni_after_update_customer', 'my_wni_after_update_customer', 10, 3);
	function my_wni_after_update_customer($user_object, $customer_args, $SCM_WC_Netsuite_Integrator_Customer_object) {
		/* 
		$customer_args contains an array with these values:
		array(
			'username',
			'email',
			'password',
			'first_name',
			'last_name',
			'billing_address_array',
			'shipping_address_array',
			'netsuite_id'
		);
		*/
		//do stuff here
	}


#### Product Hooks ####

The hook `wni_before_get_product_by_sku` runs before the get_product_by_sku function does anything.

	add_action('wni_before_get_product_by_sku', 'my_wni_before_get_product_by_sku', 10, 2);
	function my_wni_before_get_product_by_sku($product_id, $SCM_WC_Netsuite_Integrator_Product_object) {
		//do stuff here
	}

The hook `wni_after_get_product_by_sku` runs after the get_product_by_sku function does everything but before it returns.

	add_action('wni_after_get_product_by_sku', 'my_wni_after_get_product_by_sku', 10, 2);
	function my_wni_after_get_product_by_sku($product_search_response, $SCM_WC_Netsuite_Integrator_Product_object) {
		//do stuff here
	}

#### Quote Hooks ####

The hook `wni_before_create_netsuite_estimate` runs before the create_netsuite_estimate function does anything.

	add_action('wni_before_create_netsuite_estimate', 'my_wni_before_create_netsuite_estimate', 10, 2);
	function my_wni_before_create_netsuite_estimate($order_id, $SCM_WC_Netsuite_Integrator_Quote_object) {
		//do stuff here
	}

The hook `wni_after_create_netsuite_estimate` runs after the create_netsuite_estimate function does everything but before it returns.

	add_action('wni_after_create_netsuite_estimate', 'my_wni_after_create_netsuite_estimate', 10, 5);
	function my_wni_after_create_netsuite_estimate($order, $new_estimate, $estimate_request, $add_estimate_response, $SCM_WC_Netsuite_Integrator_Quote_object) {
		//do stuff here
	}

The hook `wni_create_netsuite_estimate_failed` runs if the call to NetSuite failed.

	add_action('wni_create_netsuite_estimate_failed', 'my_wni_create_netsuite_estimate_failed', 10, 2);
	function my_wni_create_netsuite_estimate_failed($add_estimate_response, $SCM_WC_Netsuite_Integrator_Quote_object) {
		//do stuff here
	}

The hook `wni_create_netsuite_estimate_succeeded` runs if the call to NetSuite succeeded.

	add_action('wni_create_netsuite_estimate_succeeded', 'my_wni_create_netsuite_estimate_succeeded', 10, 2);
	function my_wni_create_netsuite_estimate_succeeded($add_estimate_response, $SCM_WC_Netsuite_Integrator_Quote_object) {
		//do stuff here
	}


### WordPress Filters ###
There are many filters available for developers to connect into and filter the input/output of the plugin's functions. They are all listed below.

#### Customer Filters ####

The filter `wni_get_customer_request` filters the request before it's made.

	add_filter('wni_get_customer_request', 'my_wni_get_customer_request', 10, 2);
	function my_wni_get_customer_request($get_customer_request, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
		return $get_customer_request;
	}

The filter `wni_get_customer_response` filters the response before it's returned.

	add_filter('wni_get_customer_response', 'my_wni_get_customer_response', 10, 2);
	function my_wni_get_customer_response($get_customer_response, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
		return $get_customer_response;
	}

The filter `wni_customer_search_by_email_request` filters the request before it's made.

	add_filter('wni_customer_search_by_email_request', 'my_wni_customer_search_by_email_request', 10, 2);
	function my_wni_customer_search_by_email_request($customer_search_request, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
		return $customer_search_request;
	}

The filter `wni_customer_search_by_email_response` filters the response before it's returned.

	add_filter('wni_customer_search_by_email_response', 'my_wni_customer_search_by_email_response', 10, 3);
	function my_wni_customer_search_by_email_response($customer_internal_id, $customer_search_response, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
		return $customer_internal_id;
	}

The filter `wni_modified_flag_customer_search_request` filters the request before it's made.

	add_filter('wni_modified_flag_customer_search_request', 'my_wni_modified_flag_customer_search_request', 10, 2);
	function my_wni_modified_flag_customer_search_request($customer_search_request, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
		return $customer_search_request;
	}

The filter `wni_modified_flag_customer_search_response` filters the response before it's returned.

	add_filter('wni_modified_flag_customer_search_response', 'my_wni_modified_flag_customer_search_response', 10, 2);
	function my_wni_modified_flag_customer_search_response($customer_search_response, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
		return $customer_search_response;
	}

The filter `wni_update_modified_flag_request` filters the request before it's made.

	add_filter('wni_update_modified_flag_request', 'my_wni_update_modified_flag_request', 10, 2);
	function my_wni_update_modified_flag_request($customer_search_request, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
		return $customer_search_request;
	}

The filter `wni_update_modified_flag_response` filters the response before it's returned.

	add_filter('wni_update_modified_flag_response', 'my_wni_update_modified_flag_response', 10, 2);
	function my_wni_update_modified_flag_response($customer_search_response, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
		return $customer_search_response;
	}

The filter `wni_update_modified_flag_response_id` filters the response before it's returned.

	add_filter('wni_update_modified_flag_response_id', 'my_wni_update_modified_flag_response_id', 10, 3);
	function my_wni_update_modified_flag_response_id($customer_internal_id, $customer_search_response, $SCM_WC_Netsuite_Integrator_Customer_object) {
		//do stuff here
		return $customer_internal_id;
	}

#### Product Filters ####

The filter `wni_get_product_by_sku_search_request` filters the request before it's made.

	add_filter('wni_get_product_by_sku_search_request', 'my_wni_get_product_by_sku_search_request', 10, 2);
	function my_wni_get_product_by_sku_search_request($product_search_request, $SCM_WC_Netsuite_Integrator_Product_object) {
		//do stuff here
		return $product_search_request;
	}

The filter `wni_get_product_by_sku_search_response` filters the response before it's returned.

	add_filter('wni_get_product_by_sku_search_response', 'my_wni_get_product_by_sku_search_response', 10, 2);
	function my_wni_get_product_by_sku_search_response($product_search_response, $SCM_WC_Netsuite_Integrator_Product_object) {
		//do stuff here
		return $product_search_response;
	}

#### Quote Filters ####

The filter `wni_before_validation_product_sku` filters the sku before it's validated by NetSuite.

	add_filter('wni_before_validation_product_sku', 'my_wni_before_validation_product_sku', 10, 3);
	function my_wni_before_validation_product_sku($product_sku, $WC_Product, $product_from_order) {
		//do stuff here
		return $product_sku;
	}

The filter `wni_add_estimate_request` filters the request before it's made.

	add_filter('wni_add_estimate_request', 'my_wni_add_estimate_request', 10, 3);
	function my_wni_add_estimate_request($add_estimate_request, $order, $SCM_WC_Netsuite_Integrator_Quote_object) {
		//do stuff here
		return $add_estimate_request;
	}

The filter `wni_new_estimate_id` filters the id before it's returned.

	add_filter('wni_new_estimate_id', 'my_wni_new_estimate_id', 10, 4);
	function my_wni_new_estimate_id($new_estimate_id, $add_estimate_request, $add_estimate_response, $SCM_WC_Netsuite_Integrator_Product_object) {
		//do stuff here
		return $new_estimate_id;
	}


== Installation ==
1. Download the [latest tagged version zip from the BitBucket repo](https://bitbucket.org/showcase/woocommerce-netsuite-integrator/downloads#tag-downloads).
2. Upload Plugin via WordPress [Add New plugin page](/wp-admin/plugin-install.php?tab=upload).
3. Activate Plugin

== Frequently Asked Questions ==
= Will this work with any NetSuite account? =
While the plugin allows for any account to be connected, the functionality used requires specific custom fields and forms to function properly, and as such will not likely work with other accounts.

== Screenshots ==
1. The plugin settings menu item
2. Core NetSuite Options
3. Customer Options

== Changelog ==

= 1.2.7 = 

* c182f17 adds new custom status to orders sent to netsuite. modifies orders not sent to processing status.
* eaefe47 adds custom status and icon for sent to NetSuite status on woocommerce orders.
* 3d08aba version bump.

= 1.2.6 =

* e95266d adds order_id to error log for easier navigation of logs. adds filter to customize sku for bulk products.
* 326cea9 updates readme and version bump.

= 1.2.5 =

* 79234e2 updates sync function to use minutes instead of hours. updates version to 1.2.5
* 36eec12 fixes bug in error logging that was pulling incorrect support email option.
* f3e5218 updates customer upsert function to use get_user_by_email instead of username_exists.

= 1.2.4 = 

* 1c35c6b Merge branch 'master' of bitbucket.org:showcase/woocommerce-netsuite-integrator
* 65e0fa3 updates documentation formatting.
* f76785c README.md edited online with Bitbucket

= 1.2.3 =

* 189cb2c updates documentation to include hooks and filters in plugin description. updates multiple filters and hooks to be more consistent across functions. adds links to plugin page for viewing details and settings screens.


= 1.2.2 =

* db8b4e5 updated readme to current commit list.
* 13b2969 added wordpress actions and filters to each function to allow modification of functionality within theme files.
* af38d1b added functions to main integrator to modify shipping method html. added required field to netsuite settings for admin email notices on integration errors. integrated quote with payment_complete action in woocommerce. setup cron jobs on integration failure to retry sending to netsuite. if it fails more than twice, it will continue to try every hour, but it will also email the admin every hour letting them know there's an error.
* 2765f3e updated customer and quote classes to extend coutrycode function from parent service class. added custom user fields for netsuite. added boilerplate functions for adding and updating customers in netsuite. added default product to every estimate add request so we always have a product to send.
* 571638d updated product search by sku to include logout on each call. may need to remove. added quote funcitonality. It's working at this point but only for quotes that have all the needed info available. No error checking for missing info has been added yet.
* ed75adb updated readme description.


= 1.2.1 =
* 2ce8960 updated readme.

= 1.2.0 =
* 3ab430a updated version to 1.2.0
* 70825a1 added try...catch to each netsuite call to handle request errors more gracefully.
* 96e380e added quote class starter file.
* 84d5a7e added filter to allow for override of config in theme.
* 82c4fed updated logging to remove fungku from log name.
* 58ef64f completed build of product variation sku validation on product save.
* e1f1804 added get_product_by_sku function.
* fd6188c modified uninstall and install to work as activate and deactivate functions. added an uninstall function.

= 1.1.13 =
* 76e6be9 fixed bug in update checker.

= 1.1.12 =
* d421c37 fixed bug in folder naming.

= 1.1.11 =
* 3446df6 fix bug in proper_folder_name

= 1.1.10 =
* 61375be version bump.
* 03d67f6 modifications to allow for seamless updates...cont'd

= 1.1.9 =
* 163bb7b updated readme checker to look for README.md

= 1.1.8 =
* b505192 updated readme formatting. added new markdown parser. many updates to the way plugin_info is handled for view details screen.

= 1.1.7 =
* bdd78a8 version bump to force upgrade.
* 14fbaa1 removed install move functionality.
* 2cb2aec another attempt at running installer move function.
* 28190e6 updated installer functions to attempt to rename folder on install.
* e85c0e6 fix logging befor install.
* dc85607 updated post_install filter to test installation.

= 1.1.6 =
* ba9e0cd first stage of product integration.
* 14a2580 updated log function to use wp_uploads_dir() instead of hardcoded folder name. began development of product integration.
* 94e50c9 update readme formatting. update erusev/parsedown to newest version.
* ff0178e added readme.txt and modified readme settings in bitbucket updater to account for sections in readme.

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
= 1.1.13 =
This was the first version that had a completely working auto-updater. Users should update to a minimum of this version to continue to receive updates.