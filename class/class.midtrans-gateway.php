<?php
  /**
   * ### Midtrans Payment Plugin for Wordrpress-WooCommerce ###
   *
   * This plugin allow your Wordrpress-WooCommerce to accept payment from customer using Midtrans Payment Gateway solution.
   *
   * @category   Wordrpress-WooCommerce Payment Plugin
   * @author     Rizda Dwi Prasetya <rizda.prasetya@midtrans.com>
   * @link       http://docs.midtrans.com
   * (This plugin is made based on Payment Plugin Template by WooCommerce)
   *
   * LICENSE: This program is free software; you can redistribute it and/or
   * modify it under the terms of the GNU General Public License
   * as published by the Free Software Foundation; either version 2
   * of the License, or (at your option) any later version.
   * 
   * This program is distributed in the hope that it will be useful,
   * but WITHOUT ANY WARRANTY; without even the implied warranty of
   * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   * GNU General Public License for more details.
   * 
   * You should have received a copy of the GNU General Public License
   * along with this program; if not, write to the Free Software
   * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
   */

    /**
     * Midtrans Payment Gateway Class
     */
    class WC_Gateway_Midtrans extends WC_Gateway_Midtrans_Abstract {

      /**
       * Constructor
       */
      function __construct() {
        /**
         * Fetch config option field values and set it as private variables
         */
        $this->id           = 'midtrans';
        $this->method_title = __( $this->pluginTitle(), 'woocommerce' );
        $this->has_fields   = true;
        $this->notify_url   = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_Midtrans', home_url( '/' ) ) );

        parent::__construct();
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
        // Hook for displaying payment page HTML on receipt page
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
      }

      /**
       * Admin Panel Options
       * HTML that will be displayed on Admin Panel
       * @access public
       * @return void
       */
      public function admin_options() { ?>
        <h3><?php _e( $this->pluginTitle(), 'woocommerce' ); ?></h3>
        <p><?php _e('Allows payments using Midtrans.', 'midtrans-woocommerce' ); ?></p>
        <table class="form-table">
          <?php
            // Generate the HTML For the settings form. generated from `init_form_fields`
            $this->generate_settings_html();
          ?>
        </table><!--/.form-table-->
        <?php
      }

      /**
       * Initialise Gateway Settings Form Fields
       * Method ini digunakan untuk mengatur halaman konfigurasi admin
       */
      function init_form_fields() {
        /**
         * Build array of configurations that will be displayed on Admin Panel
         */
        parent::init_form_fields();
      }

      /**
       * This function auto-triggered by WC when payment process initiated
       * Serves as WC payment entry point
       * @param  [String] $order_id auto generated by WC
       * @return [array] contains redirect_url of payment for customer
       */
      function process_payment( $order_id ) {
        global $woocommerce;
        
        // Create the order object
        $order = new WC_Order( $order_id );
        // Get response object template
        $successResponse = $this->getResponseTemplate( $order );
        // Get data for charge to midtrans API
        $params = $this->getPaymentRequestData( $order_id );

        // Empty the cart because payment is initiated.
        $woocommerce->cart->empty_cart();
        $this->setLogRequest( print_r( $params, true) );
        try {
          $snapResponse = WC_Midtrans_API::createSnapTransaction( $params );
        } catch (Exception $e) {
            $this->setLogError( $e->getMessage() );
            WC_Midtrans_Utils::json_print_exception( $e, $this );
          exit();
        }

        // If `enable_redirect` admin config used, snap redirect
        if(property_exists($this,'enable_redirect') && $this->enable_redirect == 'yes'){
          $redirectUrl = $snapResponse->redirect_url;
        }else{
          $redirectUrl = $order->get_checkout_payment_url( true )."&snap_token=".$snapResponse->token;
        }

        // Store snap token & snap redirect url to $order metadata
        $order->update_meta_data('_mt_payment_snap_token',$snapResponse->token);
        $order->update_meta_data('_mt_payment_url',$snapResponse->redirect_url);
        $order->save();

        if(property_exists($this,'enable_immediate_reduce_stock') && $this->enable_immediate_reduce_stock == 'yes'){
          // Reduce item stock on WC, item also auto reduced on order `pending` status changes
          wc_reduce_stock_levels($order);
        }

        $successResponse['redirect'] = $redirectUrl;
        return $successResponse;
      }

      /**
       * Hook function that will be called on receipt page
       * Output HTML for Snap payment page. Including `snap.pay()` part
       * @param  [String] $order_id generated by WC
       * @return [String] HTML
       */
      function receipt_page( $order_id ) {
        global $woocommerce;
        $pluginName = 'fullpayment';
        // Separated as Shared PHP included by multiple class
        require_once(dirname(__FILE__) . '/payment-page.php'); 

      }
      
      /**
       * @return string
       */
      public function pluginTitle() {
        return "Midtrans";
      }

      /**
       * @return string
       */
      protected function getDefaultTitle () {
        return __('Online Payment via Midtrans', 'midtrans-woocommerce');
      }

      /**
       * @return string
       */
      protected function getDefaultDescription () {
        return __('Online Payment via Midtrans' . $this->get_option( 'min_amount' ) . '</br> You will be redirected to fullpayment page if the transaction amount below this value', 'midtrans-woocommerce');
      }

    }
