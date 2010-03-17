<?php
/**
 * EasyPopulate handler for files generated from http://www.hotbuy4u.com
 *
 * @package easypopulate
 * @author John William Robeson, Jr <johnny>
 * @copyright 2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 */

/**
 * Premier Products Upload csv conversion class
 *
 * @todo provide a sample entry
 *
 * All Available Fields:
	itemid
	category
	subcategory
	brand
	item
	modelnumber
	thumb
	pic
	price
	instock
	description
	weight
	retailprice
	UPC
	Shipping Dimensions
 */
class EPUploadPremierProducts extends EPUploadStandard
{
	const FEED_URL = 'http://www.hotbuy4u.com/productindexdl.cfm';
	const IMAGES_URL = 'http://www.hotbuy4u.com/picsdl.cfm';

	public $name = 'PremierProducts';

	public static function defaultConfig()
	{
		$config = parent::defaultConfig();
		$config['column_delimiter'] = '^';
		return $config;
	}

	public function mapFileLayout(array $filelayout)
	{
		$filelayout[1] = 'categories_name_1';
		$filelayout[2] = 'categories_name_2';
		$filelayout[3] = 'manufacturers_name';
		$filelayout[5] = 'products_model';
		$filelayout[7] = 'products_image';
		$filelayout[8] = 'products_price';
		$filelayout[9] = 'products_quantity';
		$filelayout[11] = 'products_weight';
		$filelayout = array_flip($filelayout);
		return $filelayout;
	}

	public function handleRow(array $item)
	{
		$item['metatags'] = array();
		$descriptions = array();
		$descriptions['name'] = $item['item'];

		$item['products_quantity_order_min'] = 1;
		$item['products_quantity_order_units'] = 1;

		$item['manufacturers_name'] = str_replace('?', '', $item['manufacturers_name']);

		$item['products_image'] = $this->imagePathPrefix . $item['products_image'];
		if (!file_exists(DIR_FS_CATALOG . 'images/' . $item['products_image'])) {
			$item['products_image'] = 'no_picture.gif';
		}

		if (strpos($item['products_model'], '(R)') !== false) {
			$item['description'] .= '<br> Reconditioned';
		}
		$descriptions['description'] =  '<br><em><strong>Retail Price: $' . $item['retailprice'] .
			'</strong></em><br>' . $item['description'];

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