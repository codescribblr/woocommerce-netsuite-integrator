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
		add_action( 'woocommerce_payment_complete', 'validate_order_skus_with_netsuite', 20, 1 );
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

	public function get_order_details( $order_id ) {

		// $WC_NIC = new SCM_WC_Netsuite_Integrator_Customer();

		$order = new WC_Order($order_id);
		// $order->netsuite_customer = $WC_NIC->get_customer('23481');
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

		$errors = array();

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
			$errors['quote_search'][] = $e->getMessage();
		}
		

		if (!$quoteSearchResponse->searchResult->status->isSuccess) {
			$errors['quote_search'][] = $quoteSearchResponse;
		    SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors, true));
		    return FALSE;
		} elseif ($quoteSearchResponse->searchResult->totalRecords === 0) {
			SCM_WC_Netsuite_Integrator::log_action('sucess', 'Quote #'.$quote_id.' not in NetSuite. We can begin.');
		} else {
		    SCM_WC_Netsuite_Integrator::log_action('error', 'Quote #'.$quote_id.' already in NetSuite');
		    return FALSE;
		}
	}

	public function test_process_netsuite($order){

		// Don't process the order if it's already in NetSuite
		if(!empty($order->netsuite_id) && is_numeric($order->netsuite_id)){
			return FALSE;
		}

		$service = $this->service;

		$errors = array();

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

		if(!$order->customer->data->netsuite_id){
			// Customer doesn't exist in NetSuite. We need to create the logic here to create them.


		}

		// If there's no errors up to this point, then we can go ahead and create the estimate
		// using our newly acquired $customerInternalId

		// Before we can actually create a sales order, we need to know the internalId for each
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
			$webStoreSKU = $WC_Product->get_sku();

			$WC_NIP = new SCM_WC_Netsuite_Integrator_Product();
			$productSearchResponse = $WC_NIP->get_product_by_sku($webStoreSKU);
			SCM_WC_Netsuite_Integrator::log_action('product_search_response', print_r($productSearchResponse, true));

			if ($productSearchResponse === 0) {
			    $errors['productSearch'][] = 'No Products Found with SKU = ' . $webStoreSKU;
			    SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors, true));
			} else {
				if($productSearchResponse===FALSE) {
					$missingItemDetails[$itemKey]['sku'] = $webStoreSKU;
					$missingItemDetails[$itemKey]['name'] = $webOrderItem['name'];
					$missingItemDetails[$itemKey]['amount'] = (float)$webOrderItem['line_subtotal'] / (int)$webOrderItem['qty'];
				    $missingItemDetails[$itemKey]['quantity'] = $webOrderItem['qty'];
				    $missingItemDetails[$itemKey]['webstoreProductId'] = ($webOrderItem['variation_id']) ? $webOrderItem['variation_id'] : $webOrderItem['product_id'];
				    $missingItemDetails[$itemKey]['description'] = "";
				} else {
					$estimateItemDetails[$itemKey]['id'] = $productSearchResponse->internalId;
					$estimateItemDetails[$itemKey]['amount'] = (float)$webOrderItem['line_subtotal'] / (int)$webOrderItem['qty'];
				    $estimateItemDetails[$itemKey]['quantity'] = $webOrderItem['qty'];
				    $estimateItemDetails[$itemKey]['webstoreProductId'] = ($webOrderItem['variation_id']) ? $webOrderItem['variation_id'] : $webOrderItem['product_id'];
				    $estimateItemDetails[$itemKey]['description'] = "";
				}
			}

				
		}

		// ADD NEW ESTIMATE

		$estimate = new Estimate();

		$estimate->customForm = new RecordRef();
		$estimate->customForm->internalId = 107; // Wolfpack Quote

		$estimate->entity = new RecordRef();
		$estimate->entity->internalId = $order->customer->data->netsuite_id;

		// $estimate->isTaxable = false;
		$estimate->itemList = new EstimateItemList();
		//$estimate->discountRate = "0";
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

		// Re-index the array with default array_keys
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

		$estimate->customFieldList = new CustomFieldList();
		$estimate->customFieldList->customField = array($web_quote_id);

		// Get the orderDateTimestamp
		$orderTime = strtotime($order->order_date);
		$estimate->tranDate = date("Y-m-d\TH:i:sP", $orderTime);

		if($order->customer_message){
			$estimate->memo = $order->customer_message;
		}

		$estimateRequest = new AddRequest();
		$estimateRequest->record = $estimate;

		$addEstimateResponse = $service->add($estimateRequest);

		if (!$addEstimateResponse->writeResponse->status->isSuccess) {
		    $errors['estimate_add'][] = $addEstimateResponse->writeResponse->status->statusDetail[0]->message;
		    SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors, true));
		    return FALSE;
		} else {
		    $newEstimate = $addEstimateResponse->writeResponse->baseRef->internalId;
		    update_post_meta($order->id, 'netsuite_id', $newEstimate);
		}
		return $newEstimate;
		
	}

	/*
	* In order for this function to work properly 
	* $order must be an object instance of a firesale order
	* This returns the newly created Sales Order ID so that
	* we can pull updates from NetSuite on this order ID
	*/
	public function process_netsuite_estimate($order, $resend = FALSE){
		
		SCM_WC_Netsuite_Integrator::log_action('processing_inside_started', 'process_netsuite_estimate has been instantiated');
		$service = $this->service;

		$errors = array();

		// AT THE OUTSET LETS SEARCH FOR THE WEB STORE ORDER ID IN NETSUITES SALES ORDERS 
		// TO FIND OUT IF THIS ORDER IS ALREADY IN THEIR SYSTEM
		// SEARCH BY CUSTOM FIELD (WEBSTORE CUSTOMER ID)
		if($order->orderNumber==0 || empty($order->orderNumber) || !isset($order->orderNumber)){
			SCM_WC_Netsuite_Integrator::log_action('error', 'There is a problem with the order number. It may not exist or is == 0. Order#: '.print_r($order->orderNumber, TRUE));
			return FALSE;
		}
		$webStoreOrderNumSearchField = new SearchTextNumberField();
		$webStoreOrderNumSearchField->operator = "equalTo";
		$webStoreOrderNumSearchField->searchValue = $order->orderNumber;

		$orderSearch = new TransactionSearchBasic();
		$orderSearch->otherRefNum = $webStoreOrderNumSearchField;

		$orderSearchRequest = new SearchRequest();
		$orderSearchRequest->searchRecord = $orderSearch;

		$orderSearchResponse = $service->search($orderSearchRequest);

		if (!$orderSearchResponse->searchResult->status->isSuccess) {
		    SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors, true));
		    return FALSE;
		} elseif ($orderSearchResponse->searchResult->totalRecords === 0) {
			SCM_WC_Netsuite_Integrator::log_action('sucess', 'Order #'.$order->orderNumber.' not in NetSuite. We can begin.');
		} else {
		    SCM_WC_Netsuite_Integrator::log_action('error', 'Order #'.$order->orderNumber.' already in NetSuite');
		    return FALSE;
		}

		// SEARCH FOR CUSTOMER BY WEBSTORE CUSTOMER ID

		$service->setSearchPreferences(false, 20);

		// SEARCH BY CUSTOM FIELD (WEBSTORE CUSTOMER ID)
		$webStoreSearchField = new SearchStringCustomField();
		$webStoreSearchField->operator = "is";
		$webStoreSearchField->searchValue = $order->customer->customerId;
		$webStoreSearchField->internalId = 'custentity2';

		$customerSearch = new CustomerSearchBasic();
		$customerSearch->customFieldList->customField = $webStoreSearchField;

		$customerSearchRequest = new SearchRequest();
		$customerSearchRequest->searchRecord = $customerSearch;

		$customerSearchResponse = $service->search($customerSearchRequest);

		if (!$customerSearchResponse->searchResult->status->isSuccess) {
		    $errors['customerSearch'][] = $customerSearchResponse->readResponse->status->statusDetail[0]->message;
		    SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors, true));
		    return FALSE;
		} elseif ($customerSearchResponse->searchResult->totalRecords === 0) {
			$errors['customerSearch'][] = 'No Customers Found with Web Store ID = ' . $order->customer->customerId;
			$customerInternalId = FALSE;
			SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors, true));
		} elseif ($customerSearchResponse->searchResult->totalRecords > 1) {
			$errors['customerSearch'][] = 'Too many customers returned. Web Store ID was not found';
			$customerInternalId = FALSE;
			SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors, true));
		} else {
		    $customerInternalId = $customerSearchResponse->searchResult->recordList->record[0]->internalId;
		    SCM_WC_Netsuite_Integrator::log_action('success', 'Customer Search Successful');
		}

		// If we have a customer in NetSuite already, update their info to the current info,
		// and move on to the next step.
		if ($customerInternalId) {
			// UPDATE CUSTOMER

			// Remove any previous $customer objects that may still be hanging around
			unset($customer);

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
			    $errors['customerUpdate'][] = $updateCustomerResponse->writeResponse->status->statusDetail[0]->message;
			    SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors));
		    	return FALSE;
			}
			SCM_WC_Netsuite_Integrator::log_action('success', 'Customer Update Successful');

			// If there's no errors, go ahead and move on to the next step.

		// If there is no customer in NetSuite, then we need to add them
		// before we can move on to creating a sales order.
		} else {
			SCM_WC_Netsuite_Integrator::log_action('started', 'Customer Add Started');
			// Remove any previous $customer objects that may still be hanging around
			unset($customer);

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
				$errors['customerAdd'][] = $addCustomerResponse->writeResponse->status->statusDetail[0]->message;
				SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors, true));
		    	return FALSE;
			} else {
			    $customerInternalId = $addCustomerResponse->writeResponse->baseRef->internalId;
			    SCM_WC_Netsuite_Integrator::log_action('success', 'Customer Add Successful');
			}

		}

		// If there's no errors up to this point, then we can go ahead and create the sales order
		// using our newly acquired $customerInternalId

		// Before we can actually create a sales order, we need to know the internalId for each
		// of the products the customer purchased. We'll have to run a search on the SKU to find
		// out the $productInternalId for each of the products. We'll store them in an array and
		// create a new estimateItem for each of the items in the $estimateItems array.

		$estimateItems = array();
		$estimateItemDetails = array();

		// We found out that Gift cards are not discounted items. So now we have to loop through the items 2x.
		// The first time to make sure there are no gift cards. If there are, then we'll have to subtract that
		// price from the subtotal and total to make sure that the discount is correct for the correct items.
		// Now that we have the correct discount percentage, we'll need to loop through a second time for us to 
		// calculate the individual price of each item. We'll need to check if it's a gift card and be sure not to 
		// set a discount on that item.
		$giftCardPurchased = FALSE;
		$giftCardAmount = 0;
		foreach($order->cart->items as $itemKey1 => $webOrderItem1){
			if($webOrderItem1->productId=="31"){
				$giftCardPurchased = TRUE;
				$giftCardAmount += ((float)$webOrderItem1->unitPrice->amount * $webOrderItem1->quantity);
			}
		}

		foreach($order->cart->items as $itemKey => $webOrderItem){

			// SEARCH BY PRODUCT SKU
			$webStoreSKU = $webOrderItem->productNumber;

			$webStoreSKUSearch = new SearchStringField();
			$webStoreSKUSearch->operator = "contains";
			$webStoreSKUSearch->searchValue = $webStoreSKU;

			$productSearch = new ItemSearchBasic();
			$productSearch->vendorName = $webStoreSKUSearch;

			$productSearchRequest = new SearchRequest();
			$productSearchRequest->searchRecord = $productSearch;

			$productSearchResponse = $service->search($productSearchRequest);

			if (!$productSearchResponse->searchResult->status->isSuccess) {
			    $errors['productSearch'][] = $productSearchResponse->readResponse->status->statusDetail[0]->message;
			    SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors, true));
			    return FALSE;
			} elseif ($productSearchResponse->searchResult->totalRecords === 0) {
				// If we can't find the item from the given SKU, then there is a continuity error between
				// SKUs in the webStore and NetSuite. We need to log the webStore SKU so that we can update
				// the system and not have this error in the future. This customer's order will continue to
				// be sent in each subsequent cron job until this SKU gets updated in the NetSuite System.
				// If the NetSuite system's SKU is correct, the customer's order will need to be manually
				// entered into NetSuite's UI and manually deleted from the orders queue. This queue is stored
				// as a local file on the server and processed each time the cron job is run. The SKU will
				// then need to be fixed and tested in the webStore before any further orders come in.
				$errors['productSearch'][] = 'No Products Found with SKU = ' . $webStoreSKU;
				SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors, true));
			    return FALSE;
			} else {
			    $estimateItemDetails[$itemKey]['id'] = $productSearchResponse->searchResult->recordList->record[0]->internalId;

			    // In order to figure out the actual price of each item, we need to take the disount and divide it by the subtotal
			    // to find out the percentage discount taken. Then we can apply that percentage discount to the item unitPrice to
			    // find the sale price for that item.
			    if($order->discount=="0.00" || $webOrderItem->productId=="31"){
			    	$estimateItemDetails[$itemKey]['amount'] = $webOrderItem->unitPrice->amount;
			    } else {
			    	$discountPercent = round( ( (float)$order->discount / ((float)$order->subtotal - $giftCardAmount) ), 2 );
			    	SCM_WC_Netsuite_Integrator::log_action('calculation_check', "discount = ". (float)$order->discount .", subtotal = ". (float)$order->subtotal .", discountPercent = ".$discountPercent);
			    	$estimateItemDetails[$itemKey]['amount'] = number_format((float)$webOrderItem->unitPrice->amount-((float)$webOrderItem->unitPrice->amount * $discountPercent), 2, '.', '');
			    }
			    
			    $estimateItemDetails[$itemKey]['quantity'] = $webOrderItem->quantity;
			    $estimateItemDetails[$itemKey]['webstoreProductId'] = $webOrderItem->productId;
			    $estimateItemDetails[$itemKey]['description'] = ($webOrderItem->giftcard_number) ? $webOrderItem->giftcard_number : "";
			}
		}

		// ADD NEW SALES ORDER

		$estimate = new Estimate();

		$estimate->customForm = new RecordRef();
		$estimate->customForm->internalId = 105;

		$estimate->entity = new RecordRef();
		$estimate->entity->internalId = $customerInternalId;

		// Only Tax for orders shipped to South Carolina (SC)
		$isTaxable = ($order->customer->shipAddress->address1state=="SC") ? TRUE : FALSE;

		$estimate->isTaxable = $isTaxable;
		$estimate->itemList = new EstimateItemList();
		//$estimate->discountRate = "0";
		foreach($estimateItemDetails as $key => $estimateItemDetail){
			$estimateItems[$key] = new EstimateItem();
			$estimateItems[$key]->item = new RecordRef();
			$estimateItems[$key]->item->internalId = $estimateItemDetail['id'];
			// No tax on Gift Cards
			$estimateItems[$key]->isTaxable = ($estimateItemDetail['webstoreProductId']=="31") ? FALSE : $isTaxable;
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
		$estimate->itemList->item = $estimateItems;
		$estimate->location = new RecordRef();
		// NetSuite InternalId for "Finished Goods-Performance" : Pickens Location;
		$estimate->location->internalId = 11;

		// Add shipping if the customer requested expedited shipping
		if($order->shipMethod=="International Express") {
			$estimate->shippingCost = $order->shipping;
			$estimate->shipMethod = new RecordRef();
			// NetSuite InternalId for USPS Overnight
			$estimate->shipMethod->internalId = 826;
		} else if($order->shipMethod=="International Standard") {
			$estimate->shippingCost = $order->shipping;
			$estimate->shipMethod = new RecordRef();
			// NetSuite InternalId for USPS International
			$estimate->shipMethod->internalId = 827;
		} else if($order->shipMethod=="Free Domestic") {
			$estimate->shippingCost = $order->shipping;
			$estimate->shipMethod = new RecordRef();
			// NetSuite InternalId for USPS
			$estimate->shipMethod->internalId = 824;
		} else if($order->shipMethod=="Priority Overnight") {
			$estimate->shippingCost = $order->shipping;
			$estimate->shipMethod = new RecordRef();
			// NetSuite InternalId for UPS Overnight
			$estimate->shipMethod->internalId = 825;
		}
		// Add payment method
		$estimate->paymentMethod = new RecordRef();
		if($order->payment->type=="Visa") {
			$estimate->paymentMethod->internalId = 5;
		} else if($order->payment->type=="Master Card") {
			$estimate->paymentMethod->internalId = 4;
		} else if($order->payment->type=="American Express") {
			$estimate->paymentMethod->internalId = 6;
		} 

		// Set the CC payment as approved in NetSuite
		$estimate->ccApproved = TRUE;

		$estimate->otherRefNum = $order->orderNumber;

		// Get the orderDateTimestamp
		$orderTime = ($order->orderDate->time != "" && $order->orderDate->time != 0) ? $order->orderDate->time : time();
		$estimate->tranDate = date("Y-m-d\TH:i:sP", $orderTime);
		SCM_WC_Netsuite_Integrator::log_action('status_update', $estimate->tranDate);
		if($order->giftMessage){
			$estimate->memo = $order->giftMessage;
		}

		$estimateRequest = new AddRequest();
		$estimateRequest->record = $estimate;

		$addEstimateResponse = $service->add($estimateRequest);

		if (!$addEstimateResponse->writeResponse->status->isSuccess) {
		    $errors['estimateAdd'][] = $addEstimateResponse->writeResponse->status->statusDetail[0]->message;
		    SCM_WC_Netsuite_Integrator::log_action('error', print_r($errors, true));
		    return FALSE;
		} else {
		    $newEstimate = $addEstimateResponse->writeResponse->baseRef->internalId;
		}
		return $newEstimate;
	}

	public static function compareCountryCode($code){
		return parent::compareCountryCode($code);
	}

	
}

endif;

if(isset($_GET['netsuite'])){
	add_action('init', 'test_function');
}
function test_function(){
	// update_post_meta(2120, 'netsuite_id', '');
	$WC_NIC = new SCM_WC_Netsuite_Integrator_Customer();
	$WC_NIQ = new SCM_WC_Netsuite_Integrator_Quote();
	$order = $WC_NIQ->get_order_details(527);
	if(!$order->customer->data->netsuite_id){
		$netsuite_id = $WC_NIC->customer_search_by_email($order->customer->data->user_email);
		if($netsuite_id){
			$order->customer->data->netsuite_id = $netsuite_id;
			update_user_meta($order->customer->ID, 'netsuite_id', $netsuite_id);
		}
	}
	print_r($order); exit();
	// $WC_NIQ->test_process_netsuite($order);
	exit();
}
