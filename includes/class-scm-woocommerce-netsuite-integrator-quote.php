<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'SCM_WC_Netsuite_Integrator_Quote' ) ) :

/**
 * WooCommerce NetSuite Integrator Quote Functions & Calls
 */
class SCM_WC_Netsuite_Integrator_Quote extends SCM_WC_Netsuite_Integrator_Service {

	public function __construct() {
		
		parent::__construct();
		$this->setup_filters();
		$this->setup_actions();
		
	}

	public function setup_filters() {
		// add the filter
		// add_filter( 'woocommerce_add_cart_item', array($this, 'filter_woocommerce_add_cart_item'), 10, 2 );
		// add_filter( 'woocommerce_get_sku', array($this, 'variable_product_sku_generator'), 10, 2 );
	}

	public function setup_actions() {
		// add_action( 'woocommerce_payment_complete', 'validate_order_skus_with_netsuite', 20, 1 );
	}

	public function variable_product_sku_generator($sku, $product){
		if($product->parent->id==451) {
			// print_r($product); exit();
		}
		return $sku;
	}

	// define the woocommerce_add_cart_item callback
	public function filter_woocommerce_add_cart_item( $cart_item_data, $cart_item_key ) {

		SCM_WC_Netsuite_Integrator::log_action('cart_filtered', print_r($cart_item_data,true));
		$product = $cart_item_data['data'];

		if(($product instanceof WC_Product) || ($product instanceof WC_Product_Variation)){
			$product_sku = $product->get_sku();
			if($product instanceof WC_Product_Variation){
				$variable = new WC_Product_Variable($product);
				SCM_WC_Netsuite_Integrator::log_action('Possible Variations', print_r($variable->get_available_variations(), true));
			}
			SCM_WC_Netsuite_Integrator::log_action('Product/Variation SKU', $product_sku);
			SCM_WC_Netsuite_Integrator::log_action('Product Details', print_r($variable->get_available_variations(), true));
			SCM_WC_Netsuite_Integrator::log_action('Product/Variation From NetSuite', print_r($this->get_product_by_sku($product_sku),true));
		} else {
			$cart_item_data = array();
		}
		
		
		return $cart_item_data;

	}

	// define the woocommerce_payment_complete callback
	public function validate_order_skus_with_netsuite( $order_id ) {		
		
		$order = wc_get_order($order_id);
		return false;

	}

	
}

endif;