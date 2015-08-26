<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'SCM_WC_Netsuite_Integrator_Service' ) ) :

/**
 * WooCommerce NetSuite Integrator Service Functions & Calls
 */
class SCM_WC_Netsuite_Integrator_Service {

	protected $config = array();
	protected $service = null;

	protected function __construct() {
		
		$this->includes();
		$upload_dir =  wp_upload_dir();

		$this->config = apply_filters('woocommerce_netsuite_config', array(
			// Required
			"endpoint"  => "2015_1", // Current version of the NetSuite API
			"host"      => get_option('options_wni_host_endpoint'),
			"email"     => get_option('options_wni_email'),
			"password"  => get_option('options_wni_password'),
			"role"      => "3", // Must be an admin to have rights
			"account"   => get_option('options_wni_account_number'),
			// Optional
			"logging"   => true,
			"log_path"  => $upload_dir['basedir'] . '/wc-netsuite-logs/netsuite-logs',
		), $this);

		$this->connect_service();
		
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

	/**
	 * Includes.
	 */
	private function includes() {
		include_once LIB_DIR . DS . 'netsuite-phptoolkit' . DS . 'NSPHPClient.php';
		include_once LIB_DIR . DS . 'netsuite-phptoolkit' . DS . 'NetSuiteService.php';
	}

	private function connect_service() {
		$this->service = new NetSuiteService($this->config);
		return $this->service;
	}

}

endif;