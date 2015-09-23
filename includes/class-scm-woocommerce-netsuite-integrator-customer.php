<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'SCM_WC_Netsuite_Integrator_Customer' ) ) :

/**
 * WooCommerce NetSuite Integrator Customer Functions & Calls
 */
class SCM_WC_Netsuite_Integrator_Customer extends SCM_WC_Netsuite_Integrator_Service {

	public function __construct() {
		
		parent::__construct();
		
	}

	public function get_customer($customer_id = false) {

		do_action( 'wni_before_get_customer', $customer_id, $this );

		$service = $this->service;

		$this->errors = array();

		$request = new GetRequest();
		$request->baseRef = new RecordRef();
		$request->baseRef->internalId = $customer_id;
		$request->baseRef->type = "customer";

		$request = apply_filters( 'wni_get_customer_request', $request, $this );

		try {
			$get_customer_response = $service->get($request);
		} catch (Exception $e) {
			$this->errors['customer_search'][] = $e->getMessage();
		}

		if (!$get_customer_response->readResponse->status->isSuccess) {
		    $this->errors['customer_search'][] = $get_customer_response->readResponse->status->statusDetail[0]->message;
		    SCM_WC_Netsuite_Integrator::log_action('error', print_r($this->errors, true));
		    $customer = false;
		} else {
		    $customer = $get_customer_response->readResponse->record;
		}

		do_action( 'wni_after_get_customer', $get_customer_response, $this );

		return apply_filters( 'wni_get_customer_response', $customer, $this );

	}

	/** 
	 *	Function not working for searching multiple fields simultaneously
	 * 	Resorting to using saved searches within NetSuite UI 
	 */
	public function customer_search() {

		do_action( 'wni_before_customer_search', $this );
		
		$service = $this->service;

		$this->errors = array();

		$service->setSearchPreferences(false, 20);

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

		$customerSearchRequest = apply_filters( 'wni_customer_search_request', $customerSearchRequest, $this );

		try {
			$customerSearchResponse = $service->search($customerSearchRequest);
		} catch (Exception $e) {
			$this->errors['customer_search'][] = $e->getMessage();
		}

		if (!$customerSearchResponse->searchResult->status->isSuccess) {
		    $this->errors['customer_search'][] = $customerSearchResponse->readResponse->status->statusDetail[0]->message;
		    $search_results = false;
		} elseif ($customerSearchResponse->searchResult->totalRecords === 0) {
			$this->errors['customer_search'][] = 'No Modified Customers Found';
			$search_results = false;
		} else {
		    $search_results = $customerSearchResponse->searchResult->recordList->record;
		}

		do_action( 'wni_after_customer_search', $customerSearchResponse, $this );

		return apply_filters( 'wni_customer_search_response', $search_results, $this );
	}

	public function customer_search_by_email($email) {

		do_action( 'wni_before_customer_search_by_email', $email, $this );

		$service = $this->service;

		$this->errors = array();

		$service->setSearchPreferences(true, 20);

		// SEARCH BY EMAIL
		$web_store_search_field = new SearchStringField();
		$web_store_search_field->operator = "is";
		$web_store_search_field->searchValue = $email;

		$customer_search = new CustomerSearchBasic();
		$customer_search->email = $web_store_search_field;

		$customer_search_request = new SearchRequest();
		$customer_search_request->searchRecord = $customer_search;

		$customer_search_request = apply_filters( 'wni_customer_search_by_email_request', $customer_search_request, $this );

		$customer_search_response = $service->search($customer_search_request);
		SCM_WC_Netsuite_Integrator::log_action('search_response', print_r($customer_search_response, true));

		if (!$customer_search_response->searchResult->status->isSuccess) {
		    $this->errors['customer_search'][] = $customer_search_response->readResponse->status->statusDetail[0]->message;
		    SCM_WC_Netsuite_Integrator::log_action('error', print_r($this->errors, true));
		    return FALSE;
		} elseif ($customer_search_response->searchResult->totalRecords === 0) {
			$this->errors['customer_search'][] = 'No Customers Found with Web Store ID = ' . $order->customer->customerId;
			$customer_internal_id = FALSE;
			SCM_WC_Netsuite_Integrator::log_action('error', print_r($this->errors, true));
		} elseif ($customer_search_response->searchResult->totalRecords > 1) {
			$this->errors['customer_search'][] = 'Too many customers returned. Web Store ID was not found';
			$customer_internal_id = FALSE;
			SCM_WC_Netsuite_Integrator::log_action('error', print_r($this->errors, true));
		} else {
		    $customer_internal_id = $customer_search_response->searchResult->recordList->record[0]->internalId;
		    SCM_WC_Netsuite_Integrator::log_action('success', 'Customer Search Successful');
		}

		do_action( 'wni_after_customer_search_by_email', $customer_search_response, $this );

		return apply_filters( 'wni_customer_search_by_email_response', $customer_internal_id, $customer_search_response, $this );

	}

	/*
	* This is currently just boilerplate functionality. This functionality
	* still needs to be built and tested.
	*
	*/
	public function update_customer_in_netsuite($customer) {

		return FALSE;

		$service = $this->service;
		$this->errors = array();

		$customer = new Customer();

		$customer->addressbookList = new CustomerAddressbookList();

		// We only need to add one of the addresses here. The default is for both addresses to be the same.
		// The defaultXXXXX parameter defaults to true and only needs to be set if you are not setting the default.

		$billing_address = new CustomerAddressbook();
		$billing_address->defaultBilling = TRUE;
		$billing_address->addressee = ($order->payment->holdersName) ? $order->payment->holdersName : $order->customer->fName . " " . $order->customer->lName;
		$billing_address->addr1 = $order->customer->address->address1;
		$billing_address->addr2 = $order->customer->address->address2;
		$billing_address->city = $order->customer->address->city;
		$billing_address->state = $order->customer->address->address1state;
		$billing_address->zip = $order->customer->address->zip;
		$billing_address->country = self::compareCountryCode($order->customer->address->country);

		$shipping_address = new CustomerAddressbook();
		$shipping_address->defaultShipping = TRUE;
		$shipping_address->addressee = $order->customer->shipFName . " " . $order->customer->shipLName;
		$shipping_address->addr1 = $order->customer->shipAddress->address1;
		$shipping_address->addr2 = $order->customer->shipAddress->address2;
		$shipping_address->city = $order->customer->shipAddress->city;
		$shipping_address->state = $order->customer->shipAddress->address1state;
		$shipping_address->zip = $order->customer->shipAddress->zip;
		$shipping_address->country = self::compareCountryCode($order->customer->shipAddress->country);

		$customer->addressbookList->addressbook = array($shipping_address, $billing_address);

		// $web_store_id = new StringCustomFieldRef();
		// $web_store_id->value = $order->customer->customerId;
		// $web_store_id->internalId = 'custentity2';

		// $customer->customFieldList = new CustomFieldList();
		// $customer->customFieldList->customField = array($web_store_id);

		// ADD CUSTOM FORM REFERENCE
		$customer->customForm = new RecordRef();
		$customer->customForm->internalId = 36;

		$customer->category = new RecordRef();
		$customer->category->internalId = 4; // Internet Category

		$customer->internalId = $customerInternalId;

		$updateCustomerRequest = new UpdateRequest();
		$updateCustomerRequest->record = $customer;

		$updateCustomerResponse = $service->update($updateCustomerRequest);

		if (!$updateCustomerResponse->writeResponse->status->isSuccess) {
		    $this->errors['customerUpdate'][] = $updateCustomerResponse->writeResponse->status->statusDetail[0]->message;
		    SCM_WC_Netsuite_Integrator::log_action('error', print_r($this->errors));
	    	return FALSE;
		}
		SCM_WC_Netsuite_Integrator::log_action('success', 'Customer Update Successful');

		return $customerInternalId;

	}

	/*
	* This is currently just boilerplate functionality. This functionality
	* still needs to be built and tested.
	*
	*/
	public function add_customer_in_netsuite($customer) {

		return FALSE;

		$service = $this->service;
		$this->errors = array();

		// ADD CUSTOMER
		$customer = new Customer();
		SCM_WC_Netsuite_Integrator::log_action('started', 'Customer Object Created');
		$customer->email = $order->customer->email;
		$customer->companyName = $order->customer->fName . " " . $order->customer->lName; 
		$customer->phone = $order->customer->phone;

		// We only need to add one of the addresses here. The default is for both addresses to be the same.
		// The defaultXXXXX parameter defaults to true and only needs to be set if you are not setting the default.

		$billing_address = new CustomerAddressbook();
		$billing_address->defaultBilling = TRUE;
		$billing_address->addressee = ($order->payment->holdersName) ? $order->payment->holdersName : $order->customer->fName . " " . $order->customer->lName;
		$billing_address->addr1 = $order->customer->address->address1;
		$billing_address->addr2 = $order->customer->address->address2;
		$billing_address->city = $order->customer->address->city;
		$billing_address->state = $order->customer->address->address1state;
		$billing_address->zip = $order->customer->address->zip;
		$billing_address->country = self::compareCountryCode($order->customer->address->country);

		$shipping_address = new CustomerAddressbook();
		$shipping_address->defaultShipping = TRUE;
		$shipping_address->addressee = $order->customer->shipFName . " " . $order->customer->shipLName;
		$shipping_address->addr1 = $order->customer->shipAddress->address1;
		$shipping_address->addr2 = $order->customer->shipAddress->address2;
		$shipping_address->city = $order->customer->shipAddress->city;
		$shipping_address->state = $order->customer->shipAddress->address1state;
		$shipping_address->zip = $order->customer->shipAddress->zip;
		$shipping_address->country = self::compareCountryCode($order->customer->shipAddress->country);

		$customer->addressbookList->addressbook = array($shipping_address, $billing_address);
		$customer->subsidiary = new RecordRef();
		$customer->subsidiary->internalId = 3; //This is the Kentwool Performance division (Id preset in NetSuite)

		$webStoreId = new StringCustomFieldRef();
		$webStoreId->value = $order->customer->customerId;
		$webStoreId->internalId = 'custentity2';

		$customer->customFieldList = new CustomFieldList();
		$customer->customFieldList->customField = array($webStoreId);

		$customer->customForm = new RecordRef();
		$customer->customForm->internalId = 36;

		$customer->category = new RecordRef();
		$customer->category->internalId = 4; // Internet Category

		$addCustomerRequest = new AddRequest();
		$addCustomerRequest->record = $customer;

		$addCustomerResponse = $service->add($addCustomerRequest);

		if (!$addCustomerResponse->writeResponse->status->isSuccess) {
			$this->errors['customerAdd'][] = $addCustomerResponse->writeResponse->status->statusDetail[0]->message;
			SCM_WC_Netsuite_Integrator::log_action('error', print_r($this->errors, true));
	    	return FALSE;
		} else {
		    $customerInternalId = $addCustomerResponse->writeResponse->baseRef->internalId;
		    SCM_WC_Netsuite_Integrator::log_action('success', 'Customer Add Successful');
		}

		return $customerInternalId;

	}



	public function saved_modified_flag_customer_search() {

		do_action( 'wni_before_modified_flag_customer_search', $this );

		$service = $this->service;

		$this->errors = array();

		$customerSearch = new CustomerSearchAdvanced();
		$customerSearch->savedSearchId = '345'; 
		// $customerSearch->savedSearchScriptId => 'customsearch_web_modified_flag';

		$customerSearchRequest = new SearchRequest();
		$customerSearchRequest->searchRecord = $customerSearch;

		$customerSearchRequest = apply_filters( 'wni_modified_flag_customer_search_request', $customerSearchRequest, $this );

		try {
			$customer_search_response = $service->search($customerSearchRequest);
		} catch (Exception $e) {
			$this->errors['customer_search'][] = $e->getMessage();
		}

		if (!$customer_search_response->searchResult->status->isSuccess) {
		    $this->errors['customer_search'][] = $customer_search_response->readResponse->status->statusDetail[0]->message;
		    $search_results = false;
		} elseif ($customer_search_response->searchResult->totalRecords === 0) {
			$this->errors['customer_search'][] = 'No Modified Customers Found';
			$search_results = false;
		} else {
		    $search_results = $customer_search_response->searchResult->searchRowList->searchRow;
		    $this->errors['customer_search'][] = 'Successful';
		}

		do_action( 'wni_after_modified_flag_customer_search', $customer_search_response, $this );

		SCM_WC_Netsuite_Integrator::log_action('error', print_r($this->errors, true));
		return apply_filters( 'wni_modified_flag_customer_search_response', $search_results, $this );

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

		return $customers;

	}

	public function get_and_organize_modified_customers() {
		return $this->organize_modified_customers($this->get_modified_customers(true), true);
	}

	public function update_modified_flag($customer_internal_id, $modified_flag_value = false) {

		do_action( 'wni_before_update_modified_flag', $customer_internal_id, $modified_flag_value, $this );

		$service = $this->service;

		$this->errors = array();

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

		$update_customer_request = apply_filters( 'wni_update_modified_flag_request', $update_customer_request, $this );

		try {
			$update_customer_response = $service->update($update_customer_request);
		} catch (Exception $e) {
			$this->errors['customer_update'][] = $e->getMessage();
		}

		$update_customer_response = apply_filters( 'wni_update_modified_flag_response', $update_customer_response, $this );

		if (!$update_customer_response->writeResponse->status->isSuccess) {
		    $this->errors['customer_update'][] = $update_customer_response->writeResponse->status->statusDetail[0]->message;
		    SCM_WC_Netsuite_Integrator::log_action('error', print_r($this->errors));
		    // mail('jon@createlaunchlead.com', 'Error Updating Customer ID', "oldId=".$oldId."\nnewId=".$newId);
	    	return $this->errors;
		}

		SCM_WC_Netsuite_Integrator::log_action('success', 'Customer '.$customer_internal_id.' Modified Flag Update Successful');
		// mail('jon@createlaunchlead.com', 'Customer ID Update Successful', "oldId=".$oldId."\nnewId=".$newId);

		do_action( 'wni_after_update_modified_flag', $customer_internal_id, $update_customer_response, $this );
		return apply_filters( 'wni_update_modified_flag_response_id', $customer_internal_id, $update_customer_response, $this );

	}

	public function upsert_customer($username, $email, $password='', $first_name = '', $last_name = '', $billing = array(), $shipping = array(), $netsuite_id='') {
		
		do_action( 'wni_before_upsert_customer', func_get_args(), $this );

		$nickname = !empty($first_name) ? $first_name : $username;
		$user = get_user_by( 'email', $email );
		$user_id = ($user) ? $user->ID : FALSE;

		// If user doesn't exist, add them as a customer to wordpress
		if( $user_id === FALSE ) {

			do_action( 'wni_before_create_customer', func_get_args(), $this );

			// Generate the password and create the user
			if(empty($password)){
				$password = wp_generate_password( 12, false );
			}
			$user_id = wp_create_user( $username, $password, $email );

			do_action( 'wni_after_create_customer', $user_id, func_get_args(), $this );

			if(!is_wp_error($user_id)) {

				// Set the nickname
				$updated = wp_update_user(
					array(
						'ID'          =>    $user_id,
						'nickname'    =>    $nickname
					)
				);

				$user = new WP_User( $user_id );

				do_action( 'wni_before_update_customer', $user, func_get_args(), $this );

				update_user_meta($user_id, 'nickname', ($user->nickname) ? $user->nickname : $billing['billing_name']);
				update_user_meta($user_id, 'last_synced_with_netsuite_date', time());
				update_user_meta($user_id, 'netsuite_id', $netsuite_id);

				// Set the role
				$role = 'contributor';
				global $wp_roles;

				if ( ! isset( $wp_roles ) )
				    $wp_roles = new WP_Roles();

				if(in_array('Customer', $wp_roles->get_names())){
					$role = 'customer';
				}

				$user->set_role( $role );

				do_action( 'wni_after_update_customer', $user, func_get_args(), $this );

				// Email the user
				// wp_mail( $email_address, 'Welcome!', 'Your Password: ' . $password );
				SCM_WC_Netsuite_Integrator::log_action('success', 'Customer '.$user_id.' ('.$nickname.') Created');
				return $updated;
			} else {
				SCM_WC_Netsuite_Integrator::log_action('error', print_r($user_id));
				return FALSE;
			}

		// If user exists, update them with the new info provided
		} else {

			do_action( 'wni_before_update_customer', $user, func_get_args(), $this );

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
			update_user_meta($user_id, 'netsuite_id', $netsuite_id);

			do_action( 'wni_after_update_customer', $user, func_get_args(), $this );

			SCM_WC_Netsuite_Integrator::log_action('success', 'Customer '.$user_id.' ('.$nickname.') Updated Successfully');

			return $updated;

		}
	}

	public function get_modified_customers_and_update_wordpress_customers(){
		
		$customers = $this->get_and_organize_modified_customers();
		if(!empty($customers)){
			foreach($customers as $customer){
				if($this->upsert_customer($customer['username'], $customer['email'], $customer['password'], $customer['companyName'], '', $customer['billAddress'], $customer['shipAddress'], $customer['internalId'])){
					$this->update_modified_flag($customer['internalId']);
				}	
			}
		}

	}

	public static function compareCountryCode($code){
		return parent::compareCountryCode($code);
	}
}

endif;