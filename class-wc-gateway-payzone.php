<?php
/*
 * Copyright 2015-2016 Payzone
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author Regis Vidal
 */
if (!defined('ABSPATH')) {
  exit();
}
/**
 * PayXpert Standard Payment Gateway
 *
 * Provides a PayXpert Standard Payment Gateway.
 */
include_once ('includes/Connect2PayClient.php');

class WC_Gateway_Payzone extends WC_Payment_Gateway {

  /** @var boolean Whether or not logging is enabled */
  public static $log_enabled = false;

  /** @var WC_Logger Logger instance */
  public static $log = false;

  //payzone Originator ID
  private $originator_id;
  //payzone password
  private $password;
  // payzone url to call the payment page
  private $connect2_url;

  // Merchant notifications settings
  private $merchant_notifications;
  private $merchant_notifications_to;
  private $merchant_notifications_lang;
  
  private $currency_used;
  /**
   * Constructor for the gateway.
   */
  public function __construct() {
    $this->id = 'payzone';
    $this->has_fields = false;
    $this->method_title = __('Payzone', 'payzone');
    $this->method_description = '';

    // Load the settings.
    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables
    $this->title = $this->get_option('title');
    $this->description = $this->get_option('description');
    $this->testmode = 'no';
    $this->order_button_text = $this->get_option('pay_button');
    $this->debug = 'yes' === $this->get_option('debug', 'no');
    $this->originator_id = $this->get_option('originator_id');
    $this->password = $this->get_option('password');
    $this->connect2_url = $this->get_option('connect2_url', 'https://paiement.payzone.ma');
    $this->connect2_url .= (substr($this->connect2_url, -1) == '/' ? '' : '/');
    $this->home_url = is_ssl() ? home_url('/', 'https') : home_url('/'); //set the urls (cancel or return) based on SSL
    $this->relay_response_url = add_query_arg('wc-api', 'WC_Gateway_Payzone', $this->home_url);

    $this->merchant_notifications = $this->get_option('merchant_notifications');
    $this->merchant_notifications_to = $this->get_option('merchant_notifications_to');
    $this->merchant_notifications_lang = $this->get_option('merchant_notifications_lang');
	
    $this->currency_used = $this->get_option('currency_used');

    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

    if ($this->is_iframe_on()) {
      add_action('woocommerce_receipt_payzone', array($this, 'receipt_page'));
    }

    if (!$this->is_valid_for_use()) {
      $this->enabled = 'no';
    } else {
      add_action('woocommerce_api_wc_gateway_payzone', array($this, 'handle_callback'));
    }
  }

  /**
   * Logging method
   *
   * @param string $message
   */
  public static function log($message) {
    if (self::$log_enabled) {
      if (empty(self::$log)) {
        self::$log = new WC_Logger();
      }
      self::$log->add('PayXpert', $message);
    }
  }

  /**
   * get_icon function.
   *
   * @return string
   */
  public function get_icon() {
    $icon_html = '';

    return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
  }

  /**
   * Check if this gateway is enabled and available in the user's country
   *
   * @return bool
   */
  public function is_valid_for_use() {
    // We allow to use the gateway from any where
    return true;
  }

  /**
   * Check if iframe mode is on
   *
   * @return bool
   */
  public function is_iframe_on() {
    // We allow to use the gateway from any where
    if ($this->get_option('iframe_mode') == 'yes') {
      return true;
    }
    return false;
  }

  /**
   * Admin Panel Options
   *
   * @since 1.0.0
   */
  public function admin_options() {
    if ($this->is_valid_for_use()) {
      parent::admin_options();
    } else {
      ?>
<div class="inline error">
 <p>
  <strong><?php _e( 'Gateway Disabled', 'payzone' ); ?></strong>: <?php _e( 'Payzone does not support your store currency / country', 'payzone' ); ?></p>
</div>
<?php
    }
  }

  /**
   * Initialize Gateway Settings Form Fields
   */
  public function init_form_fields() {
    $this->form_fields = array(
        'enabled' => array( /**/
            'title' => __('Enable/Disable', 'payzone'), /**/
            'type' => 'checkbox', /**/
            'label' => __('Enable Payzone payment gateway', 'payzone'), /**/
            'default' => 'yes' /**/
        ),
        'originator_id' => array(/**/
            'title' => __('Originator ID', 'payzone'), /**/
            'type' => 'text', /**/
            'description' => __('The identifier of your Originator', 'payzone'), /**/
            'default' => '' /**/
        ),
        'password' => array(/**/
            'title' => __('Password', 'payzone'), /**/
            'type' => 'text', /**/
            'description' => __('The password associated with your Originator', 'payzone'), /**/
            'default' => '' /**/
        ),
        'merchant_notifications' => array( /**/
            'title' => __('Merchant Notifications', 'payzone'), /**/
            'type' => 'select', /**/
            'class' => 'wc-enhanced-select', /**/
            'description' => __('Determine if you want or not merchant notifications after each payment attempt', 'payzone'), /**/
            'default' => 'default', /**/
            'options' => array(/**/
                'default' => __('Default value for the account', 'payzone'), /**/
                'enabled' => __('Enabled', 'payzone'), /**/
                'disabled' => __('Disabled', 'payzone') /**/
            ) /**/
        ),
        'merchant_notifications_to' => array(/**/
            'title' => __('Merchant email notifications recipient', 'payzone'), /**/
            'type' => 'text', /**/
            'description' => __('The email address that will receive merchant notifications', 'payzone'), /**/
            'default' => '' /**/
        ),
        'merchant_notifications_lang' => array( /**/
            'title' => __('Merchant email notifications language', 'payzone'), /**/
            'type' => 'select', /**/
            'class' => 'wc-enhanced-select', /**/
            'description' => __('The language that will be used for merchant notifications', 'payzone'), /**/
            'default' => 'default', /**/
            'options' => array(/**/
                'en' => __('English', 'payzone'), /**/
                'fr' => __('French', 'payzone'), /**/
                'es' => __('Spanish', 'payzone'), /**/
                'it' => __('Italian', 'payzone'), /**/
                'de' => __('German', 'payzone'), /**/
                'pl' => __('Polish', 'payzone'), /**/
                'zh' => __('Chinese', 'payzone'), /**/
                'ja' => __('Japanese', 'payzone') /**/
            ) /**/
        ),
        'title' => array(/**/
            'title' => __('Title', 'payzone'), /**/
            'type' => 'text', /**/
            'description' => __('This controls the title the user sees during checkout.', 'payzone'), /**/
            'default' => __('Credit Card Payment via payzone', 'payzone'), /**/
            'desc_tip' => true /**/
        ),
        'pay_button' => array(/**/
            'title' => __('Pay Button', 'payzone'), /**/
            'type' => 'text', /**/
            'description' => __('"Pay Button" text', 'payzone'), /**/
            'default' => __('Proceed to Payment', 'payzone') /**/
        ),
        'description' => array(/**/
            'title' => __('Description', 'payzone'), /**/
            'type' => 'text', /**/
            'desc_tip' => true, /**/
            'description' => __('This controls the description the user sees during checkout.', 'payzone'), /**/
            'default' => __('Pay via Payzone: you can pay with your credit / debit card', 'payzone') /**/
        ),
        'connect2_url' => array(/**/
            'title' => __('Payment Page URL', 'payzone'), /**/
            'type' => 'text', /**/
            'description' => __('Do not change this field unless you have been given a specific URL', 'payzone') /**/
        ),
		  'currency_used' => array( /**/
            'title' => __('Currency used', 'payzone'), /**/
            'type' => 'select', /**/
            'class' => 'wc-enhanced-select', /**/
            'description' => __('Determine the currency to be used in your web site', 'payzone'), /**/
            //'default' => 'both', /**/
            'options' => array(/**/
               // '0' => __('Only MAD', 'payzone'), /**/
                'devise' => __('only devise', 'payzone'), /**/
                'both' => __('the both', 'payzone') /**/
            ) /**/
        ),
       
    );
  }

  /**
   * Process the payment and return the result
   *
   * @param int $order_id
   * @return array
   */
  public function process_payment($order_id) {
	  global $woocommerce;
    $order = new WC_Order($order_id);

    // init api
    $c2pClient = new Connect2PayClient($this->connect2_url, $this->originator_id, $this->password);
	$amount = ($order->order_total)*100;
	$description = "";
	$currency = get_woocommerce_currency();

		if ( $currency != 'MAD' && $currency != 504 && $currency != '504' ) {
			
		// Rate of exchange

			$taux = Connect2PayCurrencyHelper::getRate($currency, 'MAD', htmlspecialchars_decode($this->originator_id), htmlspecialchars_decode($this->password));
			
			if(empty($taux) OR is_null($taux)){
				$message = "Payzone : Problème de change";
				$this->log($message);
				echo $message;              
			}
			
			if($this->currency_used == 'devise'){
			
			 $description = $amount / 100 . ' '. $currency;
			 $amount = $amount * $taux;
			 
			}else if($this->currency_used == 'both'){
				
			    $description = 'le montant de '.$amount / 100 . ' '. $currency.'	a ete converti en Dirham marocain avec un taux de change de '.$taux;
				$amount = $amount * $taux;
			}
	

		}
			
    // customer informations
    $c2pClient->setShopperID($order->get_customer_id());
    $c2pClient->setShopperEmail($order->get_billing_email());
    $c2pClient->setShopperFirstName(substr($order->get_billing_first_name(), 0, 35));
    $c2pClient->setShopperLastName(substr($order->get_billing_last_name(), 0, 35));
    $c2pClient->setShopperCompany(substr($order->get_billing_company(), 0, 128));
    $c2pClient->setShopperAddress(substr(trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2()), 0, 255));
    $c2pClient->setShopperZipcode(substr($order->get_billing_postcode(), 0, 10));
    $c2pClient->setShopperCity(substr($order->get_billing_city(), 0, 50));
    $c2pClient->setShopperState(substr($order->get_billing_state(), 0, 30));
    $c2pClient->setShopperCountryCode($order->get_billing_country());
    $c2pClient->setShopperPhone(substr(trim($order->get_billing_country()), 0, 20));
    $c2pClient->setShippingType(Connect2PayClient::_SHIPPING_TYPE_VIRTUAL);

    // Shipping information
    if ('yes' == $this->get_option('send_shipping')) {
      $c2pClient->setShipToFirstName(substr($order->get_shipping_first_name(), 0, 35));
      $c2pClient->setShipToLastName(substr($order->get_shipping_last_name(), 0, 35));
      $c2pClient->setShipToCompany(substr($order->get_shipping_company(), 0, 128));

      $c2pClient->setShipToPhone(substr(trim(), 0, 20));

      $c2pClient->setShipToAddress(substr(trim($order->get_shipping_address_1() . " " . $order->get_shipping_address_2()), 0, 255));
      $c2pClient->setShipToZipcode(substr($order->get_shipping_postcode(), 0, 10));
      $c2pClient->setShipToCity(substr($order->get_shipping_city(), 0, 50));
      $c2pClient->setShipToState(substr($order->get_shipping_state(), 0, 30));
      $c2pClient->setShipToCountryCode($order->get_shipping_country());
      $c2pClient->setShippingType(Connect2PayClient::_SHIPPING_TYPE_PHYSICAL);
    }

    // Order informations
    $c2pClient->setOrderID(substr($order->get_id(), 0, 100));
    $c2pClient->setOrderDescription($description);
    $c2pClient->setCurrency('MAD');

    $total = number_format($order->order_total * 100, 0, '.', '');
    $c2pClient->setAmount($amount);
    $c2pClient->setPaymentMode(Connect2PayClient::_PAYMENT_MODE_SINGLE);
    $c2pClient->setPaymentType(Connect2PayClient::_PAYMENT_TYPE_CREDITCARD);

    $c2pClient->setCtrlCallbackURL(WC()->api_request_url('WC_Gateway_Payzone'));
    $c2pClient->setCtrlRedirectURL($this->relay_response_url . '&order_id=' . $order_id);

    // Merchant notifications
    if (isset($this->merchant_notifications) && $this->merchant_notifications != null) {
      if ($this->merchant_notifications == 'enabled') {
        $c2pClient->setMerchantNotification(true);
        $c2pClient->setMerchantNotificationTo($this->merchant_notifications_to);
        $c2pClient->setMerchantNotificationLang($this->merchant_notifications_lang);
      } else if ($this->merchant_notifications == 'disabled') {
        $c2pClient->setMerchantNotification(false);
      }
    }

    // prepare API
    if ($c2pClient->prepareTransaction() == false) {
      $message = "can't prepare transaction - " . $c2pClient->getClientErrorMessage();
      $this->log($message);
      echo $message;
      return array('result' => 'fail', 'redirect' => '');
    }
    
    // Save the merchant token for callback verification
    update_post_meta($order_id, '_payzone_merchant_token', $c2pClient->getMerchantToken());
    update_post_meta($order_id, '_payzone_customer_url', $c2pClient->getCustomerRedirectURL());

    $url = $c2pClient->getCustomerRedirectURL();

    if($this->is_iframe_on()) $url = $order->get_checkout_payment_url(true);

    return array('result' => 'success', 'redirect' => $url);
  }

  /**
   * Can the order be refunded via PayPal?
   *
   * @param WC_Order $order
   * @return bool
   */
  public function can_refund_order($order) {
    return $order && $order->get_transaction_id();
  }

  /**
   * Process a refund if supported
   *
   * @param int $order_id
   * @param float $amount
   * @param string $reason
   * @return boolean True or false based on success, or a WP_Error object
   */
  public function process_refund($order_id, $amount = null, $reason = '') {
    $order = wc_get_order($order_id);

    if (!$this->can_refund_order($order)) {
      $this->log('Refund Failed: No transaction ID');
      return false;
    }

    $transactionId = $order->get_transaction_id();

    include_once ('includes/GatewayClient.php');

    $client = new GatewayClient($this->api_url, $this->originator_id, $this->password);

    $transaction = $client->newTransaction('Refund');
    if ($amount <= 0) {
      $amount = $order->order_total;
    }

    $total = number_format($amount * 100, 0, '.', '');

    $transaction->setReferralInformation($transactionId, $total);

    $response = $transaction->send();

    if ('000' === $response->errorCode) {
      $this->log("Refund Successful: Transaction ID {$response->transactionID}");
      $order->add_order_note(sprintf(__('Refunded %s - Refund ID: %s', 'payzone'), $amount, $response->transactionID));
      return true;
    } else {
      $this->log(
          "Refund Failed: Transaction ID {$response->transactionID}, Error {$response->errorCode} with message {$response->errorMessage}");
      return false;
    }
  }

  /**
   * Complete order, add transaction ID and note
   *
   * @param WC_Order $order
   * @param string $txn_id
   * @param string $note
   */
  protected function payment_complete($order, $txn_id = '', $note = '') {
    $order->add_order_note($note);
    $order->payment_complete($txn_id);
  }

  /**
   * Check for PayXpert Callback Response
   */
  public function handle_callback() {

    $c2pClient = new Connect2PayClient($this->connect2_url, $this->originator_id, $this->password);

    if ($_POST["data"] != null) {

      $data = $_POST["data"];
      $order_id = $_GET['order_id'];
      $merchantToken = get_post_meta($order_id, '_payzone_merchant_token', true);

      // Setup the client and decrypt the redirect Status
      if ($c2pClient->handleRedirectStatus($data, $merchantToken)) {
        // Get the PaymentStatus object
        $status = $c2pClient->getStatus();

        $errorCode = $status->getErrorCode();
        $merchantData = $status->getCtrlCustomData();
        $order = wc_get_order($order_id);

        // errorCode = 000 => payment is successful
        if ($errorCode == '000') {
          $transactionId = $status->getTransactionID();
          $message = "Successful transaction by customer redirection. Transaction Id: " . $transactionId;
          $this->payment_complete($order, $transactionId, $message, 'payzone');
          $order->update_status('completed', $message);
          $this->log($message);
          $this->redirect_to($order->get_checkout_order_received_url());
        } else if ($errorCode == '-1'){
          $message = "Unsuccessful transaction, customer left payment flow. Retrieved data: " . print_r($data, true);
          $this->log($message);
          $this->redirect_to(wc_get_checkout_url());
          wc_add_notice(__('Payment not complete, please try again', 'payzone'), 'notice');
        } else {
          wc_add_notice(__('Payment not complete: ' . $status->getErrorMessage(), 'payzone'), 'error');
          $this->redirect_to(wc_get_checkout_url());
        }
      }
    } else {

      if ($c2pClient->handleCallbackStatus()) {

        $status = $c2pClient->getStatus();

        // get the Error code
        $errorCode = $status->getErrorCode();
        $errorMessage = $status->getErrorMessage();
        $transactionId = $status->getTransactionID();

        $order_id = $status->getOrderID();

        $order = wc_get_order($order_id);
        $merchantToken = $status->getMerchantToken();

        $amount = number_format($status->getAmount() / 100, 2, '.', '');

        $data = compact("errorCode", "errorMessage", "transactionId", "invoiceId", "amount");

        $payxpert_merchant_token = get_post_meta($order_id, '_payzone_merchant_token', true);

        // Be sure we have the same merchant token
        if ($payxpert_merchant_token == $merchantToken) {
          // errorCode = 000 transaction is successfull
          if ($errorCode == '000') {

            $message = "Successful transaction Callback received with transaction Id: " . $transactionId;
            $this->payment_complete($order, $transactionId, $message, 'payzone');
            $order->update_status('completed', $message);
            $this->log($message);
          } else {

            $message = "Unsuccessful transaction Callback received with the following information: " . print_r($data, true);
            $order->add_order_note($message);
			$order->update_status('failed', $message);
            $this->log($message);
          }
        } else {
          // We do not update the status of the transaction, we just log the
          // message
          $message = "Error. Invalid token " . $merchantToken . " for order " . $order->id . " in callback from " . $_SERVER["REMOTE_ADDR"];
          $order->update_status('failed', $message);
		  $this->log($message);
        }

        // Send a response to mark this transaction as notified
        $response = array("status" => "OK", "message" => "Status recorded");
        header("Content-type: application/json");
        echo json_encode($response);
        exit();
      } else {

        $this->log("Error: Callback received an incorrect status from " . $_SERVER["REMOTE_ADDR"]);
        wp_die("Payzone Callback Failed", "Payzone", array('response' => 500));
      }
    }    
  }

  public function receipt_page($order_id) {

      //define the url
      $payzone_customer_url = get_post_meta($order_id, '_payzone_customer_url', true);

      //display the form
      ?>
      <iframe id="payzone_for_woocommerce_iframe" src="<?php echo $payzone_customer_url; ?>" width="100%" height="700" scrolling="no" frameborder="0" border="0" allowtransparency="true"></iframe>

      <?php
  }

  public function redirect_to($redirect_url) {
      // Clean
      @ob_clean();

      // Header
      header('HTTP/1.1 200 OK');

      echo "<script>window.parent.location.href='" . $redirect_url . "';</script>";
      
      exit;
  }
}
