<?php
abstract class model_methods_Abstract {

    public $code;
    public $title;
    public $description;
    public $enabled;
    private $_check;
    private $_currentCharge;


    public function getEnabled()
    {
        return   defined('MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS') &&
        (MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS == 'True') ? true : false;
    }

    public function javascript_validation()
    {
        return false;
    }

    public function selection()
    {
        return array();
    }

    abstract public function pre_confirmation_check();

    abstract public function confirmation();

    public function before_process()
    {
        global $customer_id, $order, $currency, $HTTP_POST_VARS;
        $config = array();

        $amountCents = (int)$this->format_raw($order->info['total']) ;
        $config['authorization'] = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY;
        $config['mode'] = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER;

        $config['postedParam'] = array (
            'email'     =>$order->customer['email_address'] ,
            'value'     =>$amountCents,
            'currency'  => $order->info['currency'] ,
            'card'      => array(
                                'billingDetails' => array (
                                                        'addressLine1' =>  $order->billing['street_address'],
                                                        'postcode'     =>  $order->billing['postcode'],
                                                        'country'      =>  $order->billing['country']['title'],
                                                        'city'         =>  $order->billing['city'],
                                                        'phone'        =>  $order->customer['telephone'],

                                         )
                         )

        );

        if (MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_METHOD == 'Authorize and Capture') {
            $config = array_merge( $this->_captureConfig(),$config);
        } else {
            $config = array_merge( $this->_authorizeConfig(),$config);
        }

        return $config;
    }
    protected function _placeorder($config)
    {
        global $messageStack,$order;
        //building charge
        $respondCharge = $this->_createCharge($config);
        $this->_currentCharge = $respondCharge;

        if( $respondCharge->isValid()) {
            if (preg_match('/^1[0-9]+$/', $respondCharge->getResponseCode())) {
                $order->info['order_status'] = MODULE_PAYMENT_CHECKOUAPIPAYMENT_REVIEW_ORDER_STATUS_ID;
            }

        } else  {

            $messageStack->add_session('header', MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ERROR_TITLE, 'error');
            $messageStack->add_session('header', MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ERROR_GENERAL, 'error');
            $messageStack->add_session('header', $respondCharge->getExceptionState()->getErrorMessage(), 'error');
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $respondCharge->getErrorCode(), 'SSL'));
        }

    }
    protected function _createCharge($config)
    {
        $Api = CheckoutApi_Api::getApi(array('mode'=> MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER));
        return $Api->createCharge($config);
    }
    protected function _captureConfig()
    {
        $to_return['postedParam'] = array (
            'autoCapture' =>( CheckoutApi_Client_Constant::AUTOCAPUTURE_CAPTURE),
            'autoCapTime' => MODULE_PAYMENT_CHECKOUAPIPAYMENT_AUTOCAPTIME
        );
        return $to_return;
    }

    protected function _authorizeConfig()
    {
        $to_return['postedParam'] = array(
            'autoCapture' => ( CheckoutApi_Client_Constant::AUTOCAPUTURE_AUTH),
            'autoCapTime' => 0
        );
        return $to_return;
    }

    public function after_process()
    {
        global $insert_id, $customer_id, $stripe_result, $HTTP_POST_VARS;
        if($this->_currentCharge) {
            $status_comment = array('Transaction ID: ' . $this->_currentCharge->getId(),
                'Transaction has been process using "' . MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TEXT_PUBLIC_TITLE .'" and paid with  card '. $this->_currentCharge->getCard()->getPaymentMethod(),
                'Respond code:' .$this->_currentCharge->getId());

            $sql_data_array = array('orders_id' => $insert_id,
                'orders_status_id' => MODULE_PAYMENT_CHECKOUAPIPAYMENT_REVIEW_ORDER_STATUS_ID,
                'date_added' => 'now()',
                'customer_notified' => '0',
                'comments' => implode("\n", $status_comment));

            tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
        }

        $Api = CheckoutApi_Api::getApi(
            array( 'mode'          => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER,
                   'authorization' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY)
        );

        $chargeUpdated = $Api->updateTrackId($this->_currentCharge,$insert_id);

        $this->_currentCharge  = '';

    }

    public function get_error()
    {

    }

    public function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION .
                " where configuration_key = 'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function keys()
    {
        return array(
                'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS',
                'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_PUBLISHABLE_KEY',
                'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY',
                'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_METHOD',
                'MODULE_PAYMENT_CHECKOUAPIPAYMENT_REVIEW_ORDER_STATUS_ID',
                'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ZONE',
                'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER',
                'MODULE_PAYMENT_CHECKOUAPIPAYMENT_TYPE',
                'MODULE_PAYMENT_CHECKOUAPIPAYMENT_LOCALPAYMENT_ENABLE',
                'MODULE_PAYMENT_CHECKOUAPIPAYMENT_GATEWAY_TIMEOUT',
                'MODULE_PAYMENT_CHECKOUAPIPAYMENT_AUTOCAPTIME',
                'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SORT_ORDER',
        );
    }

    public function remove() 
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function update_status() {
        global $order;

        if ( ($this->getEnabled ()) && ((int)MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ZONE > 0) && ( isset($order) && is_object($order) ) ) {
            $check_flag = false;
            $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_STRIPE_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
            while ($check = tep_db_fetch_array($check_query)) {
                if ($check['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check['zone_id'] == $order->delivery['zone_id']) {
                    $check_flag = true;
                    break;
                }
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    function install($parameter = null) {
        $params = $this->getParams();

        if (isset($parameter)) {
            if (isset($params[$parameter])) {
                $params = array($parameter => $params[$parameter]);
            } else {
                $params = array();
            }
        }

        foreach ($params as $key => $data) {
            $sql_data_array = array(
                                'configuration_title'       => $data['title'],
                                'configuration_key'         => $key,
                                'configuration_value'       => (isset($data['value']) ? $data['value'] : ''),
                                'configuration_description' => $data['desc'],
                                'configuration_group_id'    => '6',
                                'sort_order'                => '0',
                                'date_added'                => 'now()'
            );

            if (isset($data['set_func'])) {
                $sql_data_array['set_function'] = $data['set_func'];
            }

            if (isset($data['use_func'])) {
                $sql_data_array['use_function'] = $data['use_func'];
            }

            tep_db_perform(TABLE_CONFIGURATION, $sql_data_array);
        }
    }

    public function getParams()
    {

        if (!defined('MODULE_PAYMENT_CHECKOUAPIPAYMENT_REVIEW_ORDER_STATUS_ID')) {
            $check_query = tep_db_query("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = 'Checkout.com [Transactions]' limit 1");

            if (tep_db_num_rows($check_query) < 1) {
                $status_query = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
                $status = tep_db_fetch_array($status_query);

                $status_id = $status['status_id']+1;

                $languages = tep_get_languages();

                $webhookStatus = '200';

                foreach ($languages as $lang) {
                    tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name)
                    values ('" . $webhookStatus . "', '" . $lang['id'] . "', 'Checkout.com [Completed]')");
                }

                $flags_query = tep_db_query("describe " . TABLE_ORDERS_STATUS . " public_flag");
                if (tep_db_num_rows($flags_query) == 1) {
                    tep_db_query("update " . TABLE_ORDERS_STATUS . " set public_flag = 0 and downloads_flag = 0 where orders_status_id = '" . $status_id . "'");
                }
            } else {
                $check = tep_db_fetch_array($check_query);

                $status_id = $check['orders_status_id'];
            }
        } else {
            $status_id = MODULE_PAYMENT_CHECKOUAPIPAYMENT_REVIEW_ORDER_STATUS_ID;
        }


        $params = array('MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS' => array('title' => 'Enable Checkout.com Module',
            'desc' => 'Do you want to accept Checkout.com payments?',
            'value' => 'True',
            'set_func' => 'tep_cfg_select_option(array(\'True\', \'False\'), '
            ),
            'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_PUBLISHABLE_KEY' => array('title' => 'Publishable API Key',
                'desc' => 'The Checkout.com account publishable API key to use.',
                'value' => ''
            ),
            'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY' => array('title' => 'Secret API Key',
                'desc' => 'The Checkout.com account secret API key to use .',
                'value' => ''
            ),
            'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_METHOD' => array('title' => 'Transaction Method',
                'desc' => 'The processing method to use for each transaction.',
                'value' => 'Authorize',
                'set_func' => 'tep_cfg_select_option(array(\'Authorize\', \'Authorize and Capture\'), '
            ),
            'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ZONE' => array('title' => 'Payment Zone',
                'desc' => 'If a zone is selected, only enable this payment method for that zone.',
                'value' => '0',
                'use_func' => 'tep_get_zone_class_title',
                'set_func' => 'tep_cfg_pull_down_zone_classes('
            ),
            'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER' => array('title' => 'Transaction Server',
                'desc' => 'Perform transactions on the production server or on the testing server.',
                'value' => 'Preprod',
                'set_func' => 'tep_cfg_select_option(array(\'Live\', \'Preprod\', \'Test\'), '
            ),
            'MODULE_PAYMENT_CHECKOUAPIPAYMENT_TYPE' => array('title' => 'Method Type',
                'desc' => 'Verify gateway server SSL certificate on connection?',
                'value' => 'True',
                'set_func' => 'tep_cfg_select_option(array(\'True\', \'False\'), '
            ),
            'MODULE_PAYMENT_CHECKOUAPIPAYMENT_LOCALPAYMENT_ENABLE' => array('title' => 'Enable localPayment',

                'desc' => 'Enable localpayment using the js.',
                'value' => 'False',
                'set_func' => 'tep_cfg_select_option(array(\'True\', \'False\'), '
            ),
            'MODULE_PAYMENT_CHECKOUAPIPAYMENT_GATEWAY_TIMEOUT' => array('title' => 'Set Gateway timeout.',
                'desc' => 'Set how long request timeout on server.',
                'value' => '60'
            ),
            'MODULE_PAYMENT_CHECKOUAPIPAYMENT_AUTOCAPTIME' => array('title' => 'Set auto capture time.',
                'desc' => 'When transaction is set to authorize and caputure , the gateway will use this time to caputure the transaction.',
                'value' => '0'
            ),
            'MODULE_PAYMENT_CHECKOUAPIPAYMENT_REVIEW_ORDER_STATUS_ID' => array('title' => 'Review Order Status',
                'desc' => 'Set the status of orders flagged as being under review to this value',
                'value' => '0',
                'use_func' => 'tep_get_order_status_name',
                'set_func' => 'tep_cfg_pull_down_order_statuses('
            ),
            'MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SORT_ORDER' => array('title' => 'Sort order of display.',
                'desc' => 'Sort order of display. Lowest is displayed first.',
                'value' => '0')
            );

        return $params;

    }
    function format_raw($number, $currency_code = '', $currency_value = '') {
        global $currencies, $currency;

        if (empty($currency_code) || !$currencies->is_set($currency_code)) {
            $currency_code = $currency;
        }

        if (empty($currency_value) || !is_numeric($currency_value)) {
            $currency_value = $currencies->currencies[$currency_code]['value'];
        }

        return number_format(tep_round($number * $currency_value, $currencies->currencies[$currency_code]['decimal_places']), $currencies->currencies[$currency_code]['decimal_places'], '', '');
    }
}