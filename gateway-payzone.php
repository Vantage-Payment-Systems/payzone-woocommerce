<?php
/*
 * Plugin Name: WooCommerce Payzone Gateway
 * Plugin URI: http://www.payzone.ma
 * Description: WooCommerce Payzone Gateway plugin
 * Version: 1.1.2
 * Author: Payzone
 * Author URI: http://www.vpscorp.ma
 */
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
add_action('plugins_loaded', 'woocommerce_payzonet_init', 0);

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function woocommerce_payzonet_init() {
  if (!class_exists('WC_Payment_Gateway')) {
    return;
  }

  require_once (plugin_basename('class-wc-gateway-payzone.php'));

  add_filter('woocommerce_payment_gateways', 'woocommerce_payzone_add_gateway');
}

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 */
function woocommerce_payzone_add_gateway($methods) {
  $methods[] = 'WC_Gateway_payzone';
  return $methods;
}
