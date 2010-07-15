<?php
/**
 * EasyPopulate order status history import
 *
 * @package easypopulate
 * @author John William Robeson, Jr <johnny>
 * @copyright 2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 */

/**
 * Easy Populate record format
 *
 * All headings in $filelayout['columnheading'] = columnnumber
 * All values are in $items[$filelayout] = 'value'
 *
  Available Fields:
  order_id
  order_status
  notify_customer (0 or 1)
  comments  
  tracking_numbers
  tracker_id 
 */
class EPUploadOrderStatusHistory extends EasyPopulateCsvFileObject
{
	public $name = 'OrderStatusHistory';
	public $itemCount = 0;

	function __construct($file)
	{
		$this->transforms = array();
		$this->autoDetectLineEndings(ep_get_config('detect_line_endings'));
		parent::__construct($file);
	}

	public static function defaultConfig()
	{
		$config = array();
		$config['column_delimiter'] = ',';
		$config['column_enclosure'] = '"';
		$config['price_modifier'] = 0;
		$config['image_path_prefix'] = '';
		$config['tax_class_title'] = '';
		$config['metatags_keywords'] = '';
		$config['metatags_description'] = '';
		$config['metatags_title'] = '';
		$config['feed_fetch'] = false;
		$config['images_fetch'] = false;
		$config['feed_send_email'] = false;
		return $config;
	}

	/**
	 * Map csv column header names to column names
	 *
	 * @param array
	 * @return array
	 */
	public function mapFileLayout($filelayout)
	{
		$filelayout[0] = 'orders_id';
		$filelayout[1] = 'orders_status_id';
      $filelayout[2] = 'notify_customer';
      $filelayout[3] = 'comments';
      $filelayout[4] = 'tracking_numbers';
      $filelayout[5] = 'tracker_id';
		return array_flip($filelayout);
	}

	/**
	 * Map row values to columns
	 *
	 */
	public function handleRow(array $item)
	{
		return $item;
	}

	/**
	 * Do something when the item is finished
	 *
	 * @todo think about this function signature
	 */
	public function onItemFinish($itemId)
	{
		$this->itemCount++;
	}

	/**
	 * do something when the file is finished processing
	 */
	public function onFileFinish()
	{
	}

}
?>