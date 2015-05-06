<?php
 class Model
{
     public $code;
     public $title;
     public $description;
     public $enabled;
     private $_instance;

     public function __construct()
     {
         $this->signature       = 'checkoutapipayment';
         $this->api_version     = '2014-12-05';
         $this->code            = 'checkoutapipayment';
         $instance              = $this->getInstance();
         $this->title           = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TEXT_TITLE;
         $this->public_title    = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TEXT_PUBLIC_TITLE;
         $this->description     = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TEXT_DESCRIPTION;
         $this->sort_order      = defined('MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SORT_ORDER') ? MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SORT_ORDER : 0;
         $this->enabled         = $instance->getEnabled();
         $this->order_status    = defined('MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ORDER_STATUS_ID') && ((int)MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ORDER_STATUS_ID > 0) ? (int)MODULE_PAYMENT_CHECKOUTAPIPAYMENT_ORDER_STATUS_ID : 0;

         if ( MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER == 'Test' ) {
             $this->title .= ' [Test]';
             $this->public_title .= ' (' . $this->code . '; Test)';
         }

         $this->update_status();
     }

     public function getInstance()
     {
        if(!$this->_instance) {

            switch(MODULE_PAYMENT_CHECKOUAPIPAYMENT_TYPE) {
                case 'True':
                    $this->_instance = new model_methods_creditcardpci();
                break;
                default :
                    $this->_instance =  new model_methods_creditcard();

                    break;
            }
        }

         return $this->_instance;
     }

     public function check()
     {
       return  $this->getInstance()->check();
     }

     public function remove()
     {
         return  $this->getInstance()->remove();
     }

     public function keys()
     {
         return  $this->getInstance()->keys();
     }

     public function update_status()
     {
        $this->getInstance()->update_status();
     }

     public function install($parameter = null)
     {
         $this->getInstance()->install($parameter);
     }

     public function javascript_validation()
     {
         return $this->getInstance()->javascript_validation();
     }

     public function selection()
     {
         return array_merge_recursive(
                                        array(
                                             'id'     => $this->code,
                                             'module' => $this->public_title
                                        ),

            $this->getInstance()->selection()
         );
     }

     public function pre_confirmation_check()
     {
         $this->getInstance()->pre_confirmation_check();
     }

     public function confirmation()
     {
         return  $this->getInstance()->confirmation();
     }

     public function process_button()
     {
         $this->getInstance()->process_button();
     }

     public function before_process()
     {
         $this->getInstance()->before_process();
     }

     public function after_process()
     {
         $this->getInstance()->after_process();
     }

     public function get_error()
     {

     }


 }