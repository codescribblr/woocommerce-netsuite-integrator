<?php
/*
Plugin Name: WooCommerce NetSuite Integrator
Plugin URI: http://wordpress.org/plugins/woocommerce-netsuite-integrator/
Description: WooCommerce NetSuite Integrator.
Author: Showcase Marketing
Author URI: http://createlaunchlead.com
Version: 1.0.3
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
	const VERSION = '1.0.3';

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
		$upload_dir =  wp_upload_dir();

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'tgmpa_register', array( $this, 'register_required_plugins' ) );

		$this->config = array(
			// Required
			"endpoint"  => "2015_1", // Current version of the NetSuite API
			"host"      => get_option('_options_wni_host_endpoint'),
			"email"     => get_option('_options_wni_email'),
			"password"  => get_option('_options_wni_password'),
			"role"      => "3", // Must be an admin to have rights
			"account"   => get_option('_options_wni_account_number'),
			// Optional
			"logging"   => true,
			"log_path"  => $upload_dir['basedir'] . '/wc-netsuite-logs/netsuite-logs',
		);


		// Checks if WooCommerce is installed.
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3', '>=' ) ) {			
			
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

	public static function recursively_rmdir($dir) { 
		if (is_dir($dir)) { 
			$objects = scandir($dir); 
			foreach ($objects as $object) { 
				if ($object != "." && $object != "..") { 
					if (filetype($dir."/".$object) == "dir") recursively_rmdir($dir."/".$object); else unlink($dir."/".$object); 
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
			array(
				'name'      	=> 'GitHub Updater',
				'slug'      	=> 'github-updater',
				'source'    	=> 'https://github.com/afragen/github-updater/archive/master.zip',
				'required'  	=> true, // If false, the plugin is only 'recommended' instead of required.
				'external_url' 	=> 'https://github.com/afragen/github-updater',
				'version'		=> '5.0',
			),
			array(
				'name'               => 'Advanced Custom Fields Pro', // The plugin name.
				'slug'               => 'advanced-custom-fields', // The plugin slug (typically the folder name).
				'source'             => BUNDLED_PLUGINS_DIR . DS . 'advanced-custom-fields.zip', // The plugin source.
				'required'           => true, // If false, the plugin is only 'recommended' instead of required.
				'version'            => '5.2.9', // E.g. 1.0.0. If set, the active plugin must be this version or higher. If the plugin version is higher than the plugin version installed, the user will be notified to update the plugin.
				'force_activation'   => true, // If true, plugin is activated upon theme activation and cannot be deactivated until theme switch.
				'force_deactivation' => false, // If true, plugin is deactivated upon theme switch, useful for theme-specific plugins.
				'external_url'       => 'http://www.advancedcustomfields.com/pro/', // If set, overrides default API URL and points to an external URL.
				'is_callable'        => array('acf_options_page', 'admin_menu'), // If set, this callable will be be checked for availability to determine if a plugin is active.
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
		require_once LIB_DIR . DS . 'tgmpa' . DS . 'class-tgm-plugin-activation.php';
		include_once INCLUDES_DIR . DS . 'class-scm-woocommerce-netsuite-integrator-service';
		include_once INCLUDES_DIR . DS . 'class-scm-woocommerce-netsuite-integrator-customer';
	}

	/**
	 * Get the plugin options.
	 *
	 * @return array
	 */
	public static function get_options() {
		
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

		update_option('_options_wni_host_endpoint', 'https://webservices.na1.netsuite.com');
		update_option('_options_wni_email', 'stanton@wolfpackwholesale.com');
		update_option('_options_wni_password', 'Password300');
		update_option('_options_wni_account_number', '3787604');
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
			self::recursively_rmdir($dir);
		}

		delete_option('_options_wni_host_endpoint');
		delete_option('_options_wni_email');
		delete_option('_options_wni_password');
		delete_option('_options_wni_account_number');
	}

	/**
	 * WooCommerce fallback notice.
	 *
	 * @return string
	 */
	public function woocommerce_missing_notice() {
		echo '<div class="error"><p>' . sprintf( __( 'WooCommerce NetSuite Integrator depends on the last version of %s or later to work!', 'woocommerce-netsuite-integrator' ), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">' . __( 'WooCommerce 2.4+', 'woocommerce-netsuite-integrator' ) . '</a>' ) . '</p></div>';
	}

}

// Plugin install.
register_activation_hook( __FILE__, array( 'SCM_WC_Netsuite_Integrator', 'install' ) );
// Plugin uninstall
register_deactivation_hook( __FILE__, array( 'SCM_WC_Netsuite_Integrator', 'uninstall' ) );

add_action( 'plugins_loaded', array( 'SCM_WC_Netsuite_Integrator', 'get_instance' ) );

endif;