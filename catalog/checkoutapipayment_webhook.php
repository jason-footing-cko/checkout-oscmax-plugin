<?php
require('includes/application_top.php');
if (defined('MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS') && MODULE_PAYMENT_CHECKOUTAPIPAYMENT_STATUS == 'True') {

	function _process ()
	{
		$config['chargeId'] = $_GET['chargeId'];
		$config['authorization'] = MODULE_PAYMENT_CHECKOUTAPIPAYMENT_SECRET_KEY;
		$Api = CheckoutApi_Api::getApi(array('mode'=>MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER));
		$respondBody    =    $Api->getCharge($config);
		$json = $respondBody->getRawOutput();
		return $json;
	}


	require(DIR_WS_CLASSES . 'payment.php');

	$checkoutapipayment_module = 'checkoutapipayment';

	$payment_modules = new payment( $checkoutapipayment_module );
	if(isset($_GET['chargeId'])) {
		$stringCharge = _process();
	}else {
		$stringCharge = file_get_contents ( "php://input" );
	}

	if($stringCharge) {
		$Api    =    CheckoutApi_Api::getApi(array('mode'=>MODULE_PAYMENT_CHECKOUTAPIPAYMENT_TRANSACTION_SERVER));
		$objectCharge = $Api->chargeToObj($stringCharge);

		$orderId = $objectCharge->getTrackId();

		if($orderId) {
			$order_updated = false;
			$check_status_query = tep_db_query("select orders_status from " . TABLE_ORDERS . " where orders_id = '" . (int)$orderId . "'");
			$check_status = tep_db_fetch_array($check_status_query);

			if($check_status) {
				if($objectCharge->getCaptured()) {

					if($check_status['orders_status'] !=200) {
						echo "Order has #$orderId was  set complete";

						$sql = "UPDATE " . TABLE_ORDERS  . "
		                  SET orders_status = " . (int)200 . "
		                  WHERE orders_id = '" . (int)$orderId . "'";

						tep_db_query($sql);

						$sql_data_array = array('orders_id' => (int)$orderId,
						                        'orders_status_id' => 200,
						                        'date_added' => 'now()',
						                        'comments' => ' Update Checkout.com from Webhook. Status: Captured'
							                         ,
						                        'customer_notified' => 0
						);

						tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

					}else {
						echo  "Order has #$orderId was already set complete";
					}

				} elseif($objectCharge->getRefunded()) {

					$sql = "UPDATE " . TABLE_ORDERS  . "
		                  SET orders_status = " . (int)1 . "
		                  WHERE orders_id = '" . (int)$orderId . "'";
					tep_db_query($sql);
					$sql_data_array = array('orders_id' => (int)$orderId,
					                        'orders_status_id' => 1,
					                        'date_added' => 'now()',
					                        'comments' => ' Update Checkout.com from Webhook. Status:  Pending',
					                        'customer_notified' => 0
					);
					tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
					echo "Order has #$orderId was  set void (pending)";

				} elseif(!$objectCharge->getAuthorised()) {
					$sql = "UPDATE " . TABLE_ORDERS  . "
		                  SET orders_status = " . (int)1 . "
		                  WHERE orders_id = '" . (int)$orderId . "'";
					tep_db_query($sql);


					$sql_data_array = array('orders_id' => (int)$orderId,
					                        'orders_status_id' => 1,
					                        'date_added' => 'now()',
					                        'comments' => ' Update Checkout.com from Webhook. Status:  cancel ('
						                        . $orderStatuses[2] .')',
					                        'customer_notified' => 0
					);

					tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
					echo "Order has #$orderId was already set cancel (pending)";
				}

			}
		}
	}


}