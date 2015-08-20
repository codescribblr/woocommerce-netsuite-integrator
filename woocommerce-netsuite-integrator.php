<?php
/*
Plugin Name: WooCommerce NetSuite Integrator
Plugin URI: https://bitbucket.org/showcase/woocommerce-netsuite-integrator
Description: WooCommerce NetSuite Integrator.
Author: Showcase Marketing
Author URI: http://createlaunchlead.com
Version: 1.1.6
License: GPLv2 or later
Text Domain: woocommerce-netsuite-integrator
Domain Path: /languages
Bitbucket Plugin URI: https://bitbucket.org/showcase/woocoommerce-netsuite-integrator
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
	define( 'PLUGIN_DIR', dirname( __FILE__ ) );
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
if ( ! defined( 'PLUGIN_SLUG' ) ) {
	define( 'PLUGIN_SLUG', 'woocommerce-netsuite-integrator' );
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
	const VERSION = '1.1.6';

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
		
		$this->netsuite_config = array(
			"host"      => get_option('options_wni_host_endpoint'),
			"email"     => get_option('options_wni_email'),
			"password"  => get_option('options_wni_password'),
			"role"      => "3", // Must be an admin to have rights
			"account"   => get_option('options_wni_account_number'),
		);

		// Checks if NetSuite Configuration Options are set.
		if ( !$this->netsuite_config['host'] || !$this->netsuite_config['email'] || !$this->netsuite_config['password'] || !$this->netsuite_config['role'] || !$this->netsuite_config['account'] ) {			
			add_action( 'admin_notices', array( $this, 'netsuite_configuration_missing_notice' ) );
			$this->requires();
			$this->setup_options();
			return false;
		} else {
			$this->requires();
			$this->includes();
		}
		$this->setup_options();
		$upload_dir =  wp_upload_dir();

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'tgmpa_register', array( $this, 'register_required_plugins' ) );
		add_action( 'init', array( $this, 'setup_cron' ) );
		add_filter( 'cron_schedules', array( $this, 'woocommerce_netsuite_custom_schedule' ) );
		add_filter( 'upgrader_post_install', array( $this, 'post_upgrade' ), 10, 3 );

		if ( is_admin() ) {
			if( class_exists('BitBucket_Plugin_Updater') ) {
				new BitBucket_Plugin_Updater( __FILE__, get_option('options_wni_bitbucket_repo_owner'), get_option('options_wni_bitbucket_repo_name'), array('username' => get_option('options_wni_bitbucket_username'), 'password' => get_option('options_wni_bitbucket_password')), get_option('options_wni_bitbucket_private_repository') );
			}
		}

	}

	/*
	 * Usage self::log_action('error', 'Check out this sweet error!');
	 *
	*/
	public static function log_action($action, $message="", $logfile=false) {
	    $upload_dir = wp_upload_dir();
	    $logfile = ($logfile) ? $logfile : $upload_dir['basedir'] . '/wc-netsuite-logs/'.date("Y-m-d").'.log';
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

	public static function recursively_rmdir($dir) { 
		if (is_dir($dir)) { 
			$objects = scandir($dir); 
			foreach ($objects as $object) { 
				if ($object != "." && $object != "..") { 
					if (filetype($dir."/".$object) == "dir") self::recursively_rmdir($dir."/".$object); else unlink($dir."/".$object); 
				} 
			} 
			reset($objects); 
			rmdir($dir); 
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
			// array(
			// 	'name'      	=> 'GitHub Updater',
			// 	'slug'      	=> 'github-updater',
			// 	'source'    	=> 'https://github.com/afragen/github-updater/archive/master.zip',
			// 	'required'  	=> true, // If false, the plugin is only 'recommended' instead of required.
			// 	'external_url' 	=> 'https://github.com/afragen/github-updater',
			// 	'version'		=> '5.0',
			// ),
			array(
				'name'               => 'Advanced Custom Fields Pro', // The plugin name.
				'slug'               => 'advanced-custom-fields-pro', // The plugin slug (typically the folder name).
				'source'             => BUNDLED_PLUGINS_DIR . DS . 'advanced-custom-fields.zip', // The plugin source.
				'required'           => true, // If false, the plugin is only 'recommended' instead of required.
				'version'            => '5.2.9', // E.g. 1.0.0. If set, the active plugin must be this version or higher. If the plugin version is higher than the plugin version installed, the user will be notified to update the plugin.
				'force_activation'   => true, // If true, plugin is activated upon theme activation and cannot be deactivated until theme switch.
				'force_deactivation' => false, // If true, plugin is deactivated upon theme switch, useful for theme-specific plugins.
				'external_url'       => 'http://www.advancedcustomfields.com/pro/', // If set, overrides default API URL and points to an external URL.
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
		include_once INCLUDES_DIR . DS . 'class-scm-woocommerce-netsuite-integrator-service.php';
		include_once INCLUDES_DIR . DS . 'class-scm-woocommerce-netsuite-integrator-customer.php';
		include_once INCLUDES_DIR . DS . 'class-scm-woocommerce-netsuite-integrator-product.php';
		include_once INCLUDES_DIR . DS . 'class-bitbucket-plugin-updater.php';
		include_once LIB_DIR . DS . 'automattic-readme' . DS . 'class-automattic-readme.php';
		include_once LIB_DIR . DS . 'automattic-readme' . DS . 'class-parsedown.php';
	}

	/**
	 * Requires.
	 */
	private function requires() {
		require_once LIB_DIR . DS . 'tgmpa' . DS . 'class-tgm-plugin-activation.php';
	}

	/**
	 * Setup the plugin options.
	 *
	 */
	public function setup_options() {

		if( function_exists('acf_add_options_sub_page') ) {
			acf_add_options_sub_page(array(
				'page_title' 	=> 'WooCommerce NetSuite Settings',
				'menu_title'	=> 'NetSuite Settings',
				'parent_slug'	=> 'woocommerce',
				'capability'	=> 'manage_options',
			));
		}
		
		if( function_exists('acf_add_local_field_group') ) {

			acf_add_local_field_group(array (
				'key' => 'group_55cffae61b180',
				'title' => 'NetSuite Integrator Configuration Options',
				'fields' => array (
					array (
						'key' => 'field_55cffb04b831d',
						'label' => 'General Options',
						'name' => '',
						'type' => 'tab',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'placement' => 'left',
						'endpoint' => 0,
					),
					array (
						'key' => 'field_55cffb48b8321',
						'label' => 'NetSuite Endpoint Host',
						'name' => 'wni_host_endpoint',
						'type' => 'text',
						'instructions' => '',
						'required' => 1,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => 'https://webservices.na1.netsuite.com',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'maxlength' => '',
						'readonly' => 0,
						'disabled' => 0,
					),
					array (
						'key' => 'field_55cffb8cb8322',
						'label' => 'NetSuite Account Email',
						'name' => 'wni_email',
						'type' => 'email',
						'instructions' => '',
						'required' => 1,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
					),
					array (
						'key' => 'field_55cffbcdb8323',
						'label' => 'NetSuite Account Password',
						'name' => 'wni_password',
						'type' => 'password',
						'instructions' => '',
						'required' => 1,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'readonly' => 0,
						'disabled' => 0,
					),
					array (
						'key' => 'field_55cffbeab8324',
						'label' => 'NetSuite Account Number',
						'name' => 'wni_account_number',
						'type' => 'text',
						'instructions' => 'This is the numeric ID of the NetSuite account. This is not the ID of the user.',
						'required' => 1,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'maxlength' => '',
						'readonly' => 0,
						'disabled' => 0,
					),
					array (
						'key' => 'field_55cffbeab83241292',
						'label' => 'Delete All Data on Uninstall?',
						'name' => 'wni_delete_data_on_uninstall',
						'type' => 'true_false',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'message' => '',
						'default_value' => 0,
					),
					array (
						'key' => 'field_55cffb13b831e',
						'label' => 'Customer Options',
						'name' => '',
						'type' => 'tab',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'placement' => 'left',
						'endpoint' => 0,
					),
					array (
						'key' => 'field_55cffc1fb8325',
						'label' => 'Customer Sync Interval',
						'name' => 'wni_customer_sync_interval',
						'type' => 'number',
						'instructions' => 'Number of hours between each customer synchronization request between NetSuite and WooCommerce store. (1-24 hrs)',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => 1,
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'min' => 1,
						'max' => 24,
						'step' => '',
						'readonly' => 0,
						'disabled' => 0,
					),
					array (
						'key' => 'field_55cffb23b831f',
						'label' => 'Product Options',
						'name' => '',
						'type' => 'tab',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'placement' => 'left',
						'endpoint' => 0,
					),
					array (
						'key' => 'field_55cffb38b8320',
						'label' => 'Quote Options',
						'name' => '',
						'type' => 'tab',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'placement' => 'left',
						'endpoint' => 0,
					),
					array (
						'key' => 'field_55cffa23f223f20',
						'label' => 'BitBucket Options',
						'name' => '',
						'type' => 'tab',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'placement' => 'left',
						'endpoint' => 0,
					),
					array (
						'key' => 'field_55cffa23f223f20a',
						'label' => 'BitBucket Message',
						'name' => '',
						'type' => 'message',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'message' => 'To enable automatic updates for this plugin, please complete all of the fields below. If it is a private repository, you\'ll also need to check private and provide a username and password for access.',
						'esc_html' => 1,
					),
					array (
						'key' => 'field_55cffa23f223f20a1',
						'label' => 'BitBucket Repository Owner Name',
						'name' => 'wni_bitbucket_repo_owner',
						'type' => 'text',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'maxlength' => '',
						'readonly' => 0,
						'disabled' => 0,
					),
					array (
						'key' => 'field_55cffa23f223f20a2',
						'label' => 'BitBucket Repository Name',
						'name' => 'wni_bitbucket_repo_name',
						'type' => 'text',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'maxlength' => '',
						'readonly' => 0,
						'disabled' => 0,
					),
					array (
						'key' => 'field_55cffa23f223f20a3',
						'label' => 'BitBucket Private Repository',
						'name' => 'wni_bitbucket_private_repository',
						'type' => 'true_false',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'message' => '',
						'default_value' => 0,
					),
					array (
						'key' => 'field_55cffa23f223f20a4',
						'label' => 'BitBucket Account Username',
						'name' => 'wni_bitbucket_username',
						'type' => 'text',
						'instructions' => 'This is the username for access to a private repository.',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'maxlength' => '',
						'readonly' => 0,
						'disabled' => 0,
					),
					array (
						'key' => 'field_55cffa23f223f20a5',
						'label' => 'BitBucket Account Password',
						'name' => 'wni_bitbucket_password',
						'type' => 'password',
						'instructions' => 'This is the password for access to a private repository',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'readonly' => 0,
						'disabled' => 0,
					),
					
				),
				'location' => array (
					array (
						array (
							'param' => 'options_page',
							'operator' => '==',
							'value' => 'acf-options-netsuite-settings',
						),
					),
				),
				'menu_order' => 0,
				'position' => 'normal',
				'style' => 'default',
				'label_placement' => 'top',
				'instruction_placement' => 'label',
				'hide_on_screen' => '',
				'active' => 1,
				'description' => '',
			));

		}
	}

	/**
	 * Install method.
	 */
	public static function install() {

		self::log_action('install_run', 'running install function @ '.date('YMD:HiS'), ABSPATH.'actionlog.log');
		$plugin = basename(dirname(__FILE__)).'/'.basename(__FILE__);

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
			array(
				'base' 		=> $upload_dir['basedir'] . '/wc-netsuite-logs/netsuite-logs',
				'file' 		=> '.htaccess',
				'content' 	=> 'deny from all'
			),
			array(
				'base' 		=> $upload_dir['basedir'] . '/wc-netsuite-logs/netsuite-logs',
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

		add_option('options_wni_host_endpoint', 'https://webservices.na1.netsuite.com');
		add_option('options_wni_email', '');
		add_option('options_wni_password', '');
		add_option('options_wni_account_number', '');
		add_option('options_wni_customer_sync_interval', 1);

		do_action('wni_post_install', $plugin);
	}

	// Perform additional actions to successfully install our plugin
    public static function post_install( $plugin ) {



		// Remember if our plugin was previously activated
		$was_activated = is_plugin_active( $plugin );

		// Since we are hosted in BitBucket, our plugin folder would have a dirname of
		// reponame-tagname change it to our original one:
		global $wp_filesystem;
		$plugin_folder = WP_PLUGIN_DIR . DS . PLUGIN_SLUG;
		$wp_filesystem->move( WP_PLUGIN_DIR . DS . basename($plugin), $plugin_folder );

        // Re-activate plugin if needed
		if ( $was_activated ) {
		    $activate = activate_plugin( $plugin );
		}
		 
    }

	/**
	 * Uninstall Method 
	 */
	public static function uninstall() {
		// Delete files and folders added at install
		$upload_dir =  wp_upload_dir();

		$directories = array(
			array(
				'base' => $upload_dir['basedir'] . '/wc-netsuite-logs',
			),
		);

		foreach ( $directories as $dir ) {
			self::recursively_rmdir($dir['base']);
		}

		if(get_option('options_wni_delete_data_on_uninstall')){
			delete_option('options_wni_host_endpoint');
			delete_option('options_wni_email');
			delete_option('options_wni_password');
			delete_option('options_wni_account_number');
			delete_option('options_wni_customer_sync_interval');
		}

		wp_clear_scheduled_hook( 'woocommerce_netsuite_integrator_customer_cron' );
	}

	public function setup_cron() {

		$netsuite_customer_integrator = new SCM_WC_Netsuite_Integrator_Customer();
		// Schedule Cron Job Event
		if ( ! wp_next_scheduled( 'woocommerce_netsuite_integrator_customer_cron' ) ) {
			wp_schedule_event( current_time( 'timestamp' ), 'woocommerce_netsuite_customer_sync_schedule', 'woocommerce_netsuite_integrator_customer_cron' );
		}
		add_action( 'woocommerce_netsuite_integrator_customer_cron', array( $netsuite_customer_integrator, 'get_modified_customers_and_update_wordpress_customers' ) );
	}

	public function woocommerce_netsuite_custom_schedule($schedules) {
	    
	    $schedules['woocommerce_netsuite_customer_sync_schedule'] = array(
	        'interval' => get_option('options_wni_customer_sync_interval') * 60 * 60, // number of hours * 60 minutes * 60 seconds
	        'display'  => __( 'WooCommerce NetSuite Integrator Custom Schedule' ),
	    );
	 
	    return $schedules;

	}

	/**
	 * WooCommerce fallback notice.
	 *
	 * @return string
	 */
	public function woocommerce_missing_notice() {
		echo '<div class="error"><p>' . sprintf( __( 'WooCommerce NetSuite Integrator depends on the last version of %s or later to work!', 'woocommerce-netsuite-integrator' ), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">' . __( 'WooCommerce 2.4+', 'woocommerce-netsuite-integrator' ) . '</a>' ) . '</p></div>';
	}

	/**
	 * Missing configuration options.
	 *
	 * @return string
	 */
	public function netsuite_configuration_missing_notice() {
		echo '<div class="error"><p>' . sprintf( __( 'WooCommerce NetSuite Integrator requires that you provide all the required %s', 'woocommerce-netsuite-integrator' ), '<a href="/wp-admin/admin.php?page=acf-options-netsuite-settings">' . __( 'NetSuite configuration options!') . '</a>' ) . '</p></div>';
	}

}
global $wp_filesystem;
// Plugin install.
register_activation_hook( __FILE__, array( 'SCM_WC_Netsuite_Integrator', 'install' ) );
// Plugin uninstall
register_deactivation_hook( __FILE__, array( 'SCM_WC_Netsuite_Integrator', 'uninstall' ) );

add_action( 'wni_post_install', array( 'SCM_WC_Netsuite_Integrator', 'install' ) );
add_action( 'plugins_loaded', array( 'SCM_WC_Netsuite_Integrator', 'get_instance' ) );

endif;