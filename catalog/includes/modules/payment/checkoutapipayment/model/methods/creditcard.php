<?php
class model_methods_creditcard extends model_methods_Abstract
{
    public function pre_confirmation_check()
    {

    }

    public function confirmation()
    {
        return $this->confirmationField();
    }

    public function before_process()
    {
        global  $HTTP_POST_VARS,$order;
        $config = parent::before_process();
        $this->_placeorder($config);
    }

    public function  process_button()
    {
        global $order,$messageStack;
        $paymentToken =  $_POST['cko-paymentToken'];

        if(!$paymentToken) {

            $messageStack->add_session ( 'checkout_payment' , 'Please try again there was an issue your payment details' ,
                'error' );
            if ( !isset( $_GET[ 'error' ] ) ) {
                tep_redirect(tep_href_link ( FILENAME_CHECKOUT_PAYMENT , 'error=true' , 'SSL' , true , false ) );
            }
        }

        $process_button_string = '<input type="hidden" name="cko_paymentToken" value = "'.$paymentToken.'">';

        return $process_button_string;
    }

    private function getPaymentToken()
    {
        global  $order,  $_POST,$messageStack;
        $config = array();
        $Api = CheckoutApi_Api::getApi(array('mode'=> MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER));
        $amount = (int)$order->info['total'];
        $amountCents = $amount *100;
        $config['authorization'] = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY;
        $config['mode'] = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER;

        $products = array();
        $i = 1;
        foreach($order->products as $product) {

            $products[] = array (
                'name'       =>    $product['name'],
                'sku'        =>    $product['id'],
                'price'      =>    $product['final_price'],
                'quantity'   =>     $product['qty'],
            );
            $i++;
        }

        $billingAddress = array (
            'addressLine1'    => $order->billing['street_address'],
            'addressLine2'    => $order->billing['address_line_2'],
            'postcode'        => $order->billing['postcode'],
            'country'         => $order->billing['country']['title'],
            'city'            => $order->billing['city'],
            'state'           => $order->billing['state'],
           // 'phone'           => $order->customer['telephone']
        );

        $config['postedParam'] = array (
            'email'           =>    $order->customer['email_address'] ,
            'name'            =>    "{$order->customer['firstname']} {$order->customer['lastname']}",
            'value'           =>    $amountCents,
            'currency'        =>    $order->info['currency'] ,
            'products'        =>    $products,
            'shippingDetails' =>
                array (
                    'addressLine1'    =>    $order->delivery['street_address'],
                    'addressLine2'    =>    $order->delivery['address_line_2'],
                    'Postcode'        =>    $order->delivery['postcode'],
                    'Country'         =>    $order->delivery['country']['title'],
                    'City'            =>    $order->delivery['city'],
                  //  'Phone'           =>    array ('number' => $order->customer['telephone']),
                    'recipientname'   =>    $order->delivery['firstname'].' '.$order->delivery['lastname']
                )
        );

        if (MODULE_PAYMENT_CHECKOUAPIPAYMENT_AUTOCAPTIME == 'Authorize and Capture') {
            $config = array_merge_recursive ( $this->_captureConfig(),$config);
        } else {
            $config = array_merge_recursive ( $this->_authorizeConfig(),$config);
        }

        $paymentTokenCharge = $Api->getPaymentToken($config);
        $paymentToken    =   '';

        if($paymentTokenCharge->isValid()){
            $paymentToken = $paymentTokenCharge->getId();
        }

        if(!$paymentToken) {
            $error_message = $paymentTokenCharge->getExceptionState()->getErrorMessage().
                ' ( '.$paymentTokenCharge->getEventId().')';

            $messageStack->add_session('checkout_payment', $error_message . '<!-- ['.$this->code.'] -->', 'error');
            if(!isset($_GET['payment_error'])) {
                //  tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' .
                //      $paymentTokenCharge->getErrorCode(), 'SSL'));
            }
        }
        return $paymentToken;
    }

    public function confirmationField()
    {
        global $order;
        $amount = (int)$order->info['total'];
        $amountCents = $amount *100;
        $email = $order->customer['email_address'];
        $currency = $order->info['currency'];
        $publicKey = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_PUBLISHABLE_KEY;
        $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';
        $paymentToken =  $this->getPaymentToken();

        $contentArray = array();
        $contentArray[] ='<div class="widget-container"></div>';
        $contentArray[] ='<input type="hidden" name="cko-paymentToken" id="cko-paymentToken" value="'.$paymentToken.'" />';
        $contentArray[] ='<script type="text/javascript" > ';
        $contentArray[] ='var reload = false; ';
        $contentArray[] ='window.CKOConfig = {';
        $contentArray[] ='namespace: "CheckoutIntegration",';
        $contentArray[] ='debugMode: false,';
        $contentArray[] ='publicKey: "'.$publicKey.'",';
        $contentArray[] ='customerEmail: "'.$email.'",';
        $contentArray[] ='renderMode: 2,';
        $contentArray[] ='customerName: "'.$order->customer['firstname'].' '.$order->customer['lastname'].'",';
        $contentArray[] ='paymentToken: "'.$paymentToken.'",';
        $contentArray[] ='value: "'.$amountCents.'",';
        $contentArray[] ='currency: "'.$currency.'",';
        $contentArray[] ='paymentTokenExpired: function(){  reload = true; },';
        $contentArray[] ='cardCharged: function (event) { document.getElementById(\'cko-paymentToken\').value = event.data.paymentToken; reload = false; jQuery(\'[name^=checkout]\').trigger(\'submit\'); },';
        $contentArray[] ='lightboxDeactivated: function() { if(reload) { window.location.reload(); } },';
        $contentArray[] ='ready: function(){if(!jQuery(\'#checkoutButton\').hasClass(\'cko-click-bind\')){jQuery(\'#checkoutButton\').click(function(event){if(jQuery(\'[name^=payment]:checked\').val()==\'checkoutapipayment\') {event.preventDefault();CheckoutIntegration.open();}});}jQuery(\'#checkoutButton\').addClass(\'cko-click-bind\');jQuery(\'body\').append(jQuery(\'.widget-container link\'));},';
        $contentArray[] ='widgetContainerSelector: ".widget-container" };';
        $contentArray[] ='if(typeof Checkout !="undefined"){if(jQuery(\'#cko-iframe-id\').length<1) {Checkout.render(window.CKOConfig);}}else if(typeof CheckoutIntegration !="undefined"){if(jQuery(\'#cko-iframe-id\').length<1) {CheckoutIntegration.render(window.CKOConfig);} }';
        $contentArray[] ='</script>';
        $contentArray[] ='<script type="text/javascript" >if (!document.getElementById("cko-checkoutjs") ) {var script = document.createElement("script");script.type = "text/javascript";script.src = "//checkout.com/cdn/js/Checkout.js";script.id = "cko-checkoutjs";script.async = "true";document.getElementsByTagName("head")[0].appendChild(script);}</script>';

        $selection = array(
                        'fields' => array (
                            array(

                                'field' => implode('',$contentArray)
                            )

                        )
                     );

        return $selection;
    }

    protected function _createCharge($config)
    {
        $Api = CheckoutApi_Api::getApi(array('mode'=> MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER));
        $config['paymentToken'] = $_POST['cko-paymentToken'];

        return $Api->verifyChargePaymentToken($config);
    }
}