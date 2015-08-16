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

		$this->config = array(
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
		);

		$this->connect_service();
		
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