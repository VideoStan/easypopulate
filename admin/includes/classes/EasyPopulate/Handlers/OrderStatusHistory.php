<?php
/**
 * EasyPopulate order status history import
 *
 * @package easypopulate
 * @author John William Robeson, Jr <johnny>
 * @copyright 2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 */

/*
 *
 */
class EPUploadOrderStatusHistory extends EasyPopulateCsvFileObject
{
	public $name = 'OrderStatusHistory';
	public $itemCount = 0;

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