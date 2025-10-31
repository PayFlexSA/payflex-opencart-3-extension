<?php
class ModelExtensionPaymentpayflex extends Model {
	private $environments = array();

	public function index() {
		$this->createEnvironments();
	}

	private function createEnvironments() {
		$this->environments["develop"]	= array(
			"name"		=>	"Sandbox Test",
			"api_url"	=>	"https://api.uat.payflex.co.za",
			"auth_url"  =>  "https://auth-uat.payflex.co.za/auth/merchant",
			"web_url"	=>	"https://api.uat.payflex.co.za",
			"auth_audience" => "https://auth-dev.payflex.co.za",
		);
		$this->environments["production"] =	array(
			"name"		=>	"Production",
			"api_url"	=>	"https://api.payflex.co.za",
			"auth_url"  =>  "https://auth.payflex.co.za/auth/merchant",
			"web_url"	=>	"https://api.payflex.co.za",
			"auth_audience" => "https://auth-production.payflex.co.za",
		);
	}

	public function getMethod($address, $total) {
		$this->log->write('PP: getMethod');
		$this->load->language('extension/payment/payflex');
		$status = true;
		$method_data = array();
		$currencies = array( 'ZAR' );
        if ( !in_array( strtoupper( $this->session->data['currency'] ), $currencies ) ) {
            $status = false;
        }
		if ( $status ) {
			$method_data = array(
				'code' => 'payflex',
				'title' => $this->language->get('text_title'),
				'terms'      => '',
				'sort_order' => $this->config->get('payment_payflex_sort_order')
			);
		}
		return $method_data;
	}

	/**
	 * Get a token from auth0 so we can do stuff - "login"
	 */
	public function getAuthorizationCode() {
		$this->createEnvironments();

		$AuthURL = '';
		$Audience = '';
		if($this->config->get('payment_payflex_test')) {
			$AuthURL = $this->environments['develop']['auth_url'];
			$Audience = $this->environments['develop']['auth_audience'];
		} else {
			$AuthURL = $this->environments['production']['auth_url'];
			$Audience = $this->environments['production']['auth_audience'];
		}

		$AuthBody = json_decode('{
				"client_id":"'.$this->config->get('payment_payflex_username').'",
				"client_secret":"'.$this->config->get('payment_payflex_password').'",
				"audience":"'.$Audience.'",
				"grant_type":"client_credentials"
		}');

		$args = array(
			'method' => 'POST',
			'headers' => array('Content-Type' => 'application/json'),
			'body' =>  $AuthBody
		);

		$this->log->write('PP: Going to fetch token...');
		$response = $this->sendCurl($AuthURL, $args, true);
		$this->log->write($response);
		// TODO: Error checking
		$response = json_decode($response);

		// if (is_wp_error($response) ){
		// 	$this->log('PayFlex API Error ' . $response->get_error_message());
		// 	$this->display_error('Could not log into PayFlex. Please try again.');
		// 	return false;
		// }

		// TODO: Error checking
		if(isset($response->access_token)) {
			return $response->access_token;
		} else {
			return '';
		}

		// if ($response['response']['code'] == '200') {
		// 	$this->log('Saving token to cache');
		// 	// set_transient('payflex_access_token', $body->access_token, (int)$body->expires_in);
		// 	return $body->access_token;
		// }else {
		// 	return false;
		// }
	}

	private function apiUrl() {
		if($this->config->get('payment_payflex_test')) {
			return $this->environments['develop']['api_url'];
		}

		return $this->environments['production']['api_url'];
	}

	private function orderUrl() {
		return $this->apiUrl() . '/order';
	}

	private function configurationUrl() {
		return $this->apiUrl() . '/v2/configuration';
	}

	private function mapOrderItems(&$order, &$cart) {
		$items = array();

		$currency_code = 'ZAR';
		foreach ($this->cart->getProducts() as $product) {
			$item_price = $this->currency->format($this->currency->convert($product['price'], $order['currency_code'], $currency_code), $currency_code, false, false);
			$item_total = $this->currency->format($this->currency->convert($product['total'], $order['currency_code'], $order['currency_code'], $currency_code), $currency_code, false, false);

			$items[] =
				'{
					"name":"'. htmlentities( (string)substr($product['name'], 0, 26) ) .'",
					"sku":"' . (string)substr($product['product_id'], 0, 12) . '",
					"quantity":'. strval($product['quantity']) .',
					"price":'. strval($item_price) .'
				}'
			;
		}
		return $items;
	}

	public function getCheckoutUrl($order, $cart) {
		$this->log->write('PP: getCheckoutUrl');
		// $this->log->write($order);

		// Get the authorization token
		$access_token = $this->getAuthorizationCode();

		// UK Only
		$currency_code = 'ZAR';

		//Process here
		$items = $this->mapOrderItems($order, $cart);
		$this->log->write($items);

		//calculate total shipping amount
		// if( method_exists($order, 'get_shipping_total') ){
		// 	//WC 3.0
		// 	$shipping_total = $order->get_shipping_total();
		// } else {
		// 	//WC 2.6.x
		// 	$shipping_total = $order->get_total_shipping();
		// }
		$shipping_total = $this->currency->format(
			$this->currency->convert($order['total'] - $cart->getSubTotal(), $order['currency_code'], $order['currency_code'], $currency_code),
			$currency_code,
			false, false
		);

		$this->load->model('account/order');

		$order_totals = $this->model_account_order->getOrderTotals($order['order_id']);

		$tax = 0;
		$shipping = 0;
		foreach($order_totals as $total) {
			if($total['code'] == 'tax') {
				$tax += $total['value'];
			}

			if($total['code'] == 'shipping') {
				$shipping += $total['value'];
			}
		}
		$orderTotal  =  $this->currency->format( $order[ 'total' ], $order[ 'currency_code' ], '', false );
		// $this->currency->format($this->currency->convert($order['total'], $order['currency_code'], $order['currency_code'], $currency_code), $currency_code, false, false);
		$OrderBodyString = '{
			"amount": '. $orderTotal .',
			"consumer": {
				"phoneNumber":  "'. $order['telephone'] .'",
				"givenNames":  "'. $order['payment_firstname'] .'",
				"surname":  "'. $order['payment_lastname'] .'",
				"email":  "'. $order['email'] .'"
			},
			"billing": {
				"addressLine1":"'. $order['payment_address_1'].'",
				"addressLine2": "'. $order['payment_address_2'].'",
				"city": "'. $order['payment_city'].'",
				"suburb": "'.$order['payment_city'].'",
				"state": "'.$order['payment_zone'].'",
				"postcode": "'.$order['payment_postcode'].'"
			},
			"shipping": {
				"addressLine1": "'. $order['shipping_address_1'] .'",
				"addressLine2": " '. $order['shipping_address_2'] .'",
				"city": "'.$order['shipping_city'].'",
				"suburb":  "'. $order['shipping_city'] .'",
				"state": "'.$order['shipping_zone'].'",
				"postcode": "'. $order['shipping_postcode'] .'"
			},
			"description": "string",
			"items": ['
		;
		foreach ($items as $i=>$item) {
			$OrderBodyString .= $item . (($i < count($items)-1) ? ',' : '');
		}
		$plugin_version =  '1.0.1';
		// $this->get_return_url( $order )
		$OrderBodyString .= '],
			"merchant": {
				"redirectConfirmUrl": "' . $this->url->link('extension/payment/payflex/payflex/success') . '&status=confirmed",
				"redirectCancelUrl": "'  . $this->url->link('extension/payment/payflex/payflex/cancel') . '&status=cancelled",
				"statusCallbackUrl": "'  . $this->url->link('extension/payment/payflex/payflex/status') . '&status=status"
			},
			"merchantReference": "'. $order['order_id'] .'",
			"token": "'. $order['order_id'] .'",
			"taxAmount": '. $this->currency->format($this->currency->convert($tax, $order['currency_code'], $order['currency_code'], $currency_code), $currency_code, false, false) .',
			"shippingAmount":'. $this->currency->format($this->currency->convert($shipping, $order['currency_code'], $order['currency_code'], $currency_code), $currency_code, false, false) .',
			"merchantSystemInformation": {
				"plugin_version": "'. $plugin_version .'",
				"php_version": "' . PHP_VERSION . '",
				"ecommerce_platform": "OpenCart ' . VERSION . '"
			}
		}';

		// $this->log->write($OrderBodyString);
		$OrderBody = json_decode($OrderBodyString);
		// $this->log->write($OrderBody);

		$order_args = array(
			'method' => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer '. $access_token
			),
			'body' => $OrderBody,
			'timeout' => 30
		);

		$this->log->write('POST Order request: ' . print_r($order_args, true));

		// $order_response = wp_remote_post($APIURL, $order_args);
		$order_response = $this->sendCurl($this->orderUrl(), $order_args, true);
		// $order_body = json_decode(wp_remote_retrieve_body($order_response));
		$order_body = json_decode($order_response);

		//Set the PayFlex order idetifiers, the orderID and the token so that they can be used to populate the payment_custom_field.
		$payflex_order_identifiers = array(
				'orderId' => $order_body->orderId,
		    'token' => $order_body->token
		);

		//Load the global checkout order model so that we can update the order data.
		$this->load->model('checkout/order');

		//Add entry to history with PayFlex response data
		$this->model_checkout_order->addOrderHistory($order['order_id'], '0', 'Connected to PayFlex to acquire PayFlex Order ID:' .$order_body->orderId.' and Token:'.$order_body->token, false);

		//Update the payment_custom_field for this order.
		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET payment_custom_field = '" . $this->db->escape(json_encode($payflex_order_identifiers))."' WHERE order_id = '". $order['order_id'] ."'");

		// echo "<pre>";
		// var_dump(escape(json_encode($payflex_order_identifiers));
		// echo "</pre>";

		$this->log->write('POST Order response: ' . print_r($order_response, true));

		// if ($access_token == false) {
		// 	// Couldn't generate token
		// 	$order->add_order_note(__('Unable to generate the order token. Payment couldn\'t proceed.', 'woo_payflex'));
		// 	wc_add_notice(__('Sorry, there was a problem preparing your payment.', 'woo_payflex'),'error');
		// 	return array(
		// 			'result' => 'failure',
		// 			'redirect' => $order->get_checkout_payment_url(true)
		// 	);
		// } else {
		$this->log->write('Created PayFlex OrderId: '.print_r($order_body,true) );

			// Order token successful, save it so we can confirm it later
			// update_post_meta($order_id,'_payflex_order_token',$order_body->token);
			// update_post_meta($order_id,'_payflex_order_id',$order_body->orderId);
			// update_post_meta($order_id,'_order_redirectURL', $order_body->redirectUrl);
			// $savedId = get_post_meta($order_id, '_payflex_order_id', true);

		// $this->log->write( 'Saved '.$savedId.' into post meta' );
		// }

		if( isset($order_body->redirectUrl) ) {
			return array(
				'result' 	=> 'success',
				'redirect'	=> $order_body->redirectUrl
			);
		} else {
			return array(
				'result' 	=> 'fail',
				'redirect'	=> '',
				'errors' => $order_body->Errors
			);
		}
	}

	public function sendCurl($url, $data, $is_post=true) {
		$this->log->write('Send request to ' . $url);
		$ch = curl_init($url);

		$allHeaders = array();
		foreach($data['headers'] as $key => $value) {
			$allHeaders[] = $key . ': ' . $value;
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
		$this->log->write($allHeaders);

		if ($is_post) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data['body']));
		} else {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

		// $this->log->write(json_encode($data['body']));
		$response = curl_exec($ch);

		if (curl_errno($ch) != CURLE_OK) {
			$response = new stdClass();
			$response->Errors = "POST Error: " . curl_error($ch) . " URL: $url";
			$response = json_encode($response);
		} else {
			$info = curl_getinfo($ch);
			// $this->log->write($info);
			if ($info['http_code'] != 200 && $info['http_code'] != 201) {
				$response = new stdClass();
				if ($info['http_code'] == 401 || $info['http_code'] == 404 || $info['http_code'] == 403) {
					$response->Errors = "Please check the API Key and Password";
				} else {
					$response->Errors = 'Error connecting to payflex: ' . $info['http_code'];
				}
				$response = json_encode($response);
			}
		}
		curl_close($ch);

		// $this->log->write($response);
		return $response;
	}

	// Functions required for the cron updater start here.

	public function getOrders() {
		$sql = "SELECT o.order_id, CONCAT(o.firstname, ' ', o.lastname) AS customer, (SELECT os.name FROM " . DB_PREFIX . "order_status os WHERE os.order_status_id = o.order_status_id AND os.language_id = '" . (int)$this->config->get('config_language_id') . "') AS status, o.shipping_code, o.total, o.currency_code, o.currency_value, o.date_added, o.date_modified FROM `" . DB_PREFIX . "order` o";

		$sql .= " WHERE o.payment_code = 'payflex' AND (`date_added` > DATE_SUB(NOW(), INTERVAL 24 HOUR)) AND (o.order_status_id = '0' OR o.order_status_id = ".$this->config->get('payment_payflex_order_status_pending')." OR o.order_status_id = ".$this->config->get('payment_payflex_order_status_failed').")";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function cronUpdateOrders($payflexorderidtocheck,$orderidtocheck,$orderstatusidtocheck) {
		// Load main order model so it can be used to update the history
		$this->load->model('checkout/order');
		// Get the authorisation token
		$access_token = $this->getAuthorizationCode();
		$update_orders_args = array(
			'method' => 'GET',
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer '. $access_token
			),
			'body' => '',
			'timeout' => 30
		);

    //Set PayFlex connection URL to get order status
		$orderstatusqueryurl = $this->orderUrl()."/".$payflexorderidtocheck;

    //Connection to PayFlex and get order status
		$updated_order_response = $this->sendCurl($orderstatusqueryurl, $update_orders_args, false);
		$updated_order_response_body = json_decode($updated_order_response);
			if (empty($updated_order_response_body->Errors)){
					$returnedorderid = $updated_order_response_body->orderId;
					$returnedorderstatus = $updated_order_response_body->orderStatus;
					if ($returnedorderstatus == "Approved"){
							$order_status_id = $this->config->get('payment_payflex_order_status_success');
						  $this->model_checkout_order->addOrderHistory($orderidtocheck, $order_status_id, 'PayFlex: ' . $returnedorderid, true);
					} elseif ($returnedorderstatus == "Created"){
							$order_status_id = $this->config->get('payment_payflex_order_status_pending');
							$this->model_checkout_order->addOrderHistory($orderidtocheck, $order_status_id, 'Checked payment status with Payflex. Still pending approval.', false);
					} elseif ($returnedorderstatus == "Abandoned"){
							$order_status_id = $this->config->get('payment_payflex_order_status_expired');
							$this->model_checkout_order->addOrderHistory($orderidtocheck, $order_status_id, 'Checked payment status with Payflex. Status is Abandoned.', false);
					} elseif ($returnedorderstatus == "Declined"){
							$order_status_id = $this->config->get('payment_payflex_order_status_cancelled');
							$this->model_checkout_order->addOrderHistory($orderidtocheck, $order_status_id, 'Checked payment status with Payflex. Status is Declined or Cancelled.', false);
					} else {
						$order_status_id = $this->config->get('payment_payflex_order_status_failed');
						$this->model_checkout_order->addOrderHistory($orderidtocheck, $order_status_id, 'Checked payment status with Payflex. Status is Failed.', false);
					}
					// Output progress messages.
					echo "<br/>Order " . $orderidtocheck . " has been processed:";
					echo "<br/>Response from API: " . $returnedorderstatus;
					echo "<br/>Order successfully updated to status " .  $order_status_id;
			} else {
				//echo $updated_order_response_body->Errors; Only enable this if debugging.
			}
	}

	public function updateCronJobRunTime() {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = 'payment_payflex' AND `key` = 'payment_payflex_cron_job_last_run'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` (`store_id`, `code`, `key`, `value`, `serialized`) VALUES (0, 'payment_payflex', 'payment_payflex_cron_job_last_run', NOW(), 0)");
	}

}
