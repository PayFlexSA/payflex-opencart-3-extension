<?php
class ControllerExtensionPaymentpayflex extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/payment/payflex');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->validate())) {
			$this->model_setting_setting->editSetting('payment_payflex', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

		if($this->config->get('config_ssl')) {
			$data['text_payflex'] = sprintf($this->language->get('text_payflex'), HTTPS_CATALOG);
		} else {
			$data['text_payflex'] = sprintf($this->language->get('text_payflex'), HTTP_CATALOG);
		}

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['pf_store_url'] = HTTPS_CATALOG;

		//////////////////////////////////////////////////////////////////////
		// Error copying
		if (isset($this->error['username'])) {
			$data['error_username'] = $this->error['username'];
		} else {
			$data['error_username'] = '';
		}

		if (isset($this->error['password'])) {
			$data['error_password'] = $this->error['password'];
		} else {
			$data['error_password'] = '';
		}

		if (isset($this->error['reference'])) {
			$data['error_reference'] = $this->error['reference'];
		} else {
			$data['error_reference'] = '';
		}

	  //////////////////////////////////////////////////////////////////////
		// Breadcrumb
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], 'SSL')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_payment'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('payment/payflex', 'user_token=' . $this->session->data['user_token'], 'SSL')
		);

		//////////////////////////////////////////////////////////////////////
		// Actions?
		$data['action'] = $this->url->link('extension/payment/payflex', 'user_token=' . $this->session->data['user_token'], 'SSL');
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

		//////////////////////////////////////////////////////////////////////
		// Page Data
        
        if (isset($this->request->post['payment_payflex_sort_order'])) {
			$data['payment_payflex_sort_order'] = $this->request->post['payment_payflex_sort_order'];
		} else {
			$data['payment_payflex_sort_order'] = $this->config->get('payment_payflex_sort_order');
		}

		if (isset($this->request->post['payment_payflex_test'])) {
			$data['payment_payflex_test'] = $this->request->post['payment_payflex_test'];
		} else {
			$data['payment_payflex_test'] = $this->config->get('payment_payflex_test');
		}

		if (isset($this->request->post['payment_payflex_order_status_id'])) {
			$data['payment_payflex_order_status_id'] = $this->request->post['payment_payflex_order_status_id'];
		} else {
			$data['payment_payflex_order_status_id'] = $this->config->get('payment_payflex_order_status_id');
		}

		if (isset($this->request->post['payment_payflex_order_status_refunded_id'])) {
			$data['payment_payflex_order_status_refunded_id'] = $this->request->post['payment_payflex_order_status_refunded_id'];
		} else {
			$data['payment_payflex_order_status_refunded_id'] = $this->config->get('payment_payflex_order_status_refunded_id');
		}

		if (isset($this->request->post['payment_payflex_order_status_auth_id'])) {
			$data['payment_payflex_order_status_auth_id'] = $this->request->post['payment_payflex_order_status_auth_id'];
		} else {
			$data['payment_payflex_order_status_auth_id'] = $this->config->get('payment_payflex_order_status_auth_id');
		}

		if (isset($this->request->post['payment_payflex_username'])) {
			$data['payment_payflex_username'] = $this->request->post['payment_payflex_username'];
		} else {
			$data['payment_payflex_username'] = $this->config->get('payment_payflex_username');
		}

		if (isset($this->request->post['payment_payflex_password'])) {
			$data['payment_payflex_password'] = $this->request->post['payment_payflex_password'];
		} else {
			$data['payment_payflex_password'] = $this->config->get('payment_payflex_password');
		}

		if (isset($this->request->post['payment_payflex_order_status_success'])) {
			$data['payment_payflex_order_status_success'] = $this->request->post['payment_payflex_order_status_success'];
		} else {
			$data['payment_payflex_order_status_success'] = $this->config->get('payment_payflex_order_status_success');
		}

		if (isset($this->request->post['payment_payflex_order_status_pending'])) {
		  $data['payment_payflex_order_status_pending'] = $this->request->post['payment_payflex_order_status_pending'];
		} else {
		  $data['payment_payflex_order_status_pending'] = $this->config->get('payment_payflex_order_status_pending');
		}

		if (isset($this->request->post['payment_payflex_order_status_expired'])) {
		  $data['payment_payflex_order_status_expired'] = $this->request->post['payment_payflex_order_status_expired'];
		} else {
		  $data['payment_payflex_order_status_expired'] = $this->config->get('payment_payflex_order_status_expired');
		}

		if (isset($this->request->post['payment_payflex_order_status_cancelled'])) {
		  $data['payment_payflex_order_status_cancelled'] = $this->request->post['payment_payflex_order_status_cancelled'];
		} else {
		  $data['payment_payflex_order_status_cancelled'] = $this->config->get('payment_payflex_order_status_cancelled');
		}

		if (isset($this->request->post['payment_payflex_order_status_failed'])) {
		  $data['payment_payflex_order_status_failed'] = $this->request->post['payment_payflex_order_status_failed'];
		} else {
		  $data['payment_payflex_order_status_failed'] = $this->config->get('payment_payflex_order_status_failed');
		}

		// Cron Job Information Starts

		if (isset($this->request->post['payment_payflex_cron_job_token'])) {
		  $data['payment_payflex_cron_job_token'] = $this->request->post['payment_payflex_cron_job_token'];
		} elseif ($this->config->get('payment_payflex_cron_job_token')) {
		  $data['payment_payflex_cron_job_token'] = $this->config->get('payment_payflex_cron_job_token');
		} else {
		  $data['payment_payflex_cron_job_token'] = sha1(uniqid(mt_rand(), 1));
		}

		$data['payment_payflex_cron_job_url'] = HTTPS_CATALOG . 'index.php?route=extension/payment/payflex/cron&token=' . $data['payment_payflex_cron_job_token'];

		if ($this->config->get('payment_payflex_cron_job_last_run')) {
		  $data['payment_payflex_cron_job_last_run'] = $this->config->get('payment_payflex_cron_job_last_run');
		} else {
		  $data['payment_payflex_cron_job_last_run'] = '';
		}


		// Cron Job Information Ends

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		/*if (isset($this->request->post['payment_payflex_reference'])) {
			$data['payment_payflex_reference'] = $this->request->post['payment_payflex_reference'];
		} else {
			$data['payment_payflex_reference'] = $this->config->get('payment_payflex_reference');
		}*/

		if (isset($this->request->post['payment_payflex_status'])) {
			$data['payment_payflex_status'] = $this->request->post['payment_payflex_status'];
		} else {
			$data['payment_payflex_status'] = $this->config->get('payment_payflex_status');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/payflex', $data));
	}

	public function install() {
		$this->load->model('extension/payment/payflex');
		$this->model_extension_payment_payflex->install();
	}

	public function uninstall() {
		$this->load->model('extension/payment/payflex');
		$this->model_extension_payment_payflex->uninstall();
	}

	public function refund() {
	}

	private function validate() {

		if (!$this->user->hasPermission('modify', 'extension/payment/payflex')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['payment_payflex_username']) {
			$this->error['username'] = $this->language->get('error_username');
		}
		if (!$this->request->post['payment_payflex_password']) {
			$this->error['password'] = $this->language->get('error_password');
		}
		/*if (!$this->request->post['payment_payflex_reference']) {
			$this->error['reference'] = $this->language->get('error_reference');
		}*/

		return !$this->error;
	}
}
