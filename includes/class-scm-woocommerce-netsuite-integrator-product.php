<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'SCM_WC_Netsuite_Integrator_Product' ) ) :

/**
 * WooCommerce NetSuite Integrator Product Functions & Calls
 */
class SCM_WC_Netsuite_Integrator_Product extends SCM_WC_Netsuite_Integrator_Service {

	public function __construct() {
		
		parent::__construct();
		$this->setup_filters();
		$this->setup_actions();
		
	}

	public function setup_filters() {
		
	}

	public function setup_actions() {
		add_action( 'save_post', array($this, 'validate_sku_on_save'), 10, 3 );
		add_action( 'woocommerce_ajax_save_product_variations', array($this, 'validate_sku_on_save_variation'), 10, 1 );
		add_action( 'admin_notices', array($this, 'product_admin_notices'), 0);
	}

	public function get_product_by_sku($product_sku) {

		//SKU is stored as Name in NetSuite

		$service = $this->service;

		$errors = array();

		// SEARCH BY PRODUCT SKU
		$product_sku_search = new SearchStringField();
		$product_sku_search->operator = "contains";
		$product_sku_search->searchValue = (string)$product_sku;

		$product_search = new ItemSearchBasic();
		$product_search->itemId = $product_sku_search;

		$product_search_request = new SearchRequest();
		$product_search_request->searchRecord = $product_search;

		try {
			$product_search_response = $service->search($product_search_request);
		} catch (Exception $e) {
			$errors['product_search'][] = $e->getMessage();
		}

		if (!$product_search_response->searchResult->status->isSuccess || !isset($product_search_response)) {
		    $errors['product_search'][] = $product_search_response->readResponse->status->statusDetail[0]->message;
		    SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors, true));
		    return FALSE;
		} elseif ($product_search_response->searchResult->totalRecords === 0) {
			// If we can't find the item from the given SKU, then there is a continuity error between
			// SKUs in the webStore and NetSuite. We need to log the webStore SKU so that we can update
			// the system and not have this error in the future.
			$errors['product_search'][] = 'No Products Found with SKU = ' . $product_sku;
			SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors, true));
		    return FALSE;
		} else {
		    return $product_search_response->searchResult->recordList->record[0];
		}

	}

	public function validate_sku_on_save($post_ID, $post, $update) {
		//Don't run the SKU validator for BULK products.
		if(!has_category('bulk', $_POST['product_id'])){
			if(isset($_POST['product-type']) && $_POST['product-type']=='variable'){
				//Don't run the SKU validator for BULK products.
				if(!has_category('bulk', $_POST['product_id'])){
					$variation_keys = array_keys($_POST['variable_post_id']);
					foreach($variation_keys as $v){
						if(empty($_POST['variable_sku'][$v])){
							$this->add_netsuite_sku_validation_error_notice($_POST['variable_post_id'][$v],'', true);
						} else {
							if(!$this->get_product_by_sku($_POST['variable_sku'][$v])){
								$this->add_netsuite_sku_validation_error_notice($_POST['variable_post_id'][$v], $_POST['variable_sku'][$v], true);
							}
						}
					}
				}
			} elseif(isset($_POST['product-type']) && isset($_POST['_sku'])) {
				if(empty($_POST['_sku'])){
					$this->add_netsuite_sku_validation_error_notice($_POST['ID']);
				} else {
					if(!$this->get_product_by_sku($_POST['_sku'])){
						$this->add_netsuite_sku_validation_error_notice($_POST['ID'], $_POST['_sku']);
					}
				}
			}
		}
	}

	public function validate_sku_on_save_variation($variation_id) {
		//Don't run the SKU validator for BULK products.
		if(!has_category('bulk', $_POST['product_id'])){
			$variation_keys = array_keys($_POST['variable_post_id']);
			foreach($variation_keys as $v){
				if(empty($_POST['variable_sku'][$v])){
					WC_Admin_Meta_Boxes::add_error( sprintf(__( 'Product Variation #%d SKU is blank. This product will not be added to any quotes until the SKU matches the SKU in NetSuite.', 'woocommerce-netsuite-integrator' ), $_POST['variable_post_id'][$v]) );
				} else {
					if(!$this->get_product_by_sku($_POST['variable_sku'][$v])){
						WC_Admin_Meta_Boxes::add_error( sprintf(__( 'Product Variation #%d SKU does not exist in NetSuite or the NetSuite returned an error. This product will not be added to any quotes until the SKU matches the SKU in NetSuite.', 'woocommerce-netsuite-integrator' ), $_POST['variable_post_id'][$v]) );
					}
				}
			}
		}
	}

	/**
	 * SKU Error Notice.
	 *
	 * @return string
	 */
	public function add_netsuite_sku_validation_error_notice($product_id, $variation_sku='', $is_variation=false) {
		global $post;
		$notices = get_option('wni_product_notices');
		$variation_text = $is_variation ? 'Variation ' : '';
		if(empty($variation_sku)){
			$error_string = '<div class="error"><p>' . sprintf(__( 'Product '.$variation_text.'#%d SKU is blank. This product will not be added to any quotes until the SKU matches the SKU in NetSuite.', 'woocommerce-netsuite-integrator' ), $product_id) . '</p></div>';
		} else {
			$error_string = '<div class="error"><p>' . printf(__( 'Product '.$variation_text.'#%d SKU does not exist in NetSuite or the NetSuite returned an error. This product will not be added to any quotes until the SKU matches the SKU in NetSuite.', 'woocommerce-netsuite-integrator' ), $product_id) . '</p></div>';
		}
		if(!isset($notices[$post->ID][$product_id]) && !empty($product_id)){
			$notices[$post->ID][$product_id] = $error_string;
		}
		update_option('wni_product_notices', $notices);
		
	}

	/**
	 * Admin Notices.
	 *
	 * @echo string
	 */
	public function product_admin_notices() {

		global $post;
	    $notices = get_option('wni_product_notices');
	    if (empty($notices)) return '';
	    foreach($notices as $pid => $messages){
	        if ($post->ID == $pid ){
	            foreach($messages as $m) {
	            	echo $m;
	            }
	            //make sure to remove notice after its displayed so its only displayed when needed.
	            unset($notices[$pid]);
	            break;
	        }
	    }
	    update_option('wni_product_notices','');
	}
	
}

endif;

$WC_NIP = new SCM_WC_Netsuite_Integrator_Product();