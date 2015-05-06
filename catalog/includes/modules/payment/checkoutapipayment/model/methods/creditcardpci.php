<?php
class model_methods_creditcardpci extends model_methods_Abstract
{
    public function confirmation()
    {
        global $order;

        for ($i=1; $i<13; $i++) {
            $expires_month[] = array('id' => sprintf('%02d', $i), 'text' => strftime('%B',mktime(0,0,0,$i,1,2000)));
        }

        $today = getdate();
        for ($i=$today['year']; $i < $today['year']+10; $i++) {
            $expires_year[] = array('id' => strftime('%y',mktime(0,0,0,1,1,$i)), 'text' => strftime('%Y',mktime(0,0,0,1,1,$i)));
        }

        $confirmation = array('fields' => array(
                array(
                    'title' => $this->get_cc_images()
                ),
                array(
                    'title' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_CREDITCARD_OWNER,
                    'field' => tep_draw_input_field('cc_owner', $order->billing['firstname'] . ' ' . $order->billing['lastname'])
                ),
                array(
                    'title' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_CREDITCARD_NUMBER,
                    'field' => tep_draw_input_field('cc_number_nh-dns')
                ),
                array(
                    'title' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_CREDITCARD_EXPIRY,
                    'field' => tep_draw_pull_down_menu('cc_expires_month', $expires_month) . '&nbsp;' . tep_draw_pull_down_menu('cc_expires_year', $expires_year)
                ),
                array(
                    'title' => MODULE_PAYMENT_CHECKOUTAPIPAYMENT_CREDITCARD_CVC,
                    'field' => tep_draw_input_field('cc_cvc_nh-dns', '', 'size="5" maxlength="4"')
                )
            )
        );

        return $confirmation;

    }

    function get_cc_images() {
        $cc_images = '';
        reset($this->allowed_types);
        while (list($key, $value) = each($this->allowed_types)) {
            $cc_images .= tep_image(DIR_WS_ICONS . $key . '.gif', $value);
        }
        return $cc_images;
    }

    public function pre_confirmation_check()
    {

    }
    public function before_process()
    {
        global  $HTTP_POST_VARS,$order;

        $config = parent::before_process();
        $config['postedParam']['card']['phoneNumber']   = $order->customer['telephone'];
        $config['postedParam']['card']['name']          = $HTTP_POST_VARS['cc_owner'];
        $config['postedParam']['card']['number']        = $HTTP_POST_VARS['cc_number_nh-dns'];
        $config['postedParam']['card']['expiryMonth']   = (int)$HTTP_POST_VARS['cc_expires_month'];
        $config['postedParam']['card']['expiryYear']    = (int)$HTTP_POST_VARS['cc_expires_year'];
        $config['postedParam']['card']['cvv']           = $HTTP_POST_VARS['cc_cvc_nh-dns'];
        $config['postedParam']['card']['cvv2']          = $HTTP_POST_VARS['cc_cvc_nh-dns'];

        $this->_placeorder($config);
    }

    public function  process_button()
    {

    }
}