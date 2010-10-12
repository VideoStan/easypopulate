<?php
/**
 * EasyPopulate order status history import
 *
 * @package easypopulate
 * @author johnny <johnny@localmomentum.net>
 * @copyright 200?-2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 * @todo <johnny> make it a better class
 */


class EasyPopulateImportOrderStatusHistory extends EasyPopulateProcess
{
	public function dependenciesMet()
	{
		return true;
	}

	function isGobPackageTrackerInstalled()
	{
		return file_exists(DIR_FS_ADMIN . 'package_trackers.php');	
	}

	public function run(SplFileInfo $fileInfo)
	{
		// @todo do this before run()
		$file = $this->openFile($fileInfo);
		if ($file === false) return false

		foreach ($file as $items) {
			$output_message = '';
			$items = $file->handleRow($items);

			if (empty($items['orders_id']) || empty($items['orders_status_id'])) continue;

			$order = $this->getOrder($items['orders_id']);
			if (!$order) continue; // @todo error;
			
			if (!isset($items['comments'])) $items['comments'] = '';
			
			if (trim($items['notify_customer']) == '') {
				$items['notify_customer'] = 0;
			}

			$status = $this->getOrderStatusIds($items['orders_status_id']);
			$items['orders_status_id'] = key($status);

			if ($this->isGobPackageTrackerInstalled()
			&& isset($items['tracking_numbers']) 
			&& !empty($items['tracking_numbers'])) {
				$trackingInfo = $this->getTrackerInfo($items['tracker_id']);
				$trackerId = $trackingInfo['tracker_id'];
			}

 			if ($order['orders_status'] != $items['orders_status_id']) {
 				$data = array();

 				$data['orders_id'] = $items['orders_id'];
 				$data['orders_status_id'] = $items['orders_status_id'];
				$data['date_added'] = 'NOW()';
				$data['customer_notified'] = $items['notify_customer'];
				$data['comments'] = $items['comments'];
				if (isset($trackingInfo)) {
					$data['tracker_id'] = $trackerId;
					$data['tracking_numbers'] = $items['tracking_numbers'];
				}
				$query = ep_db_modify(TABLE_ORDERS_STATUS_HISTORY, $data, 'INSERT');
				$result = ep_query($query);

				$where = " orders_id = " . zen_db_input($items['orders_id']);
				$data = array();
				$data['orders_status'] = zen_db_input($items['orders_status_id']);
				$data['last_modified'] = 'NOW()';
				$query = ep_db_modify(TABLE_ORDERS, $data, 'UPDATE', $where);

				$result = ep_query($query);
				// @todo notify comments?
				if ($items['notify_customer']) {
					$order['orders_status_name'] = current($status);
					$order['comments'] = $items['comments'];
					if (isset($trackingInfo)) {
						$order['tracking_numbers'] = $items['tracking_numbers'];
						$order['tracker_status'] = $trackingInfo['tracker_status'];
						$order['tracker_carrier_name'] = $trackingInfo['tracker_carrier_name'];
						$order['tracker_carrier_link'] = $trackingInfo['tracker_carrier_link'];
					}
					$this->sendOrderStatusEmail($order);
				}
			}

			$output_data = array_values($items);
			// @todo write  status message and status to tempFile 

			$output_data = $this->flattenArray($items);
			if (empty($this->tempFile->filelayout)) {
				$this->tempFile->setFileLayout(array_keys($output_data), true);
			}

			$this->tempFile->write($output_data);
			//$file->onItemFinish($products_id, $products_model);
			$this->itemCount++;
		}

		return true;
	}
		
	public function getOrderStatusIds($name = '', $langId = 1)
	{
		$query = "SELECT orders_status_id, orders_status_name FROM " . TABLE_ORDERS_STATUS . "
		WHERE language_id = " . zen_db_input($langId);

		if (!empty($name)) {
			if (is_numeric($name)) {
				$query .= " AND orders_status_id = '" . zen_db_input($name) . "'";
			} else {
				$query .= " AND orders_status_name = '" . zen_db_input($name) . "'";
			}
		}

		$result = ep_query($query);

		if (!$result) return array();
		$statuses = array();
		while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$statuses[$row['orders_status_id']] = $row['orders_status_name'];
		}
		return $statuses;
	}
		
	public function getTrackerInfo($name = '')
	{
		$query = "SELECT * FROM " . TABLE_TRACKERS . " WHERE ";
		
		if (is_numeric($name)) {
			$query .= "tracker_id = " . zen_db_input($name);
		} else { 
			$query .= "tracker_carrier_name = '" . zen_db_input($name) . "'";
		}
		$result = ep_query($query);
		if (!$result) return false;
		$row = mysql_fetch_array($result, MYSQL_ASSOC);
		return $row;
	}

	public function getOrder($orderId)
	{
		$query = "SELECT * FROM " . TABLE_ORDERS . " WHERE orders_id = " . (int)$orderId;
		$result = ep_query($query);
		if (!is_resource($result)) return false;
		$row = mysql_fetch_array($result, MYSQL_ASSOC);
		return $row;
	}

	/**
	 * Send an email to customers if their order status has changed
	 *
	 * @param array $order 
	 * @param bool $notifyComments
	 * @param bool $notifyTracking
	 * @todo generate the email text in a better way than zencart does
	 */
	public function sendOrderStatusEmail($order, $notifyComments = false)
	{
		$config = $this->config->getValues($this->importHandler);

		error_reporting($GLOBALS['zen_error_level']);
		require_once DIR_FS_ADMIN . '/includes/languages/' . $config['language'] . '/orders.php';
		error_reporting($this->errorLevel);
		$comments = (isset($order['comments'])) ? $order['comments'] : '';

		$html_msg = array();
		$html_msg['EMAIL_TEXT_STATUS_TRACKING'] = '';
		$html_msg['EMAIL_TEXT_STATUS_COMMENTS'] = '';
		if (isset($order['tracking_numbers']) && !empty($order['tracking_numbers'])) {
			$trackingNumbers = preg_split("/[ ,;]+/", $order['tracking_numbers'], -1, PREG_SPLIT_NO_EMPTY);

			$trackingSnippet = $order['tracker_carrier_name'] . ":";              
			foreach ($trackingNumbers as $trackingNumber) {
				$trackingSnippet .= "\n";
				if ($order['tracker_status']) {
					$trackerLink = sprintf($order['tracker_carrier_link'], $trackingNumber);
					$trackingSnippet .= '<a href="' . $trackerLink . '" target="_blank">' . $trackerLink . '</a>';
				} else {
					$trackingSnippet .= $trackingNumber;
				}
				$htmlmsg['EMAIL_TEXT_STATUS_TRACKING'] = $trackingSnippet;
				$trackingSnippet = EMAIL_TEXT_TRACKING_NUMBER . "\n" . $trackingSnippet . "\n\n";
			}
		}
		$message = STORE_NAME . "\n" . EMAIL_SEPARATOR . "\n";
		$message .= EMAIL_TEXT_ORDER_NUMBER . ' ' . $order['orders_id'] . "\n\n";
		$html_msg['EMAIL_TEXT_ORDER_NUMBER'] = EMAIL_TEXT_ORDER_NUMBER . ' ' . $order['orders_id'];
		if (isset($order['COWOA_order']) && !$order['COWOA_order']) {
			$message .= EMAIL_TEXT_INVOICE_URL . ' ' . zen_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $order['orders_id'], 'SSL') . "\n\n";
			$html_msg['EMAIL_TEXT_INVOICE_URL']  = '<a href="' . zen_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id=' . $order['orders_id'], 'SSL') .'">'.str_replace(':','',EMAIL_TEXT_INVOICE_URL).'</a>';
		}
		$message .= EMAIL_TEXT_DATE_ORDERED . ' ' . zen_date_long($order['date_purchased']) . "\n\n";
		if (isset($order['delivery_date']) && !empty($order['delivery_date'])) {
			$message .= EMAIL_TEXT_DELIVERY_DATE . ' ' . zen_date_long($order['delivery_date']) . "\n\n";
	      $html_msg['EMAIL_TEXT_DELIVERY_DATE'] = EMAIL_TEXT_DELIVERY_DATE . ' ' . zen_date_long($order['delivery_date']);
		}
		if (!empty($comments)) {
			$message .= EMAIL_TEXT_COMMENTS_UPDATE . strip_tags($comments) . "\n\n";
			$html_msg['EMAIL_TEXT_STATUS_COMMENTS'] = nl2br($comments);
		}
		$message .= isset($trackingSnippet) ? strip_tags($trackingSnippet) : '';
		$message .=  EMAIL_TEXT_STATUS_UPDATED . 
		sprintf(EMAIL_TEXT_STATUS_LABEL, $order['orders_status_name'] ) .
		EMAIL_TEXT_STATUS_PLEASE_REPLY;

		$html_msg['EMAIL_CUSTOMERS_NAME']    = $order['customers_name'];
		$html_msg['EMAIL_TEXT_DATE_ORDERED'] = EMAIL_TEXT_DATE_ORDERED . ' ' . zen_date_long($order['date_purchased']);
		$html_msg['EMAIL_TEXT_STATUS_UPDATED'] = str_replace('\n','', EMAIL_TEXT_STATUS_UPDATED);
		$html_msg['EMAIL_TEXT_STATUS_LABEL'] = str_replace('\n','', sprintf(EMAIL_TEXT_STATUS_LABEL, $order['orders_status_name'] ));
		$html_msg['EMAIL_TEXT_NEW_STATUS'] = $order['orders_status_name'];
		$html_msg['EMAIL_TEXT_STATUS_PLEASE_REPLY'] = str_replace('\n','', EMAIL_TEXT_STATUS_PLEASE_REPLY);

		zen_mail($order['customers_name'], $order['customers_email_address'], EMAIL_TEXT_SUBJECT . ' #' . $order['orders_id'], $message, STORE_NAME, EMAIL_FROM, $html_msg, 'order_status');

		//send extra emails
		if (SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO_STATUS == '1' and SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO != '') {
			zen_mail('', SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO, SEND_EXTRA_ORDERS_STATUS_ADMIN_EMAILS_TO_SUBJECT . ' ' . EMAIL_TEXT_SUBJECT . ' #' . $order['orders_id'], $message, STORE_NAME, EMAIL_FROM, $html_msg, 'order_status_extra');
		}			
	}
}
?>
