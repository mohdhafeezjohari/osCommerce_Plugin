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

$order = new order();
$order_total_modules = new order_total;

$order_totals = $order_total_modules->process();
$info = ( $HTTP_POST_VARS )? $HTTP_POST_VARS : $_POST;

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
			
//================================== PAYMENT SUCCESS ==================================
if ( ($skey==$key1) && ($status=="00") ) { 
    // (1) UPDATE ORDER STATUS
    $sql_data_array = array('orders_status' => $nb_order_status);	 
    tep_db_perform( TABLE_ORDERS, $sql_data_array, "update", "orders_id='" . $orderid . "'" );
    $succ = 1;
				
    $comment = "molpay payment : captured " . $order->info['comments'];
    //================================== PAYMENT FAILED ==================================
}
else {		 															
    $nb_order_status = 1;
    $comment = "molpay payment : failed " . $order->info['comments'];
}

//===========================IPN===============================================//
while ( list($k,$v) = each($info) ) 
{
    $postData[]= $k."=".$v;
}
$postdata   =implode("&",$postData);
$url        ="https://www.onlinepayment.com.my/MOLPay/API/chkstat/returnipn.php";
$ch         =curl_init();
curl_setopt($ch, CURLOPT_POST , 1 );
curl_setopt($ch, CURLOPT_POSTFIELDS , $postdata );
curl_setopt($ch, CURLOPT_URL , $url );
curl_setopt($ch, CURLOPT_HEADER , 1 );
curl_setopt($ch, CURLINFO_HEADER_OUT , TRUE );
curl_setopt($ch, CURLOPT_RETURNTRANSFER , 1 );
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER , FALSE);
$result = curl_exec( $ch );
curl_close( $ch );
//=======================END OF IPN=============================================//

$insert_id = $_POST['orderid'];

//======= ORDERS TOTAL =======
for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {
    $sql_data_array = array(
        'orders_id' => $insert_id,
        'title' => $order_totals[$i]['title'],
        'text' => $order_totals[$i]['text'],
        'value' => $order_totals[$i]['value'],
        'class' => $order_totals[$i]['code'],
        'sort_order' => $order_totals[$i]['sort_order'] );

    //tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
}

//======= ORDER NOTIFICATION =======
$customer_notification = (SEND_EMAILS == 'true')? '1' : '0';
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

	
//======= STOCK UPDATE =======
for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
    // ===== START :: stock update =====
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
    // ===== END :: stock update =====
		
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
    //tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);  // SAVED DUIRNG molpay PRE-ORDER
    $order_products_id = tep_db_insert_id(); 

    //Insert customer choosen option to order
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
            //tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array, "update", "orders_id='".$insert_id."'"); // SAVED DURING molpay PRE-ORDER
            if((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {
                $sql_data_array = array(
                    'orders_id' => $insert_id,
                    'orders_products_id' => $order_products_id,
                    'orders_products_filename' => $attributes_values['products_attributes_filename'],
                    'download_maxdays' => $attributes_values['products_attributes_maxdays'],
                    'download_count' => $attributes_values['products_attributes_maxcount'] );
                tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
            }
            $products_ordered_attributes .= "\n\t" . $attributes_values['products_options_name'] . ' ' . $attributes_values['products_options_values_name'];
        }
    }
		
    //Insert customer choosen option eof
    $total_weight += ($order->products[$i]['qty'] * $order->products[$i]['weight']);
    $total_tax += tep_calculate_tax($total_products_price, $products_tax) * $order->products[$i]['qty'];
    $total_cost += $total_products_price;

    $products_ordered .= $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'] . ' (' . $order->products[$i]['model'] . ') = ' . $currencies->display_price($order->products[$i]['final_price'], 
            $order->products[$i]['tax'], $order->products[$i]['qty']) . $products_ordered_attributes . "\n";
		
} // end for loop
//======= EMAIL CONFIRMATION =======
if($succ) {
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
        $email_order .= "\n" . EMAIL_TEXT_DELIVERY_ADDRESS . "\n" .
                        EMAIL_SEPARATOR . "\n" .
                        tep_address_label($customer_id, $sendto, 0, '', "\n") . "\n";
    }

    $email_order .= "\n" . EMAIL_TEXT_BILLING_ADDRESS . "\n" .
                    EMAIL_SEPARATOR . "\n" .
                    tep_address_label($customer_id, $billto, 0, '', "\n") . "\n\n";
    if (is_object($$payment)) {
        $email_order .= EMAIL_TEXT_PAYMENT_METHOD . "\n" .
                        EMAIL_SEPARATOR . "\n";
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
    $cart->reset(true);
    tep_session_unregister('sendto');
    tep_session_unregister('billto');
    tep_session_unregister('shipping');
    tep_session_unregister('payment');
    tep_session_unregister('comments');
    tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
}
else {
    tep_redirect( tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=molpay&ErrDesc=molpaypaymentfailed', 'SSL') );    
}
?>
