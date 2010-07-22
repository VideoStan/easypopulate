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
 * Premier Products csv conversion class
 *
 * @todo provide a sample entry
 *
 * All Available Fields:

 */
class EPUploadPremierProducts extends EPUploadStandard
{
	public $name = 'PremierProducts';
	public $expectedFileLayout = array(
		'Brand',
		'Category',
		'Description',
		'Dimensions',
		'Instock',
		'Item',
		'ItemID',
		'Modelnum',
		'Pic',
		'Price',
		'Retailprice',
		'Special',
		'TAGLINE',
		'Tpic',
		'UPC',
		'WEIGHT');

	public static function defaultConfig()
	{
		$config = parent::defaultConfig();
		$config['feed_url'] = 'http://hotbuy4u.com/products.csv';
		$config['local_file'] = 'PremierProducts.csv';
		$config['images_url'] = 'http://www.hotbuy4u.com/picsdl.cfm';
		$config['images_file_path'] = 'inetpub/wwwroot/products/pics';
		return $config;
	}

	public function mapFileLayout(array $filelayout)
	{
		$filelayout[0] = 'manufacturers_name';
		$filelayout[1] = 'categories_name_1';
		$filelayout[4] = 'products_quantity';
		$filelayout[7] = 'products_model';
		$filelayout[8] = 'products_image';
		$filelayout[9] = 'products_price';
		$filelayout[15] = 'products_weight';
		$filelayout = array_flip($filelayout);
		return $filelayout;
	}

	public function handleRow(array $item)
	{
		$item['metatags'] = array();
		$descriptions = array();
		$descriptions['name'] = $item['Item'];

		$item['manufacturers_name'] = str_replace('?', '', $item['manufacturers_name']);

		$item['products_image'] = $this->imagePathPrefix . $item['products_image'];
		if (!file_exists(DIR_FS_CATALOG . 'images/' . $item['products_image'])) {
			$item['products_image'] = 'no_picture.gif';
		}

		$descriptions['description'] =  $item['TAGLINE'] . '<br>' .
		'<em><strong>Retail Price: $' . $item['Retailprice'] .'</strong></em><br>' .
		$item['Description'];
		if (strpos($item['products_model'], '(R)') !== false) {
			$descriptions['description'] .= '<br> Reconditioned';
		}

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