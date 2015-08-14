<?php
/**
 * MOLPay osCommerce Plugin
 * 
 * @package Payment Gateway
 * @author MOLPay Technical Team <technical@molpay.com>
 * @version 2.0.0
 */

require('includes/application_top.php');
require('includes/languages/english/checkout_process.php');
require(DIR_WS_CLASSES . 'order.php');
require(DIR_WS_CLASSES . 'order_total.php');

$order = new order;
$order_total_modules = new order_total;
$order_totals = $order_total_modules->process();
  
$oid = $_REQUEST['orderid'];
$cust_q = mysql_query("SELECT customers_id FROM " . TABLE_ORDERS . " WHERE orders_id = '" . $oid . "' LIMIT 1");
$cust_r = mysql_fetch_assoc( $cust_q );
$cust_id = $cust_r['customers_id'];

$od_obj = get_odObj($oid);
$order_totals = $od_total;
$order->products = $od_obj['products'];
$order->info     = $od_obj['info'];
$order->customer = $od_obj['customer'];


// ===========================:: return array similar to $order ::=========================
function get_odObj( $order_id ) {
    global $languages_id;
      
    $index = 0;
    $products = array();
    $info = array();
    $total_a = array(); //$this->totals
    $customer = array();
    $delivery = array();
    $billing =array();

    $order_query = tep_db_query("select * from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
    $order = tep_db_fetch_array($order_query);

    $totals_query = tep_db_query("select title, text from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int)$order_id . "' order by sort_order");
    while ($totals = tep_db_fetch_array($totals_query)) {
        $total_a[] = array(
            'title' => $totals['title'],
            'text' => $totals['text'] );
    }

    $order_total_query = tep_db_query("select text from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int)$order_id . "' and class = 'ot_total'");
    $order_total = tep_db_fetch_array($order_total_query);

    $shipping_method_query = tep_db_query("select title from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int)$order_id ."' and class = 'ot_shipping'");
    $shipping_method = tep_db_fetch_array($shipping_method_query);

    $order_status_query = tep_db_query("select orders_status_name from " . TABLE_ORDERS_STATUS . " where orders_status_id = '" . $order['orders_status']. "' and language_id = '" . (int)$languages_id . "'");
    $order_status = tep_db_fetch_array($order_status_query);

    $info = array(
        'currency' => $order['currency'],
        'currency_value' => $order['currency_value'],
        'payment_method' => $order['payment_method'],
        'cc_type' => $order['cc_type'],
        'cc_owner' => $order['cc_owner'],
        'cc_number' => $order['cc_number'],
        'cc_expires' => $order['cc_expires'],
        'date_purchased' => $order['date_purchased'],
        'orders_status' => $order_status['orders_status_name'],
        'last_modified' => $order['last_modified'],
        'total' => strip_tags($order_total['text']),
        'shipping_method' => ((substr($shipping_method['title'], -1) == ':') ? substr(strip_tags($shipping_method['title']), 0, -1) : strip_tags($shipping_method['title'])));
      

    $customer = array(
        'id' => $order['customers_id'],
        'name' => $order['customers_name'],
        'company' => $order['customers_company'],
        'street_address' => $order['customers_street_address'],
        'suburb' => $order['customers_suburb'],
        'city' => $order['customers_city'],
        'postcode' => $order['customers_postcode'],
        'state' => $order['customers_state'],
        'country' => array('title' => $order['customers_country']),
        'format_id' => $order['customers_address_format_id'],
        'telephone' => $order['customers_telephone'],
        'email_address' => $order['customers_email_address'] );

    $delivery = array(
        'name' => trim($order['delivery_name']),
        'company' => $order['delivery_company'],
        'street_address' => $order['delivery_street_address'],
        'suburb' => $order['delivery_suburb'],
        'city' => $order['delivery_city'],
        'postcode' => $order['delivery_postcode'],
        'state' => $order['delivery_state'],
        'country' => array('title' => $order['delivery_country']),
        'format_id' => $order['delivery_address_format_id'] );


    if (empty($delivery['name']) && empty($delivery['street_address'])) {
        $delivery = false;
    }

    $billing = array(
        'name' => $order['billing_name'],
        'company' => $order['billing_company'],
        'street_address' => $order['billing_street_address'],
        'suburb' => $order['billing_suburb'],
        'city' => $order['billing_city'],
        'postcode' => $order['billing_postcode'],
        'state' => $order['billing_state'],
        'country' => array('title' => $order['billing_country']),
        'format_id' => $order['billing_address_format_id'] );

      
    $index = 0;
    $orders_products_query = tep_db_query("select orders_products_id, products_id, products_name, products_model, products_price, products_tax, products_quantity, final_price from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$order_id . "'");
    while ($orders_products = tep_db_fetch_array($orders_products_query)) {
        $products[$index] = array(
            'qty' => $orders_products['products_quantity'],
            'id' => $orders_products['products_id'],
            'name' => $orders_products['products_name'],
            'model' => $orders_products['products_model'],
            'tax' => $orders_products['products_tax'],
            'price' => $orders_products['products_price'],
            'final_price' => $orders_products['final_price'] );
        
        $subindex = 0;
        $attributes_query = tep_db_query("select products_options, products_options_values, options_values_price, price_prefix from " .TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " where orders_id = '" . (int)$order_id . "' and orders_products_id = '" .(int)$orders_products['orders_products_id'] . "'");
        if (tep_db_num_rows($attributes_query)) {
            while ($attributes = tep_db_fetch_array($attributes_query)) {
                $products[$index]['attributes'][$subindex] = array(
                    'option' => $attributes['products_options'],
                    'value' => $attributes['products_options_values'],
                    'prefix' => $attributes['price_prefix'],
                    'orders_product_id' => $orders_products['orders_products_id'],
                    'price' => $attributes['options_values_price'] );
            $subindex++;
            }
        }

        $info['tax_groups']["{$products[$index]['tax']}"] = '1';

        $index++;
    } 
      
    $arr['products'] = $products;
    $arr['info']     = $info;
    $arr['total_a'] = $total_a;
    $arr['customer'] = $customer;
    $arr['delivery'] = $delivery;
    return $arr;
}
  
function combine($arr1,$arr2) {
    $n = sizeof($arr1);
    for ($i=0; $i<$n; $i++) {
        $arr[$arr1[$i]] = $arr2[$i];
    }
    return $arr;
}

  
function filter_arr( $arr ) {
    while ( list($k,$v)=each($arr) ) { 
        if ( $v!="" )
            $newArr[] = $v;         
    }
    return $newArr;
}

$info = ( $HTTP_POST_VARS )? $HTTP_POST_VARS : $_POST;

$nbcb = $info['nbcb'];
$amount = $info['amount'];
$orderid = $info['orderid'];
$appcode = $info['appcode'];
$tranID = $info['tranID'];
$domain = $info['domain'];
$status = $info['status'];
$currency = $info['currency'];
$paydate = $info['paydate'];
$chnanel = $info['channel'];
$skey = $info['skey'];
$password = MODULE_PAYMENT_molpay_KEY;

$nb_order_status = MODULE_PAYMENT_molpay_ORDER_STATUS_ID; 
					  			
$key0 = md5( $tranID.$orderid.$status.$domain.$amount.$currency );
$key1 = md5( $paydate.$domain.$key0.$appcode.$password );
			
if ( $skey!=$key1 ) 
    $status = "-1";
			
if ( $nbcb=="1" ) {
    //================================== PAYMENT SUCCESS ==================================
    if ( ($skey==$key1) && ($status=="00") ) {
    
        // (1) UPDATE ORDER STATUS
        $sql_data_array = array('orders_status' => $nb_order_status);	 
        tep_db_perform(TABLE_ORDERS, $sql_data_array, "update", "orders_id='".$orderid."'");
        $succ = 1;
        $comment = "(callback) molpay payment : captured " . $order->info['comments'];
			
    //================================== PAYMENT FAILED ==================================
    }
    else {		 				
        $nb_order_status = 1;
        $comment = "(callback) molpay payment : failed ".$order->info['comments'];
    }
	
     //===========================CALLBACK IPN===============================================//
    if($nbcb==1)
    {
        echo "CBTOKEN:MPSTATOK";
    }
    //=======================END OF IPN=============================================//
    
    $insert_id = $_POST['orderid'];
  
	
    //======= ORDERS TOTAL ======= // CHECK HERE!
    for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {
        $sql_data_array = array(
            'orders_id' => $insert_id,
            'title' => $order_totals[$i]['title'],
            'text' => $order_totals[$i]['text'],
            'value' => $order_totals[$i]['value'],
            'class' => $order_totals[$i]['code'],
            'sort_order' => $order_totals[$i]['sort_order'] );
        //  tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
    }
	
	
    //======= ORDER NOTIFICATION =======
    $customer_notification = (SEND_EMAILS == 'true') ? '1' : '0';
    $sql_data_array = array(
        'orders_id' => $insert_id,
        'orders_status_id' => $nb_order_status,
        'date_added' => 'now()',
        'customer_notified' => $customer_notification,
        'comments' => $comment );
  
    tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
	
    // Initialized for the email confirmation
    $products_ordered = '';
    $subtotal = 0;
    $total_tax = 0;

    //======================= STOCK UPDATE ========================
    for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
        if ( STOCK_LIMITED == 'true' && $succ=="1" ) {
            if (DOWNLOAD_ENABLED == 'true') {
                $stock_query_raw = "SELECT products_quantity, pad.products_attributes_filename
                            FROM " . TABLE_PRODUCTS . " p
                            LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                             ON p.products_id=pa.products_id
                            LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                             ON pa.products_attributes_id=pad.products_attributes_id
                            WHERE p.products_id = '" . tep_get_prid($order->products[$i]['id']) . "'";
														

                // Will work with only one option for downloadable products. Otherwise, we have to build the query dynamically with a loop
                $products_attributes = $order->products[$i]['attributes'];
                if (is_array($products_attributes)) {
                    $stock_query_raw .= " AND pa.options_id = '" . $products_attributes[0]['option_id'] . "' AND pa.options_values_id = '" . $products_attributes[0]['value_id'] . "'";
                }
                $stock_query = tep_db_query($stock_query_raw);
            }
            else {
                $stock_query_raw = "select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'";
                $stock_query = tep_db_query("select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
            }
      
      
            if (tep_db_num_rows($stock_query) > 0) {
                $stock_values = tep_db_fetch_array($stock_query); 
                // Do not decrement quantities if products_attributes_filename exists
                if ((DOWNLOAD_ENABLED != 'true') || (!$stock_values['products_attributes_filename'])) {
                    $stock_left = $stock_values['products_quantity'] - $order->products[$i]['qty'];
                }
                else {
                    $stock_left = $stock_values['products_quantity'];
                }
                tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = '" . $stock_left . "' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                if ( ($stock_left < 1) && (STOCK_ALLOW_CHECKOUT == 'false') ) {
                    tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '0' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                }
            }
        }
   
        // ======================== END :: stock update =======================
		
        // Update products_ordered (for bestsellers list)
        tep_db_query("update " . TABLE_PRODUCTS . " set products_ordered = products_ordered + " . sprintf('%d', $order->products[$i]['qty']) . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");


        $sql_data_array = array(
            'orders_id' => $insert_id,
            'products_id' => tep_get_prid($order->products[$i]['id']),
            'products_model' => $order->products[$i]['model'],
            'products_name' => $order->products[$i]['name'],
            'products_price' => $order->products[$i]['price'],
            'final_price' => $order->products[$i]['final_price'],
            'products_tax' => $order->products[$i]['tax'],
            'products_quantity' => $order->products[$i]['qty'] );
        //tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array); // SAVED DURING molpay PRE-ORDER
        $order_products_id = tep_db_insert_id(); 

        // Insert customer choosen option to order
        $attributes_exist = '0';
        $products_ordered_attributes = '';
    
    
        if (isset($order->products[$i]['attributes'])) {
            $attributes_exist = '1';
            for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
                if (DOWNLOAD_ENABLED == 'true') {
                    $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
                                        from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                        left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                        on pa.products_attributes_id=pad.products_attributes_id
                                        where pa.products_id = '" . $order->products[$i]['id'] . "'
                                        and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
                                        and pa.options_id = popt.products_options_id
                                        and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
                                        and pa.options_values_id = poval.products_options_values_id
                                        and popt.language_id = '1'
                                        and poval.language_id = '1' ";
                    $attributes = mysql_query($attributes_query);
                }
                else {
                    $attributes = mysql_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '1' and poval.language_id = '1' ");
                }
                $attributes_values = tep_db_fetch_array($attributes);
                $sql_data_array = array(
                    'orders_id' => $insert_id,
                    'orders_products_id' => $order_products_id,
                    'products_options' => $attributes_values['products_options_name'],
                    'products_options_values' => $attributes_values['products_options_values_name'],
                    'options_values_price' => $attributes_values['options_values_price'],
                    'price_prefix' => $attributes_values['price_prefix'] );
                //tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array); // SAVED DURING molpay PRE-ORDER
                if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {
                    $sql_data_array = array(
                        'orders_id' => $insert_id,
                        'orders_products_id' => $order->products[$i]['attributes'][$j]['orders_products_id'],
                        'orders_products_filename' => $attributes_values['products_attributes_filename'],
                        'download_maxdays' => $attributes_values['products_attributes_maxdays'],
                        'download_count' => $attributes_values['products_attributes_maxcount'] );
                    tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
                }
                $products_ordered_attributes .= "\n\t" . $attributes_values['products_options_name'] . ' ' . $attributes_values['products_options_values_name'];
            }
        }
		
        // Insert customer choosen option eof
        $total_weight += ($order->products[$i]['qty'] * $order->products[$i]['weight']);
        $total_tax += tep_calculate_tax($total_products_price, $products_tax) * $order->products[$i]['qty'];
        $total_cost += $total_products_price;

        $products_ordered .= $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'] . 
                            ' (' . $order->products[$i]['model'] . ') = ' . $currencies->display_price($order->products[$i]['final_price'], 
                            $order->products[$i]['tax'], $order->products[$i]['qty']) . $products_ordered_attributes . "\n";
		
    }	
    //=================== EMAIL CONFIRMATION ===================
    if ( $succ ) {
        $email_order =  STORE_NAME . "\n" .
                        EMAIL_SEPARATOR . "\n" .
                        EMAIL_TEXT_ORDER_NUMBER . ' ' . $insert_id . "\n" .
                        EMAIL_TEXT_INVOICE_URL . ' ' . tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $insert_id, 'SSL', false) . "\n" .
                        EMAIL_TEXT_DATE_ORDERED . ' ' . strftime(DATE_FORMAT_LONG) . "\n\n";
        if ($order->info['comments']) {
            $email_order .= tep_db_output($order->info['comments']) . "\n\n";
        }
        $email_order .= EMAIL_TEXT_PRODUCTS . "\n" .
                        EMAIL_SEPARATOR . "\n" .
                        $products_ordered .
                        EMAIL_SEPARATOR . "\n";

        for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {
            $email_order .= strip_tags($order_totals[$i]['title']) . ' ' . strip_tags($order_totals[$i]['text']) . "\n";
        }

        if ($order->content_type != 'virtual') {
            $email_order .= "\n" . EMAIL_TEXT_DELIVERY_ADDRESS . "\n" . EMAIL_SEPARATOR . "\n" . tep_address_label($customer_id, $sendto, 0, '', "\n") . "\n";
        }

        $email_order .= "\n" . EMAIL_TEXT_BILLING_ADDRESS . "\n" . EMAIL_SEPARATOR . "\n" . tep_address_label($customer_id, $billto, 0, '', "\n") . "\n\n";
        if (is_object($$payment)) {
            $email_order .= EMAIL_TEXT_PAYMENT_METHOD . "\n" . EMAIL_SEPARATOR . "\n";
            $payment_class = $$payment;
            $email_order .= $payment_class->title . "\n\n";
            if ($payment_class->email_footer) {
              $email_order .= $payment_class->email_footer . "\n\n";
            }
        }
        tep_mail($order->customer['firstname'] . ' ' . $order->customer['lastname'], $order->customer['email_address'], EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

        // Send emails to other people
        if (SEND_EXTRA_ORDER_EMAILS_TO != '') {
            tep_mail('', SEND_EXTRA_ORDER_EMAILS_TO, EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        }
        //tep_session_unregister('cart');

        /** ======================== comment this for callback url ===================================
        $cart->reset(true);
        tep_session_unregister('sendto');
        tep_session_unregister('billto');
        tep_session_unregister('shipping');
        tep_session_unregister('payment');
        tep_session_unregister('comments');
        tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
        ============================================================================================ **/
    }
    //else { tep_redirect( tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=molpay&ErrDesc=molpaypaymentfailed', 'SSL') ); }
 } 
?>
