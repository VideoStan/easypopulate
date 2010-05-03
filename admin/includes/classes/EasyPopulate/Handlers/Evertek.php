<?php
/**
 * EasyPopulate handler for files generated from http://www.evertek.com
 *
 * @package easypopulate
 * @author John William Robeson, Jr <johnny>
 * @copyright 2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 */

/**
 * Evertek Upload csv conversion class
 *
 * @todo provide a sample entry
 [0] => QtyAvail
 [1] => PartNumber
 [2] => Product Description
 [3] => Link
 [4] => Condition
 [5] => UPC
 [6] => Disc Qty
 [7] => Disc Price
 [8] => Reg Price
 [9] => Warranty
 [10] => Unit Weight
 [11] => 70x70 Image
 [12] => 300x300 Image
 [13] => Manufacturer
 [14] => Unit Dims
 [15] => Category Code
 [16] => Cat-SubCat Pairs
 [17] => Product Note
 [18] => Features/Specifications
 [19] => Package Includes
 [20] => Additional Information
 *
 */
class EPUploadEvertek extends EPUploadStandard
{
	public $name = 'Evertek';

	private $category = '';

	public static function defaultConfig()
	{
		$config = parent::defaultConfig();
		$config['feed_url'] = 'http://www.evertek.com/Inventory_list/Evertek_Inventory_List.csv';
		$config['local_file'] = 'Evertek.csv';
		return $config;
	}

	public function mapFileLayout(array $filelayout)
	{

		$filelayout[0] = 'products_quantity';
		$filelayout[1] = 'products_model';
		$filelayout[6] = 'discount_qty_1';
		$filelayout[7] = 'discount_price_1';
		$filelayout[8] = 'products_price';
		$filelayout[10] = 'products_weight';
		$filelayout[12] = 'products_image';
		$filelayout[13] = 'manufacturers_name';
		$filelayout[15] = 'categories_name_1';
		$filelayout = array_flip($filelayout);
		return $filelayout;
	}

	public function handleRow(array $item)
	{
		if (empty($item['products_model'])) {
			// There is only the category on this line
			$this->category = $item['Product Description'];
			$this->next();
			$item = $this->current();
		}
		$item['categories_name_1'] = $this->category;

		$item['metatags'] = array();
		$descriptions = array();
		$descriptions['name'] = $item['Product Description'];

		$item['products_price'] = str_replace('$', '', $item['products_price']);
		$item['discount_price_1'] = str_replace('$', '', $item['discount_price_1']);

		$imageURL = $item['products_image'];
		$item['products_image'] = $this->imagePathPrefix . basename($item['products_image']);

		$imagePath = DIR_FS_CATALOG . 'images/' . $item['products_image'];
		if (!empty($item['products_image']) && !file_exists($imagePath)) {
			if (!@copy($imageURL, $imagePath)) { // @todo error message
				$item['products_image'] = 'no_picture.gif';
			}
		}

		$description = $item['Product Note']
		. '<br><strong>Condition:</strong> ' . $item['Condition']
		. '<br><strong>Warranty:</strong> ' . $item['Warranty']
		. '<br><strong>Features/Specifications:</strong> ' . $item['Features/Specifications']
		. '<br><strong>Package Includes:</strong> ' . $item['Package Includes']
		. '<br><strong>Additional Information:</strong> ' . $item['Additional Information'];
		$descriptions['description'] = $description;

		$item['descriptions'][1] = $descriptions;

		return $item;
	}

	function onFileStart()
	{
		$this->productIds = array();
	}

	function onItemFinish($productId, $productModel)
	{
		$this->productIds[] = (int)$productId;
		$this->itemCount++;
	}

	public function onFileFinish()
	{
		$this->removeMissingProducts();
	}
}
?>