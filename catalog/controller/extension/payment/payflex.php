<?php
class ControllerExtensionPaymentpayflex extends Controller {

	public function index() {
		$this->load->language('extension/payment/payflex');

		// $data['button_confirm'] = $this->language->get('button_confirm');
		// $data['button_pay'] = $this->language->get('button_pay');

		$this->load->model('checkout/order');
		$order_id = $this->session->data['order_id'];
		$order_info = $this->model_checkout_order->getOrder($order_id);
    /*$order_status_id = 1;
    $this->model_checkout_order->addOrderHistory($order_id, $order_status_id);*/

		$amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);

		$this->log->write($order_info);
		$this->log->write($amount);

		$request = new stdClass();

		$this->load->model('extension/payment/payflex');
		$template = 'payflex';
		$result = $this->model_extension_payment_payflex->getCheckoutUrl($order_info, $this->cart);

		if($result['result'] !== 'fail') {
			$data['redirectUrl'] = $result['redirect'];
		} else {
			$data['errors'] = $result['errors'];
		}

		return $this->load->view('extension/payment/' . $template, $data);
	}

	public function callback() {
		$this->load->language('extension/payment/payflex');
	}

	// Cron funciton starts. This is to catch any transactions that were succesful on the PayFlex system but didn't get updated within the shopping cart.
	public function cron() {
		if (isset($this->request->get['token']) && $this->config->get('payment_payflex_cron_job_token') == $this->request->get['token'] && $this->config->get('payment_payflex_status') == 1) {
			$this->load->model('extension/payment/payflex');
			// Load main order model so it can be used to query orders.
			$this->load->model('checkout/order');

			echo "The PayFlex cron has been initialised.<br/>";

			$unprocessedorders = $this->model_extension_payment_payflex->getOrders();

			foreach ($unprocessedorders as $unprocessedorder){
				$ordertoprocess = $this->model_checkout_order->getOrder($unprocessedorder["order_id"]);
				$ordertoprocess_payflex_orderid = $this->model_extension_payment_payflex->cronUpdateOrders($ordertoprocess["payment_custom_field"]["orderId"],$unprocessedorder["order_id"],$ordertoprocess["order_status_id"]);
			}

			echo "<br/><br/>The PayFlex cron has completed. Have a nice day. :)";

			$this->model_extension_payment_payflex->updateCronJobRunTime();  // added and working/
		}
	}
}
