<?php
/**
Plugin Name: WooCommerce NetSuite Integrator
Plugin URI: http://wordpress.org/plugins/woocommerce-netsuite-integrator/
Description: WooCommerce NetSuite Integrator.
Author: Showcase Marketing
Author URI: http://createlaunchlead.com
Version: 1.0.2
License: GPLv2 or later
Text Domain: woocommerce-netsuite-integrator
Domain Path: /languages
Bitbucket Plugin URI: https://bitbucket.org/showcase/woocoommerce-netsuite-integrator
Bitbucket Branch: master
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants for use in All plugin files
if ( ! defined( 'DS' ) ) {
	define( 'DS', DIRECTORY_SEPARATOR );
}
if ( ! defined( 'PLUGINS_DIR' ) ) {
	define( 'PLUGINS_DIR', dirname(dirname( __FILE__ )) );
}
if ( ! defined( 'PLUGIN_DIR' ) ) {
	define( 'PLUGIN_DIR', PLUGINS_DIR . DS . 'woocommerce-netsuite-integrator' );
}
if ( ! defined( 'INCLUDES_DIR' ) ) {
	define( 'INCLUDES_DIR', PLUGIN_DIR . DS . 'includes' );
}
if ( ! defined( 'LIB_DIR' ) ) {
	define( 'LIB_DIR', INCLUDES_DIR . DS . 'libs' );
}
if ( ! defined( 'BUNDLED_PLUGINS_DIR' ) ) {
	define( 'BUNDLED_PLUGINS_DIR', LIB_DIR . DS . 'bundled_plugins' );
}

if ( ! class_exists( 'SCM_WC_Netsuite_Integrator' ) ) :

/**
 * WooCommerce NetSuite Integrator main class.
 */
class SCM_WC_Netsuite_Integrator {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.2';

	public $config = array();
	public $service = null;

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin.
	 */
	private function __construct() {
		
		$this->includes();

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'tgmpa_register', array( $this, 'register_required_plugins' ) );

		// Checks if WooCommerce is installed.
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3', '>=' ) ) {
			$this->includes();
			$this->config = array(
				// Required
				"endpoint"  => "2015_1",
				"host"      => "https://webservices.na1.netsuite.com",
				"email"     => "stanton@wolfpackwholesale.com",
				"password"  => "Password300",
				"role"      => "3",
				"account"   => "3787604",
				// Optional
				"logging"   => true,
				"log_path"  => "/private/logs/netsuite"
			);
			// add_action( 'plugins_loaded', array( $this, 'get_customer' ), 99 );
			// add_action( 'plugins_loaded', array( $this, 'customer_search' ), 98 );
			// add_action( 'plugins_loaded', array( $this, 'get_and_organize_modified_customers' ), 97 );
			// add_action( 'plugins_loaded', array( $this, 'update_modified_flag' ), 96 );
			// add_action( 'plugins_loaded', array( $this, 'get_modified_customers_and_update_wordpress_customers' ), 95 );
		} else {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
		}
		
	}

	/*
	 * Usage self::log_action('error', 'Check out this sweet error!');
	 *
	*/
	public static function log_action($action, $message="", $logfile=false) {
	    $logfile = ($logfile) ? $logfile : $_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/wc-netsuite-logs/'.date("Y-m-d").'.log';
	    $new = file_exists($logfile) ? false : true;
	    if($handle = fopen($logfile, 'a')) { // append
	        $timestamp = strftime("%Y-%m-%d %H:%M:%S", time());
	        $content = "{$timestamp} | {$action}: {$message}\n";
	        fwrite($handle, $content);
	        fclose($handle);
	        if($new) { chmod($logfile, 0755); }
	    } else {
	        return false;
	    }
	}

	public function register_required_plugins() {
		/*
		 * Array of plugin arrays. Required keys are name and slug.
		 * If the source is NOT from the .org repo, then source is also required.
		 */
		$plugins = array(

			array(
				'name'      => 'WooCommerce',
				'slug'      => 'woocommerce',
				'required'  => true,
				'version'	=> '2.4',
			),
			array(
				'name'      	=> 'GitHub Updater',
				'slug'      	=> 'github-updater',
				'source'    	=> 'https://github.com/afragen/github-updater/archive/master.zip',
				'required'  	=> true, // If false, the plugin is only 'recommended' instead of required.
				'external_url' 	=> 'https://github.com/afragen/github-updater',
				'version'		=> '5.0',
			),

		);

		/*
		 * Array of configuration settings. Amend each line as needed.
		 */
		$config = array(
			'id'           => 'tgmpa-woocommerce-netsuite-integrator',	// Unique ID for hashing notices for multiple instances of TGMPA.
			'default_path' => '',                      					// Default absolute path to bundled plugins.
			'menu'         => 'tgmpa-install-plugins', 					// Menu slug.
			'parent_slug'  => 'plugins.php',            				// Parent menu slug.
			'capability'   => 'manage_options',   						// Capability needed to view plugin install page, should be a capability associated with the parent menu used.
			'has_notices'  => true,                    					// Show admin notices or not.
			'dismissable'  => true,                    					// If false, a user cannot dismiss the nag message.
			'dismiss_msg'  => '',                      					// If 'dismissable' is false, this message will be output at top of nag.
			'is_automatic' => false,                   					// Automatically activate plugins after installation or not.
			'message'      => '',                      					// Message to output right before the plugins table.

		);

		tgmpa( $plugins, $config );

	}

	private function connect_service() {
		$this->service = new NetSuiteService($this->config);
		return $this->service;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Get assets url.
	 *
	 * @return string
	 */
	public static function get_assets_url() {
		return plugins_url( 'assets/', __FILE__ );
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce-netsuite-integrator' );

		load_textdomain( 'woocommerce-netsuite-integrator', trailingslashit( WP_LANG_DIR ) . 'woocommerce-netsuite-integrator/woocommerce-netsuite-integrator-' . $locale . '.mo' );
		load_plugin_textdomain( 'woocommerce-netsuite-integrator', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Includes.
	 */
	private function includes() {
		require_once LIB_DIR . DS . 'tgmpa' . DS . 'class-tgm-plugin-activation.php';
		include_once LIB_DIR . DS . 'netsuite-phptoolkit' . DS . 'NSPHPClient.php';
		include_once LIB_DIR . DS . 'netsuite-phptoolkit' . DS . 'NetSuiteService.php';
	}

	/**
	 * Get the plugin options.
	 *
	 * @param  array $colors
	 *
	 * @return array
	 */
	public static function get_options( $colors ) {
		$colors = array_map( 'esc_attr', (array) $colors );

		// Defaults.
		if ( empty( $colors['primary'] ) ) {
			$colors['primary'] = '#a46497';
		}
		if ( empty( $colors['secondary'] ) ) {
			$colors['secondary'] = '#ebe9eb';
		}
		if ( empty( $colors['highlight'] ) ) {
			$colors['highlight'] = '#77a464';
		}
		if ( empty( $colors['content_bg'] ) ) {
			$colors['content_bg'] = '#ffffff';
		}
		if ( empty( $colors['subtext'] ) ) {
			$colors['subtext'] = '#777777';
		}

		return $colors;
	}

	/**
	 * Install method.
	 */
	public static function install() {
		// Install files and folders for uploading files and prevent hotlinking
		$upload_dir =  wp_upload_dir();

		$files = array(
			array(
				'base' 		=> $upload_dir['basedir'] . '/wc-netsuite-logs',
				'file' 		=> '.htaccess',
				'content' 	=> 'deny from all'
			),
			array(
				'base' 		=> $upload_dir['basedir'] . '/wc-netsuite-logs',
				'file' 		=> 'index.html',
				'content' 	=> ''
			),
		);

		foreach ( $files as $file ) {
			if ( wp_mkdir_p( $file['base'] ) && ! file_exists( trailingslashit( $file['base'] ) . $file['file'] ) ) {
				if ( $file_handle = @fopen( trailingslashit( $file['base'] ) . $file['file'], 'w' ) ) {
					fwrite( $file_handle, $file['content'] );
					fclose( $file_handle );
				}
			}
		}
	}

	/**
	 * WooCommerce fallback notice.
	 *
	 * @return string
	 */
	public function woocommerce_missing_notice() {
		echo '<div class="error"><p>' . sprintf( __( 'WooCommerce NetSuite Integrator depends on the last version of %s or later to work!', 'woocommerce-netsuite-integrator' ), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">' . __( 'WooCommerce 2.4+', 'woocommerce-netsuite-integrator' ) . '</a>' ) . '</p></div>';
	}

	public function get_customer($customer_id = false) {

		$service = $this->connect_service();

		$errors = array();

		$request = new GetRequest();
		$request->baseRef = new RecordRef();
		$request->baseRef->internalId = $customer_id;
		$request->baseRef->type = "customer";
		$getResponse = $service->get($request);

		if (!$getResponse->readResponse->status->isSuccess) {
		    $errors['customerSearch'][] = $getResponse->readResponse->status->statusDetail[0]->message;
		    self::log_action('error', print_r($errors, true));
		    $customer = false;
		} else {
		    $customer = $getResponse->readResponse->record;
		}

		return $customer;

	}

	/** 
	 *	Function not working for searching multiple fields simultaneously
	 * 	Resorting to using saved searches within NetSuite UI 
	 */
	public function customer_search(){
		
		$service = $this->connect_service();

		$errors = array();

		// SEARCH FOR CUSTOMER BY WEBSTORE CUSTOMER ID

		$service->setSearchPreferences(false, 20);

		// SEARCH BY CUSTOM FIELD (WEBSTORE CUSTOMER ID)
		$webStoreSearchField = new SearchBooleanCustomField();
		$webStoreSearchField->searchValue = 'true';
		// $webStoreSearchField->internalId = '150'; // scriptId => custentity_op_modified
		$webStoreSearchField->scriptId = 'custentity_op_modified';

		$customerStageSearchField = new SearchEnumMultiSelectField();
		setFields($customerStageSearchField, array('operator' => 'anyOf', 'searchValue' => '_customer'));
		$customerStageSearchField->operator = 'is';
		$customerStageSearchField->searchValue = 'Customer';

		$customerSearch = new CustomerSearchBasic();
		$customerSearch->customFieldList = new SearchCustomFieldList();
		$customerSearch->customFieldList->customField[] = $webStoreSearchField;
		// $customerSearch->stage = $customerStageSearchField;

		$customerSearchRequest = new SearchRequest();
		$customerSearchRequest->searchRecord = $customerSearch;

		$customerSearchResponse = $service->search($customerSearchRequest);

		// print_r($customerSearchResponse);

		if (!$customerSearchResponse->searchResult->status->isSuccess) {
		    $errors['customerSearch'][] = $customerSearchResponse->readResponse->status->statusDetail[0]->message;
		    $search_results = false;
		} elseif ($customerSearchResponse->searchResult->totalRecords === 0) {
			$errors['customerSearch'][] = 'No Modified Customers Found';
			$search_results = false;
		} else {
		    $search_results = $customerSearchResponse->searchResult->recordList->record;
		}

		return $search_results;
	}

	public function saved_modified_flag_customer_search() {

		$service = $this->connect_service();

		$errors = array();

		$customerSearch = new CustomerSearchAdvanced();
		$customerSearch->savedSearchId = '345'; 
		// $customerSearch->savedSearchScriptId => 'customsearch_web_modified_flag';

		$customerSearchRequest = new SearchRequest();
		$customerSearchRequest->searchRecord = $customerSearch;

		$customerSearchResponse = $service->search($customerSearchRequest);

		if (!$customerSearchResponse->searchResult->status->isSuccess) {
		    $errors['customerSearch'][] = $customerSearchResponse->readResponse->status->statusDetail[0]->message;
		    $search_results = false;
		} elseif ($customerSearchResponse->searchResult->totalRecords === 0) {
			$errors['customerSearch'][] = 'No Modified Customers Found';
			$search_results = false;
		} else {
		    $search_results = $customerSearchResponse->searchResult->searchRowList->searchRow;
		    $errors['customerSearch'][] = 'Successful';
		}

		self::log_action('error', print_r($errors, true));
		// print_r($search_results);
		return $search_results;

	}

	public function get_modified_customers($use_saved_search=true){
		
		if($use_saved_search){
			$modified_customers = $this->saved_modified_flag_customer_search();
		} else {
			$modified_customers = $this->customer_search();
		}

		return $modified_customers;

	}

	public function organize_modified_customers($modified_customers, $remove_duplicates = true){

		$customers = array();

		if(is_array($modified_customers) && !empty($modified_customers)){
			foreach($modified_customers as $key => $customer){
				$customers[$key]['internalId'] = $customer->basic->internalId[0]->searchValue->internalId;
				$customers[$key]['companyName'] = is_array($customer->basic->companyName) ? $customer->basic->companyName[0]->searchValue : '';
				$customers[$key]['isDefaultBilling'] = is_array($customer->basic->isDefaultBilling) ? $customer->basic->isDefaultBilling[0]->searchValue : '';
				$customers[$key]['billAddress']['billing_name'] = is_array($customer->basic->billAttention) ? $customer->basic->billAttention[0]->searchValue : '';
				$customers[$key]['billAddress']['billing_company'] = is_array($customer->basic->billAddressee) ? $customer->basic->billAddressee[0]->searchValue : '';
				$customers[$key]['billAddress']['billing_address_1'] = is_array($customer->basic->billAddress1) ? $customer->basic->billAddress1[0]->searchValue : '';
				$customers[$key]['billAddress']['billing_address_2'] = is_array($customer->basic->billAddress2) ? $customer->basic->billAddress2[0]->searchValue : '';
				$customers[$key]['billAddress']['billing_address_3'] = is_array($customer->basic->billAddress3) ? $customer->basic->billAddress3[0]->searchValue : '';
				$customers[$key]['billAddress']['billing_city'] = is_array($customer->basic->billCity) ? $customer->basic->billCity[0]->searchValue : '';
				$customers[$key]['billAddress']['billing_state'] = is_array($customer->basic->billState) ? $customer->basic->billState[0]->searchValue : '';
				$customers[$key]['billAddress']['billing_postcode'] = is_array($customer->basic->billZipCode) ? $customer->basic->billZipCode[0]->searchValue : '';
				$customers[$key]['billAddress']['billing_country_name'] = is_array($customer->basic->billCountry) ? $customer->basic->billCountry[0]->searchValue : '';
				$customers[$key]['billAddress']['billing_country'] = is_array($customer->basic->billCountryCode) ? $customer->basic->billCountryCode[0]->searchValue : '';
				$customers[$key]['billAddress']['billing_phone'] = is_array($customer->basic->billPhone) ? $customer->basic->billPhone[0]->searchValue : '';
				$customers[$key]['isDefaultShipping'] = is_array($customer->basic->isDefaultShipping) ? $customer->basic->isDefaultShipping[0]->searchValue : '';
				$customers[$key]['shipAddress']['shipping_name'] = is_array($customer->basic->shipAttention) ? $customer->basic->shipAttention[0]->searchValue : '';
				$customers[$key]['shipAddress']['shipping_company'] = is_array($customer->basic->shipAddressee) ? $customer->basic->shipAddressee[0]->searchValue : '';
				$customers[$key]['shipAddress']['shipping_address_1'] = is_array($customer->basic->shipAddress1) ? $customer->basic->shipAddress1[0]->searchValue : '';
				$customers[$key]['shipAddress']['shipping_address_2'] = is_array($customer->basic->shipAddress2) ? $customer->basic->shipAddress2[0]->searchValue : '';
				$customers[$key]['shipAddress']['shipping_address_3'] = is_array($customer->basic->shipAddress3) ? $customer->basic->shipAddress3[0]->searchValue : '';
				$customers[$key]['shipAddress']['shipping_city'] = is_array($customer->basic->shipCity) ? $customer->basic->shipCity[0]->searchValue : '';
				$customers[$key]['shipAddress']['shipping_state'] = is_array($customer->basic->shipState) ? $customer->basic->shipState[0]->searchValue : '';
				$customers[$key]['shipAddress']['shipping_postcode'] = is_array($customer->basic->shipZip) ? $customer->basic->shipZip[0]->searchValue : '';
				$customers[$key]['shipAddress']['shipping_country_name'] = is_array($customer->basic->shipCountry) ? $customer->basic->shipCountry[0]->searchValue : '';
				$customers[$key]['shipAddress']['shipping_country'] = is_array($customer->basic->shipCountryCode) ? $customer->basic->shipCountryCode[0]->searchValue : '';

				//Handle Custom Fields
				foreach($customer->basic->customFieldList->customField as $customField){
					if($customField->internalId=='148'){ // 'scriptID' => custentity_op_password
						$customers[$key]['password'] = $customField->searchValue;
					} elseif($customField->internalId=='149'){ // 'scriptID' => custentity_op_email
						$customers[$key]['email'] = $customField->searchValue;
					} elseif($customField->internalId=='154'){ // 'scriptID' => custentity_op_login
						$customers[$key]['username'] = $customField->searchValue;
					}
				}

				if($remove_duplicates){
					$customers[$key] = serialize($customers[$key]);
				}

			}

			if($remove_duplicates){
				$customers = array_unique($customers);
				foreach($customers as &$customer_obj){
					$customer_obj = unserialize($customer_obj);
				}
			}

		} else {
			$customers = false;
		}

		// print_r($customers);
		return $customers;

	}

	public function get_and_organize_modified_customers() {
		return $this->organize_modified_customers($this->get_modified_customers(true), true);
	}

	public function update_modified_flag($customer_internal_id, $modified_flag_value = false) {

		$service = $this->connect_service();

		$errors = array();

		// UPDATE CUSTOMER
		$customer = new Customer();

		$modified_flag = new BooleanCustomFieldRef();
		$modified_flag->value = $modified_flag_value;
		$modified_flag->scriptId = 'custentity_op_modified'; // scriptId => custentity_op_modified

		$customer->customFieldList = new CustomFieldList();
		$customer->customFieldList->customField = array($modified_flag);

		// ADD CUSTOM FORM REFERENCE
		$customer->customForm = new RecordRef();
		$customer->customForm->internalId = '14'; // name => Vape Shops

		$customer->internalId = $customer_internal_id;

		$update_customer_request = new UpdateRequest();
		$update_customer_request->record = $customer;

		$service->setPreferences(false, false, false, true);

		$update_customer_response = $service->update($update_customer_request);

		// print_r($update_customer_response);

		if (!$update_customer_response->writeResponse->status->isSuccess) {
		    $errors['customerUpdate'][] = $update_customer_response->writeResponse->status->statusDetail[0]->message;
		    self::log_action('error', print_r($errors));
		    // mail('jon@createlaunchlead.com', 'Error Updating Customer ID', "oldId=".$oldId."\nnewId=".$newId);
	    	return $errors;
		}

		self::log_action('success', 'Customer '.$customer_internal_id.' Modified Flag Update Successful');
		// mail('jon@createlaunchlead.com', 'Customer ID Update Successful', "oldId=".$oldId."\nnewId=".$newId);
		return $customer_internal_id;

	}

	public function upsert_customer($username, $email, $password='', $first_name = '', $last_name = '', $billing = array(), $shipping = array()) {
		
		$nickname = !empty($first_name) ? $first_name : $username;
		$user_id = username_exists( $username );

		// If user doesn't exist, add them as a customer to wordpress
		if( $user_id === NULL ) {

			// Generate the password and create the user
			if(empty($password)){
				$password = wp_generate_password( 12, false );
			}
			$user_id = wp_create_user( $username, $password, $email );

			if(!is_wp_error($user_id)) {

				// Set the nickname
				$updated = wp_update_user(
					array(
						'ID'          =>    $user_id,
						'nickname'    =>    $nickname
					)
				);

				$user = new WP_User( $user_id );

				update_user_meta($user_id, 'nickname', ($user->nickname) ? $user->nickname : $billing['billing_name']);
				update_user_meta($user_id, 'last_synced_with_netsuite_date', time());

				// Set the role
				$role = 'contributor';
				global $wp_roles;

				if ( ! isset( $wp_roles ) )
				    $wp_roles = new WP_Roles();

				if(in_array('Customer', $wp_roles->get_names())){
					$role = 'customer';
				}

				$user->set_role( $role );

				// Email the user
				// wp_mail( $email_address, 'Welcome!', 'Your Password: ' . $password );
				self::log_action('success', 'Customer '.$user_id.'('.$nickname.') Created');
				return $updated;
			} else {
				self::log_action('error', print_r($user_id));
				return $user_id;
			}

		// If user exists, update them with the new info provided
		} else {

			$user = new WP_User($user_id);

			// Set the nickname
			$updated = wp_update_user(
				array(
					'ID'				=>	$user_id,
					'user_pass'			=>	$password,
					'nickname'			=>	($user->nickname) ? $user->nickname : $nickname,
				)
			);

			$billing_name = explode(' ', $billing['billing_name']);
			$shipping_name = explode(' ', $shipping['shipping_name']);
			//Maybe Update User Fields
			update_user_meta($user_id, 'first_name', ($user->first_name) ? $user->first_name : $billing_name[0]);
			update_user_meta($user_id, 'last_name', ($user->last_name) ? $user->last_name : array_pop($billing_name));
			update_user_meta($user_id, 'nickname', ($user->nickname) ? $user->nickname : $billing['billing_name']);

			//Maybe Update Billing Fields
			update_user_meta($user_id, 'billing_company', ($user->billing_company) ? $user->billing_company : $billing['billing_company']);
			update_user_meta($user_id, 'billing_first_name', ($user->billing_first_name) ? $user->billing_first_name : $billing_name[0]);
			update_user_meta($user_id, 'billing_last_name', ($user->billing_last_name) ? $user->billing_last_name : array_pop($billing_name));
			update_user_meta($user_id, 'billing_address_1', ($user->billing_address_1) ? $user->billing_address_1 : $billing['billing_address_1']);
			update_user_meta($user_id, 'billing_address_2', ($user->billing_address_2) ? $user->billing_address_2 : trim($billing['billing_address_2'].' '.$billing['billing_address_3']));
			update_user_meta($user_id, 'billing_city', ($user->billing_city) ? $user->billing_city : $billing['billing_city']);
			update_user_meta($user_id, 'billing_state', ($user->billing_state) ? $user->billing_state : $billing['billing_state']);
			update_user_meta($user_id, 'billing_postcode', ($user->billing_postcode) ? $user->billing_postcode : $billing['billing_postcode']);
			update_user_meta($user_id, 'billing_state', ($user->billing_state) ? $user->billing_state : $billing['billing_state']);
			update_user_meta($user_id, 'billing_country', ($user->billing_country) ? $user->billing_country : $billing['billing_country']);
			update_user_meta($user_id, 'billing_phone', ($user->billing_phone) ? $user->billing_phone : $billing['billing_phone']);
			update_user_meta($user_id, 'billing_email', ($user->billing_email) ? $user->billing_email : $email);

			//Maybe Update Shipping Fields
			update_user_meta($user_id, 'shipping_company', ($user->shipping_company) ? $user->shipping_company : $shipping['shipping_company']);
			update_user_meta($user_id, 'shipping_first_name', ($user->shipping_first_name) ? $user->shipping_first_name : $shipping_name[0]);
			update_user_meta($user_id, 'shipping_last_name', ($user->shipping_last_name) ? $user->shipping_last_name : array_pop($shipping_name));
			update_user_meta($user_id, 'shipping_address_1', ($user->shipping_address_1) ? $user->shipping_address_1 : $shipping['shipping_address_1']);
			update_user_meta($user_id, 'shipping_address_2', ($user->shipping_address_2) ? $user->shipping_address_2 : trim($shipping['shipping_address_2'].' '.$shipping['shipping_address_3']));
			update_user_meta($user_id, 'shipping_city', ($user->shipping_city) ? $user->shipping_city : $shipping['shipping_city']);
			update_user_meta($user_id, 'shipping_state', ($user->shipping_state) ? $user->shipping_state : $shipping['shipping_state']);
			update_user_meta($user_id, 'shipping_postcode', ($user->shipping_postcode) ? $user->shipping_postcode : $shipping['shipping_postcode']);
			update_user_meta($user_id, 'shipping_state', ($user->shipping_state) ? $user->shipping_state : $shipping['shipping_state']);
			update_user_meta($user_id, 'shipping_country', ($user->shipping_country) ? $user->shipping_country : $shipping['shipping_country']);

			update_user_meta($user_id, 'last_synced_with_netsuite_date', time());

			self::log_action('success', 'Customer '.$user_id.'('.$nickname.') Updated Successfully');

			return $updated;

		}
	}

	public function get_modified_customers_and_update_wordpress_customers(){
		
		$customers = $this->get_and_organize_modified_customers();
		if(!empty($customers)){
			foreach($customers as $customer){
				if($this->upsert_customer($customer['username'], $customer['email'], $customer['password'], $customer['companyName'], '', $customer['billAddress'], $customer['shipAddress'])){
					$this->update_modified_flag($customer['internalId']);
				}	
			}
		}

	}

}

// Plugin install.
register_activation_hook( __FILE__, array( 'SCM_WC_Netsuite_Integrator', 'install' ) );

add_action( 'plugins_loaded', array( 'SCM_WC_Netsuite_Integrator', 'get_instance' ) );

endif;