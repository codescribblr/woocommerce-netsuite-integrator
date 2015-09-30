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
		// add_filter( 'woocommerce_add_cart_item', array($this, 'filter_woocommerce_add_cart_item'), 10, 2 );
		// add_filter( 'woocommerce_get_sku', array($this, 'variable_product_sku_generator'), 10, 2 );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_resend_netsuite_order_actions' ), 10, 2 );
	}

	public function setup_actions() {
		add_action( 'woocommerce_payment_complete', array($this, 'schedule_create_netsuite_estimate'), 20, 1 );
		add_action( 'wni_create_netsuite_estimate', array($this, 'create_netsuite_estimate'), 10, 2);

		// Admin
		if ( is_admin() ) {
			// handle single order resend from order action button
			add_action( 'wp_ajax_wni_resend_netsuite_order', array( $this, 'process_ajax_order_resend' ) );
			// Add 'Resend to Netsuite' action on orders page
			add_action( 'woocommerce_admin_order_actions_start', array( $this, 'add_resend_netsuite_order_actions' ), 10, 1 );
		}
	}

	public function variable_product_sku_generator($sku, $product){
		if($product->parent->id==451) {

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

	public function get_order_details( $order_id ) {

		$order = new WC_Order($order_id);
		$order->customer = $order->get_user();
		$order->customer->netsuite_id = get_user_meta($order->customer->ID, 'netsuite_id', true);
		$order->order_items = $order->get_items();
		$order->shipping_address = array(
			'first_name'    => $order->shipping_first_name,
			'last_name'     => $order->shipping_last_name,
			'company'       => $order->shipping_company,
			'address_1'     => $order->shipping_address_1,
			'address_2'     => $order->shipping_address_2,
			'city'          => $order->shipping_city,
			'state'         => $order->shipping_state,
			'postcode'      => $order->shipping_postcode,
			'country'       => $order->shipping_country
		);
		$order->billing_address = array(
			'first_name'    => $order->billing_first_name,
			'last_name'     => $order->billing_last_name,
			'company'       => $order->billing_company,
			'address_1'     => $order->billing_address_1,
			'address_2'     => $order->billing_address_2,
			'city'          => $order->billing_city,
			'state'         => $order->billing_state,
			'postcode'      => $order->billing_postcode,
			'country'       => $order->billing_country
		);
		$order->netsuite_id = get_post_meta($order_id, 'netsuite_id', true);

		return $order;

	}

	// define the woocommerce_payment_complete callback
	public function validate_order_skus_with_netsuite( $order_id ) {		
		
		$order = wc_get_order($order_id);


	}

	/* This functionality is currently not working. 
	* [code] => INVALID_CSTM_FIELD_REF
    * [message] => The specified custom field reference bodycustfields is invalid.
    * Until this gets resolved, we can't search to see if the quote is already in NetSuite
    */
	public function search_transactions_by_webstore_quote_id($quote_id) {
		SCM_WC_Netsuite_Integrator::log_action('processing_inside_started', 'process_netsuite_estimate has been instantiated');
		$service = $this->service;

		$this->errors = array();

		// AT THE OUTSET LETS SEARCH FOR THE WEB STORE ORDER ID IN NETSUITES SALES ORDERS 
		// TO FIND OUT IF THIS ORDER IS ALREADY IN THEIR SYSTEM
		// SEARCH BY CUSTOM FIELD (WEBSTORE CUSTOMER ID)
		if($quote_id==0 || empty($quote_id) || !isset($quote_id)){
			SCM_WC_Netsuite_Integrator::log_action('error', 'There is a problem with the order number. It may not exist or is == 0. Quote#: '.print_r($quote_id, TRUE));
			return FALSE;
		}
		$webStoreQuoteNumSearchField = new SearchLongCustomField();
		$webStoreQuoteNumSearchField->operator = "equalTo";
		$webStoreQuoteNumSearchField->searchValue = $quote_id;
		// $webStoreQuoteNumSearchField->internalId = '158'; // scriptId => custbody_web_quote_id
		$webStoreQuoteNumSearchField->scriptId = 'custbody_web_quote_id';


		$quoteSearchBasic = new TransactionSearchBasic();
		$quoteSearchBasic->customFieldList = new SearchCustomFieldList();
		$quoteSearchBasic->customFieldList->customField[] = $webStoreQuoteNumSearchField;

		$quoteCustomSearch = new CustomSearchJoin();
		$quoteCustomSearch->customizationRef = new CustomizationRef();
		$quoteCustomSearch->customizationRef->scriptId = 'bodycustfields';
		$quoteCustomSearch->searchRecordBasic = $quoteSearchBasic;

		$quoteSearch = new TransactionSearch();
		$quoteSearch->customSearchJoin = $quoteCustomSearch;

		$quoteSearchColumns = new TransactionSearchRow();
		$quoteSearchColumns->basic = new TransactionSearchRowBasic();
		$quoteSearchColumns->basic->customFieldList = new SearchColumnCustomFieldList();

		$quoteSearchAdvanced = new TransactionSearchAdvanced();
		$quoteSearchAdvanced->criteria = $quoteSearch;
		$quoteSearchAdvanced->columns = $quoteSearchColumns;

		$quoteSearchRequest = new SearchRequest();
		$quoteSearchRequest->searchRecord = $quoteSearch;

		$service->setSearchPreferences(false);

		try {
			$quoteSearchResponse = $service->search($quoteSearchRequest);
		} catch (Exception $e) {
			$this->errors['quote_search'][] = $e->getMessage();
		}
		

		if (!$quoteSearchResponse->searchResult->status->isSuccess) {
			$this->errors['quote_search'][] = $quoteSearchResponse;
		    SCM_WC_Netsuite_Integrator::log_action('error', print_r($this->errors, true));
		    return FALSE;
		} elseif ($quoteSearchResponse->searchResult->totalRecords === 0) {
			SCM_WC_Netsuite_Integrator::log_action('sucess', 'Quote #'.$quote_id.' not in NetSuite. We can begin.');
		} else {
		    SCM_WC_Netsuite_Integrator::log_action('error', 'Quote #'.$quote_id.' already in NetSuite');
		    return FALSE;
		}
	}

	/*
	* In order for this function to work properly 
	* $order must be an object instance of a firesale order
	* This returns the newly created Sales Order ID so that
	* we can pull updates from NetSuite on this order ID
	*/
	public function create_netsuite_estimate($order_id, $resend = FALSE, $manual_resend = FALSE) {

		if(!get_option('options_wni_enable_quote_sync')){
			return FALSE;
		}
		
		SCM_WC_Netsuite_Integrator::log_action('processing_inside_started', 'create_netsuite_estimate has been called for order #'.$order_id);
		do_action( 'wni_before_create_netsuite_estimate', $order_id, $this );

		$order = $this->get_order_details($order_id);
		$this->errors['order_id'] = $order_id;

		// Don't process the order if it's already in NetSuite
		if( ( !empty($order->netsuite_id) && is_numeric($order->netsuite_id) ) || $order->get_status() == 'sent-netsuite'){
			return FALSE;
		}

		$service = $this->service;

		$this->errors = array();

		// Check to see if the customer is already in NetSuite. If so, let's update their user with the NetSuite Id.
		if(!$order->customer->data->netsuite_id){
			$WC_NIC = new SCM_WC_Netsuite_Integrator_Customer();
			$netsuite_customer = $WC_NIC->customer_search_by_email($order->customer->data->user_email);
			if($netsuite_customer){
				$netsuite_id = $netsuite_customer->internalId;
				$order->customer->data->netsuite_id = $netsuite_id;
				update_user_meta($order->customer->ID, 'netsuite_id', $netsuite_id);
			}
		}

		// If we have a customer in NetSuite already, update their info to the current info,
		// pull the salesRep associated with this customer,
		// and move on to the next step.
		if ($order->customer->data->netsuite_id) {

			// UPDATE CUSTOMER

			// PULL SALES REP
			$this->get_sales_rep_from_netsuite($order->id);
			
			// If there's no errors, go ahead and move on to the next step.

		// If there is no customer in NetSuite, then we need to add them
		// before we can move on to creating an estimate.
		} else {

			// Customer doesn't exist in NetSuite. We need to create the logic here to create them.

			// ADD CUSTOMER

		}

		// If there's no errors up to this point, then we can go ahead and create the estimate
		// using our newly acquired $netsuite_id

		// Before we can actually create an estimate, we need to know the internalId for each
		// of the products the customer purchased. We'll have to run a search on the SKU to find
		// out the $productInternalId for each of the products. We'll store them in an array and
		// create a new estimateItem for each of the items in the $estimateItems array.

		$estimateItems = array();
		$estimateItemDetails = array();

		// Create Default Item to ensure that every quote has at least 1 item in NetSuite
		$estimateItemDetails[0]['id'] = 31076;
		$estimateItemDetails[0]['amount'] = 0;
	    $estimateItemDetails[0]['quantity'] = 1;
	    $estimateItemDetails[0]['description'] = "";

		foreach($order->order_items as $itemKey => $webOrderItem){

			// SEARCH BY PRODUCT SKU
			$product_id = ($webOrderItem['variation_id']) ? $webOrderItem['variation_id'] : $webOrderItem['product_id'];
			$WC_Product = new WC_Product($product_id);
			if( has_category('bulk', $product_id) ) {
				add_filter( 'wni_before_validation_product_sku', array($this, 'generate_custom_sku'), 10, 3);
			}
			$webStoreSKU = apply_filters('wni_before_validation_product_sku', $WC_Product->get_sku(), $WC_Product, $webOrderItem);

			$WC_NIP = new SCM_WC_Netsuite_Integrator_Product();
			$productSearchResponse = $WC_NIP->get_product_by_sku($webStoreSKU);
			SCM_WC_Netsuite_Integrator::log_action('product_search_response', print_r($productSearchResponse, true));

			if ($productSearchResponse === 0) {
			    $this->errors['productSearch'][] = 'No Products Found with SKU = ' . $webStoreSKU;
			    $missingItemDetails[$itemKey]['sku'] = $webStoreSKU;
				$missingItemDetails[$itemKey]['name'] = $webOrderItem['name'];
				$missingItemDetails[$itemKey]['amount'] = (float)$webOrderItem['line_subtotal'] / (int)$webOrderItem['qty'];
			    $missingItemDetails[$itemKey]['quantity'] = $webOrderItem['qty'];
			    $missingItemDetails[$itemKey]['webstoreProductId'] = ($webOrderItem['variation_id']) ? $webOrderItem['variation_id'] : $webOrderItem['product_id'];
			    $missingItemDetails[$itemKey]['size'] = isset($webOrderItem['pa_size']) ? $webOrderItem['pa_size'] : '';
			    $missingItemDetails[$itemKey]['nicotene-strength'] = isset($webOrderItem['pa_nicotine-strength']) ? $webOrderItem['pa_nicotine-strength'] : '';
			    $missingItemDetails[$itemKey]['blend-pg/vg'] = isset($webOrderItem['pa_blend']) ? $webOrderItem['pa_blend'] : '';
			    $missingItemDetails[$itemKey]['flavor'] = isset($webOrderItem['pa_flavor']) ? $webOrderItem['pa_flavor'] : '';
			    SCM_WC_Netsuite_Integrator::log_action('error', print_r($this->errors, true));
			} elseif($productSearchResponse === FALSE) {
				$missingItemDetails[$itemKey]['sku'] = $webStoreSKU;
				$missingItemDetails[$itemKey]['name'] = $webOrderItem['name'];
				$missingItemDetails[$itemKey]['amount'] = (float)$webOrderItem['line_subtotal'] / (int)$webOrderItem['qty'];
			    $missingItemDetails[$itemKey]['quantity'] = $webOrderItem['qty'];
			    $missingItemDetails[$itemKey]['webstoreProductId'] = ($webOrderItem['variation_id']) ? $webOrderItem['variation_id'] : $webOrderItem['product_id'];
			    $missingItemDetails[$itemKey]['size'] = isset($webOrderItem['pa_size']) ? $webOrderItem['pa_size'] : '';
			    $missingItemDetails[$itemKey]['nicotene-strength'] = isset($webOrderItem['pa_nicotine-strength']) ? $webOrderItem['pa_nicotine-strength'] : '';
			    $missingItemDetails[$itemKey]['blend-pg/vg'] = isset($webOrderItem['pa_blend']) ? $webOrderItem['pa_blend'] : '';
			    $missingItemDetails[$itemKey]['flavor'] = isset($webOrderItem['pa_flavor']) ? $webOrderItem['pa_flavor'] : '';
			} else {
				$estimateItemDetails[$itemKey]['id'] = $productSearchResponse->internalId;
				$estimateItemDetails[$itemKey]['amount'] = (float)$webOrderItem['line_subtotal'] / (int)$webOrderItem['qty'];
			    $estimateItemDetails[$itemKey]['quantity'] = $webOrderItem['qty'];
			    $estimateItemDetails[$itemKey]['webstoreProductId'] = ($webOrderItem['variation_id']) ? $webOrderItem['variation_id'] : $webOrderItem['product_id'];
			    $estimateItemDetails[$itemKey]['description'] = $webOrderItem['name'];
			}

		}

		// ADD NEW ESTIMATE

		$estimate = new Estimate();

		$estimate->customForm = new RecordRef();
		$estimate->customForm->internalId = 107; // Wolfpack Quote

		// Attach the customer
		$estimate->entity = new RecordRef();
		$estimate->entity->internalId = $order->customer->data->netsuite_id;

		// $estimate->isTaxable = false;
		// $estimate->discountRate = "0";
		
		foreach($estimateItemDetails as $key => $estimateItemDetail){
			$estimateItems[$key] = new EstimateItem();
			$estimateItems[$key]->item = new RecordRef();
			$estimateItems[$key]->item->internalId = $estimateItemDetail['id'];
			// $estimateItems[$key]->isTaxable = false;
			$estimateItems[$key]->description = $estimateItemDetail['description'];
			$estimateItems[$key]->quantity = $estimateItemDetail['quantity'];
			// We do not need to worry about entering the price from NetSuite because the webStore can have
			// custom pricing based on dealer discounts and promo codes. We'll just enter the total.
			// $estimateItem[$key]->price = new RecordRef();
			// $estimateItem[$key]->price->internalId = $id;
			// We have an issue with data types here. The amount in NetSuite is the total amount of that particular item
			// meaning quantity * price. But the fields are both strings. So we need to cast them as the appropriate types
			// in order to keep the calculation correct.
			$estimateItems[$key]->amount = (int)$estimateItemDetail['quantity'] * (float)$estimateItemDetail['amount'];
		}

		$estimate->itemList = new EstimateItemList();

		// Re-index the array with default array_keys
		$missingItemDetails = array_values($missingItemDetails);
		$estimateItems = array_values($estimateItems);
		$estimate->itemList->item = $estimateItems;

		// Add the Order # to both the otherRefNum field and the custom field web_quote_id 
		// so we can both search for the quote later, and view the quote_id in NetSuite's Webstore tab
		/*
		*	[0] => You do not have permissions to set a value for element otherrefnum due to one of the following reasons: 1) The field is read-only; 2) An associated feature is disabled; 3) The field is available either when a record is created or updated, but not in both cases.
		*/
		// $estimate->otherRefNum = $order->id;

		$web_quote_id = new LongCustomFieldRef();
		$web_quote_id->value = $order->id;
		$web_quote_id->scriptId = 'custbody_web_quote_id'; // scriptId => custbody_web_quote_id

		$quantity_of_unknown_items = new StringCustomFieldRef;
		$quantity_of_unknown_items->value = count($missingItemDetails);
		$quantity_of_unknown_items->scriptId = 'custbody_op_quantity_of_unknown_items'; // scriptId => custbody_op_quantity_of_unknown_items

		$unknown_items_on_order = new StringCustomFieldRef;
		$unknown_items_on_order->value = json_encode($missingItemDetails);
		$unknown_items_on_order->scriptId = 'custbody_op_unknown_item_on_order'; // scriptId => custbody_op_unknown_item_on_order

		$estimate->customFieldList = new CustomFieldList();
		$estimate->customFieldList->customField[] = $web_quote_id;
		$estimate->customFieldList->customField[] = $quantity_of_unknown_items;
		$estimate->customFieldList->customField[] = $unknown_items_on_order;

		// Get the order_date_timestamp
		$order_time = strtotime($order->order_date);
		$estimate->tranDate = date("Y-m-d\TH:i:sP", $order_time);

		if($order->customer_message){
			$estimate->memo = $order->customer_message;
		}

		$add_estimate_request = new AddRequest();
		$add_estimate_request->record = $estimate;

		$add_estimate_request = apply_filters('wni_add_estimate_request', $add_estimate_request, $order, $this);

		try {
			$add_estimate_response = $service->add($add_estimate_request);
		} catch (Exception $e) {
			$this->errors['estimate_add'][] = $e->getMessage();
		}

		if (!$add_estimate_response->writeResponse->status->isSuccess) {
		    $this->errors['estimate_add'][] = $add_estimate_response->writeResponse->status->statusDetail[0]->message;
		    SCM_WC_Netsuite_Integrator::log_action('error', print_r($this->errors, true));
		    do_action('wni_create_netsuite_estimate_failed', $add_estimate_response, $this);
		    if($resend){
		    	$message = sprintf( __( 'There is some kind of issue happening with the Woocommerce NetSuite Integrator ('.$add_estimate_response->writeResponse->status->statusDetail[0]->message.'). Multiple attempts have failed to send Order #%d through to NetSuite. We will continue to attempt to send every hour, but it probably needs to be handled manually.', 'woocommerce-netsuite-integrator'), $order_id );
		    	$headers = 'From: '.get_option('blogname').' <'.get_option('admin_email').'>' . "\r\n";
		    	wp_mail(get_option('options_wni_support_email'), get_option('blogname') . ' ' . __('NetSuite Integration Error', 'woocommerce-netsuite-integrator'), $message, $headers);
		    }
		    $this->schedule_create_netsuite_estimate($order->id, time() + (60 * 60), true);
		    $order->update_status('processing');
		    $order->add_order_note('Error adding to NetSuite: '. $add_estimate_response->writeResponse->status->statusDetail[0]->message);
		    return FALSE;
		} else {
			do_action('wni_create_netsuite_estimate_succeeded', $add_estimate_response, $this);
		    $new_estimate_id = $add_estimate_response->writeResponse->baseRef->internalId;
		    $new_estimate_id = apply_filters('wni_new_estimate_id', $new_estimate_id, $add_estimate_request, $add_estimate_response, $this);
		    update_post_meta($order->id, 'netsuite_id', $new_estimate_id);
		    $order->update_status('sent-netsuite');
		    $order->add_order_note('Order added to NetSuite successfully.');
		    if(get_option('options_wni_enable_sales_rep_new_order_email')){
				$this->resend_admin_order_email($order);
			}

		}

		do_action('wni_after_create_netsuite_estimate', $order, $new_estimate_id, $add_estimate_request, $add_estimate_response, $this);

		return $new_estimate_id;
	}

	public function schedule_create_netsuite_estimate($order_id, $time_before_sheduling = FALSE, $resend = FALSE) {
		
		$time_before_sheduling = !$time_before_sheduling ? time() : $time_before_sheduling;

		wp_schedule_single_event( $time_before_sheduling, 'wni_create_netsuite_estimate', array( $order_id, $resend ) );

	}

	public function get_sales_rep_from_netsuite($order_id) {
		// PULL SALES REP
		$order = $this->get_order_details($order_id);
		$WC_NIC = new SCM_WC_Netsuite_Integrator_Customer();
		$netsuite_customer = $WC_NIC->get_entity($order->customer->data->netsuite_id);
		if($netsuite_customer){
			$sales_rep_id = $netsuite_customer->salesRep->internalId;
			$order->customer->data->sales_rep_id =  $sales_rep_id;
			update_user_meta($order->customer->ID, 'sales_rep_netsuite_id', $sales_rep_id);

			$netsuite_sales_rep = $WC_NIC->get_entity($sales_rep_id, 'employee');
			if($netsuite_sales_rep){
				$sales_rep_email = $netsuite_sales_rep->email;
				$order->customer->data->sales_rep_email =  $sales_rep_email;
				update_user_meta($order->customer->ID, 'sales_rep_email', $sales_rep_email);
			}
		}
		return $order;
	}

	// Change new order email recipient for registered customers
	public function change_admin_new_order_email_recipient( $recipient, $order ) {

		$order = $this->get_order_details($order->id);
		$customer_id = $order->get_user_id();
		if($sales_rep_email = get_user_meta($customer_id, 'sales_rep_email', true)){
			$new_recipient = $sales_rep_email;
		} else {
			$updated_order = $this->get_sales_rep_from_netsuite($order->id);
			$new_recipient = $updated_order->customer->data->sales_rep_email;
		}
	    
	    return ( empty($new_recipient) || ($recipient == $new_recipient) ) ? FALSE : $new_recipient;
	}

	// Add new order email cc
	public function add_admin_new_order_email_cc( $headers, $email_type, $order ) {

		if($email_type != 'new_order') {
			return $headers;
		}
		
		if($custom_cc_emails = get_option('options_wni_sales_rep_new_order_cc')){
			$headers .= 'CC: '. $custom_cc_emails . "\r\n";
		} else {
			return $headers;
		}
	    
	    return $headers;
	}

	public function resend_admin_order_email($order) {

		add_filter( 'woocommerce_email_recipient_new_order', array( $this, 'change_admin_new_order_email_recipient' ), 10, 2);
		add_filter( 'woocommerce_email_headers', array( $this, 'add_admin_new_order_email_cc' ), 10, 3);

		do_action( 'woocommerce_before_resend_order_emails', $order );

		// Ensure gateways are loaded in case they need to insert data into the emails
		WC()->payment_gateways();
		WC()->shipping();

		// Load mailer
		$mailer = WC()->mailer();

		$email_to_send = 'new_order';

		$mails = $mailer->get_emails();

		if ( ! empty( $mails ) ) {
			foreach ( $mails as $mail ) {
				if ( $mail->id == $email_to_send ) {
					$mail->trigger( $order->id );
				}
			}
		}

		do_action( 'woocommerce_after_resend_order_email', $order, $email_to_send );

		remove_filter( 'woocommerce_email_recipient_new_order', array( $this, 'change_admin_new_order_email_recipient'), 10 );

	}

	public function process_ajax_order_resend() {

		if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'woocommerce-netsuite-integrator' ) );
		}

		if ( ! check_admin_referer( 'wni_resend_netsuite_order' ) ) {
			wp_die( __( 'You have taken too long, please go back and try again.', 'woocommerce-netsuite-integrator' ) );
		}

		$order_id = ! empty( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : '';

		if ( ! $order_id ) {
			die;
		}
		$response = new StdClass();
		$netsuite_estimate_id = $this->create_netsuite_estimate($order_id, true, true);
		if($netsuite_estimate_id===FALSE){
			$response->type = "error";
			$response->message = "There was an error sending to NetSuite. (".$this->errors['estimate_add'][0].")";
			$response->order_num = $order_id;
		} else {
			$response->type = "updated";
			$response->message = "Order #".$order_id." was sent successfully to NetSuite.";
			$response->order_num = $order_id;
			$response->netsuite_id = $netsuite_estimate_id;
		}
		echo json_encode($response);

		exit;
	}

	public function add_resend_netsuite_order_actions($order) {

		if($order->get_status() == 'sent-netsuite' || !get_option('options_wni_enable_quote_sync')){
			return FALSE;
		}

	    $action = 'resend-netsuite';
		$url = wp_nonce_url( admin_url( 'admin-ajax.php?action=wni_resend_netsuite_order&order_id=' . $order->id ), 'wni_resend_netsuite_order' );
		$name = __( 'Resend to Netsuite', 'woocommerce-netsuite-integrator' );

		printf( '<a class="button tips %s" href="%s" data-tip="%s">%s</a>', $action, esc_url( $url ), $name, $name );

	}

	public function generate_custom_sku($sku, $WC_Product, $WC_Order_Item) {
		$item = $WC_Order_Item;
		$sku = $item['pa_size'] . '-' . $_product->get_sku() . '-' . strtoupper($item['pa_nicotine-strength']) . '-' . ucwords(str_replace('-', ' ', $item['pa_flavor'])) . '-' . substr($item['pa_blend'], 0, 2) . '/' . substr($item['pa_blend'], 2);

		return $sku;
	}

	public static function compareCountryCode($code){
		return parent::compareCountryCode($code);
	}

	
}

endif;

$WC_NIQ = new SCM_WC_Netsuite_Integrator_Quote();