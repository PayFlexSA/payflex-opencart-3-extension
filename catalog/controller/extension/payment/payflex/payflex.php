<?php

class ControllerExtensionPaymentPayflexPayflex extends Controller {

  public function index() {
    $this->log->write('Howdy');
  }

  public function success() {
    // &token=0eca257a-7576-498d-ace4-f3a63f968eb5&orderId=1ca21e26-dfa0-4757-9dc1-b55ba1182afb
    $this->load->model('checkout/order');
    $order_id = $this->session->data['order_id'];
    $order_status_id = $this->config->get('payment_payflex_order_status_success');
    $this->model_checkout_order->addOrderHistory($order_id, $order_status_id, 'PayFlex: ' . $_GET['orderId'], true);
    $this->response->redirect($this->url->link('checkout/success', '', true));
  }

  public function cancel() {
    $this->response->redirect($this->url->link('checkout/checkout', '', true));
  }

  public function status() {

  }

}
