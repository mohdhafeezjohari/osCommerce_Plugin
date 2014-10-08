<?php
/**
 * MOLPay osCommerce Plugin
 * 
 * @package Payment Gateway
 * @author MOLPay Technical Team <technical@molpay.com>
 * @version 2.0.0
 */

class molpay {
    
    public  $code, 
            $title, 
            $description, 
            $enabled , 
            $Return_Payment, 
            $order_status;

    function __construct() {
        $this->code = 'molpay';
        $this->title = MODULE_PAYMENT_molpay_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_molpay_TEXT_DESCRIPTION;
        $this->enabled = ((MODULE_PAYMENT_molpay_STATUS == 'True') ? true : false);
        $this->form_action_url = 'https://www.onlinepayment.com.my/MOLPay/pay/' . MODULE_PAYMENT_molpay_MERCHANTID . "/";	

        if ((int)MODULE_PAYMENT_molpay_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_molpay_ORDER_STATUS_ID;
        }	  
        $this->Return_Payment = array();      	  
    }
    
    function javascript_validation() {
        return false;
    }

    function selection() {
        return array(
            'id' => $this->code,
            'module' => $this->title );
    }

    function pre_confirmation_check() {
        return false;
    }

    function confirmation() {
        return false;
    }

    function process_button() {
        global  $HTTP_SERVER_VARS, 
                $CardNumber, 
                $order, 
                $customer_id, 
                $orders_id, 
                $currencies, 
                $currency, 
                $ot_subtotal, 
                $ot_shipping, 
                $ot_total;
        
        if ($order->products[0]) foreach ($order->products as $key => $product_no) {
            if ($prod_list) { 
                $prod_list .= " ";
                $prod_list_names .= ", ";                
            }
            $prod_list .= $product_no['id'];
            $prod_list_names .= $product_no['name'] . " x " . $product_no['qty'];
        }      
      
        $this->pre_order($prod_list_names);
        $this->pre_prod($order->products); // UPDATE TABLE PRODUCT FOR PRE-ORDER
      
        $oid_query = tep_db_query("select Max(orders_id) as maxoid from " . TABLE_ORDERS . " ");
        $oid_res = tep_db_fetch_array($oid_query);
        $oid = $oid_res['maxoid'];	
			
        $curr_rate = $currencies->currencies[$currency][value];
        $amt = $order->info['total'] * $curr_rate;
        $nb_amt = number_format( $amt, 2,'.','');
      
        $this->pre_total($oid);
      
        $vcode = md5($nb_amt.MODULE_PAYMENT_molpay_MERCHANTID.$oid.MODULE_PAYMENT_molpay_KEY);			
        $process_button_string = tep_draw_hidden_field('decline_url', tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(MODULE_PAYMENT_molpay_TEXT_ERROR_MESSAGE), 'SSL', false)) .
        tep_draw_hidden_field('merchant_id', MODULE_PAYMENT_molpay_MERCHANTID) .
        tep_draw_hidden_field('amount',  $nb_amt  ) .
        tep_draw_hidden_field('vcode',  $vcode  ) .
        tep_draw_hidden_field('orderid', $oid) .
        tep_draw_hidden_field('currency',strtolower($currency)).
        tep_draw_hidden_field('bill_name', $order->customer['firstname']." ".$order->customer['lastname']) .
        tep_draw_hidden_field('bill_desc', $prod_list_names) .
        tep_draw_hidden_field('bill_mobile', $order->customer['telephone']) .
        tep_draw_hidden_field('bill_email', $order->customer['email_address']) .
        tep_draw_hidden_field('country', $order->customer['country']['iso_code_2']) .
        tep_draw_hidden_field('cs1', $customer_id." ".$prod_list) .
        tep_draw_hidden_field('product_price', number_format($order->info['total'], 2)) .
        tep_draw_hidden_field('card_no', $order->info['cc_number']) .
        tep_draw_hidden_field('exp_month', $this->cc_expiry_month) .
        tep_draw_hidden_field('exp_year', $this->cc_expiry_year) .
        tep_draw_hidden_field('product_name', "E-cart: $prod_list_names product(s), ".$order->info['shipping_method']." delivery service(s)") .
        tep_draw_hidden_field('cvc', $this->cc_v2) .
        tep_draw_hidden_field('phone', $order->customer['telephone']) .
        tep_draw_hidden_field('street', $order->customer['street_address']) .
        tep_draw_hidden_field('city', $order->customer['city']) .
        tep_draw_hidden_field('state', $order->customer['state']) .
        tep_draw_hidden_field('returnurl',  tep_href_link('molpay_cburl.php', tep_session_name().'='.tep_session_id(), 'SSL', false)) .
        tep_draw_hidden_field('zip', $order->customer['postcode']);
        return $process_button_string;      
    }

    function pre_order($prod) {
        global $order, $customer_id;
        
        $sql_data_array = array(
            'customers_id' => $customer_id,
            'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
            'customers_company' => $order->customer['company'],
            'customers_street_address' => $order->customer['street_address'],
            'customers_suburb' => $order->customer['suburb'],
            'customers_city' => $order->customer['city'],
            'customers_postcode' => $order->customer['postcode'],
            'customers_state' => $order->customer['state'],
            'customers_country' => $order->customer['country']['title'],
            'customers_telephone' => $order->customer['telephone'],
            'customers_email_address' => $order->customer['email_address'],
            'customers_address_format_id' => $order->customer['format_id'],
            'delivery_name' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
            'delivery_company' => $order->delivery['company'],
            'delivery_street_address' => $order->delivery['street_address'],
            'delivery_suburb' => $order->delivery['suburb'],
            'delivery_city' => $order->delivery['city'],
            'delivery_postcode' => $order->delivery['postcode'],
            'delivery_state' => $order->delivery['state'],
            'delivery_country' => $order->delivery['country']['title'],
            'delivery_address_format_id' => $order->delivery['format_id'],
            'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
            'billing_company' => $order->billing['company'],
            'billing_street_address' => $order->billing['street_address'],
            'billing_suburb' => $order->billing['suburb'],
            'billing_city' => $order->billing['city'],
            'billing_postcode' => $order->billing['postcode'],
            'billing_state' => $order->billing['state'],
            'billing_country' => $order->billing['country']['title'],
            'billing_address_format_id' => $order->billing['format_id'],
            'payment_method' => $this->code,
            'cc_type' => $order->info['cc_type'],
            'cc_owner' => $order->info['cc_owner'],
            'cc_number' => $order->info['cc_number'],
            'cc_expires' => $order->info['cc_expires'],
            'date_purchased' => 'now()',
            'orders_status' => '1',
            'currency' => $order->info['currency'],
            'currency_value' => $order->info['currency_value'] );	
        tep_db_perform(TABLE_ORDERS, $sql_data_array);	
        $comment =  "(molpay pre-order) : " . $prod . " " . $order->info['comments'];
	
        $oid_query = tep_db_query("select Max(orders_id) as maxoid from " . TABLE_ORDERS . " ");
        $oid_res = tep_db_fetch_array($oid_query);
        $oid = $oid_res['maxoid'];

        $sql_data_hist = array(
            'orders_id' => $oid,
            'orders_status_id' => '1',
            'date_added' => 'now()',
            'comments' => $comment );
  
        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_hist);
    }

    function pre_prod($prods) {
        $n = sizeof($prods);
        $n2 = array();

        $oid_query = tep_db_query("select Max(orders_id) as maxoid from " . TABLE_ORDERS . " ");
        $oid_res = tep_db_fetch_array($oid_query);
        $oid = $oid_res['maxoid'];

      
        for($i=0; $i<$n; $i++) {
            $pid = $prods[$i]['id'];
            if ( $prods[$i]['attributes']!="" ) {
                $pid = tep_get_prid($prods[$i]['id']);
                $attr[$pid] = $prods[$i]['attributes'];
            }
            
            $sql_data_array = array(
                'orders_id'=>$oid,
                'products_id'=>$pid,
                'products_model'=>$prods[$i]['model'],
                'products_name'=>$prods[$i]['name'],
                'products_price'=>$prods[$i]['price'],
                'final_price'=>$prods[$i]['final_price'],
                'products_tax'=>$prods[$i]['tax'],
                'products_quantity'=>$prods[$i]['qty'] );
            tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);
      
        }
      
        if ( is_array($attr) && $attr!="" ) {
            while( list($k,$v)=each($attr) ) {
                $q = mysql_query("select orders_products_id from ".TABLE_ORDERS_PRODUCTS." where orders_id='$oid' and products_id='$k' limit 1 ");
                $r = mysql_fetch_assoc($q);
                $opid = $r['orders_products_id'];

                $size_v = sizeof($v);
                for ($j=0; $j<$size_v; $j++) {
                    $sql_att = array(
                        'orders_id'=>$oid ,
                        'orders_products_id'=>$opid ,
                        'products_options'=>$v[$j]['option'] ,
                        'products_options_values'=>$v[$j]['value'],
                        'options_values_price'=>$v[$j]['price'],
                        'price_prefix'=>$v[$j]['prefix'] );
                    tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_att); 
                }
            }
        }
    }
  
    function pre_total($oid) {
        global $ot_subtotal, $ot_shipping, $ot_total;
  
        $sub = $ot_subtotal->output[0];
        $shp = $ot_shipping->output[0];
        $tot = $ot_total->output[0];
    
        //ot_subtotal
        $od_total[0]= array(
            "code"=>$ot_subtotal->code,
            "title"=>$sub['title'],
            "text"=>$sub['text'],
            "value"=>$sub['value'],
            "sort_order"=>$ot_subtotal->sort_order );
        //ot_shipping
        $od_total[1]= array(
            "code"=>$ot_shipping->code,
            "title"=>$shp['title'],
            "text"=>$shp['text'],
            "value"=>$shp['value'],
            "sort_order"=>$ot_shipping->sort_order );
        //ot_total   
        $od_total[2]= array(
            "code"=>$ot_total->code,
            "title"=>$tot['title'],
            "text"=>$tot['text'],
            "value"=>$tot['value'],
            "sort_order"=>$ot_total->sort_order );

  
        $n = sizeof($od_total);  
  
        for ($i=0; $i<$n; $i++) {
            $sql_data_array = array(
                'orders_id' => $oid,
                'title' => $od_total[$i]['title'],
                'text' => $od_total[$i]['text'],
                'value' => $od_total[$i]['value'],
                'class' => $od_total[$i]['code'],
                'sort_order' => $od_total[$i]['sort_order'] );


            tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
        }
    }
    
    function before_process( ) {
    }

		
    function show_payment_msg( $order_id = 0 ){
        if( $this->Return_Payment[status]=="00"  ) {
            $title="Payment Success";
            if( $order_id  )   
                $link="<a href='account_history_info.php?order_id=$order_id' >Click to Order Information</a>";  
            else 
                $link="<a href='account_history.php' >Click to My Order History</a>"; 
        }
        else {
            $link="<a href='checkout_confirmation.php' >Click to Order Confirmation</a>" ;
            $title="Payment Failure";
        }
        echo "<center>
              <h1>$title</h1> 
              <pre style='width:300px;padding:10px;text-align:left;border:2px solid #333333;' >
                  ".$this->Return_Payment['message']."\n\n$link
              </pre>";   
    } 
   
    function after_process() {
    }

    function get_error() {
        global $HTTP_GET_VARS;
        $error = array(
            'title' => "Unsuccessfull Transaction",
            'error' => "Sorry, your MOLPay transaction cannot be process due to some reasons. Plese make payment again!" );
        
        return $error;
    }

    function check() {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_molpay_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    function install() {
        tep_db_query("insert into " . TABLE_CONFIGURATION . "
                   (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
                    values ('Enable molpay Module', 'MODULE_PAYMENT_molpay_STATUS', 'True', 'Do you want to accept molpay payments?', '6', '0','tep_cfg_select_option(array(\'True\', \'False\'), ', now())");

        tep_db_query("insert into " . TABLE_CONFIGURATION . " 
                   (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
                    values ('Merchant ID','MODULE_PAYMENT_molpay_MERCHANTID','','Please have molpay merchant ID set below in this form.', '6', '0', now())");

        tep_db_query("insert into " . TABLE_CONFIGURATION . "
                   (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
                    values ('MOLPay Verify Key','MODULE_PAYMENT_molpay_KEY','','Please have molpay verification key  set below in this form.', '6', '0', now())");

        tep_db_query("insert into " . TABLE_CONFIGURATION . "
                   (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
                    values ('Order Status', 'MODULE_PAYMENT_molpay_OSTATUS', '1', 'Do you want to accept molpay payments?', '6', '0','tep_cfg_select_option( array(\'True\', \'False\'), ', now()) ");   

        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
                    values ('Set Order Status', 'MODULE_PAYMENT_molpay_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");

        tep_db_query("insert into " . TABLE_CONFIGURATION . " 
                   (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
                    values ('Sort order of display.', 'MODULE_PAYMENT_molpay_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
      
    }

    function remove() {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
        return array(
            'MODULE_PAYMENT_molpay_STATUS', 
            'MODULE_PAYMENT_molpay_MERCHANTID',
            'MODULE_PAYMENT_molpay_KEY',
            'MODULE_PAYMENT_molpay_ORDER_STATUS_ID',
            'MODULE_PAYMENT_molpay_SORT_ORDER'
        );
    }
}
?>