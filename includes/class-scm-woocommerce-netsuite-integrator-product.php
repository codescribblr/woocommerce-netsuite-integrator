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
		
	}

	public function get_product_by_sku($product_sku) {

		$service = $this->service;

		$errors = array();

		// SEARCH BY PRODUCT SKU
		$product_sku_search = new SearchStringField();
		$product_sku_search->operator = "contains";
		$product_sku_search->searchValue = $product_sku;

		$product_search = new ItemSearchBasic();
		$product_search->vendorName = $product_sku_search;

		$product_search_request = new SearchRequest();
		$product_search_request->searchRecord = $product_search;

		$product_search_response = $service->search($product_search_request);

		if (!$product_search_response->searchResult->status->isSuccess) {
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

	/** 
	 *	Function not working for searching multiple fields simultaneously
	 * 	Resorting to using saved searches within NetSuite UI 
	 */
	public function customer_search(){
		
		$service = $this->service;

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

		$service = $this->service;

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

		SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors, true));
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

		$service = $this->service;

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
		    SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors));
		    // mail('jon@createlaunchlead.com', 'Error Updating Customer ID', "oldId=".$oldId."\nnewId=".$newId);
	    	return $errors;
		}

		SCM_WC_Netsuite_Integrator::log_action('success', 'Customer '.$customer_internal_id.' Modified Flag Update Successful');
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
				SCM_WC_Netsuite_Integrator::log_action('success', 'Customer '.$user_id.'('.$nickname.') Created');
				return $updated;
			} else {
				SCM_WC_Netsuite_Integrator::log_action('error', print_r($user_id));
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

			SCM_WC_Netsuite_Integrator::log_action('success', 'Customer '.$user_id.'('.$nickname.') Updated Successfully');

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

endif;

// if(isset($_POST['action'])){
// 	exit('test');
// 	if(in_array(array('editpost', 'woocommerce_save_variations'), $_POST['action'])){
// 		SCM_WC_Netsuite_Integrator_Product::log_action('post_data', print_r($_POST, true)); 
// 		remove_action('wp_ajax_woocommerce_save_variations');
// 		remove_action('wp_ajax_nopriv_woocommerce_save_variations');
// 		remove_action('wc_ajax_save_variations');
// 		die('cancelled update');
// 	}
// }
