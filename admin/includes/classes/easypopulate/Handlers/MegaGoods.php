<?php
/**
 * EasyPopulate handler for files generated from http://www.megagoods.com
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
   0 => model
   1 => mpn
   2 => name
   3 => description
   4 => upc
   5 => manufacturer
   6 => price
   7 => retails online
   8 => MSRP
   9 => weight
   10 => condition
   11 => image
   12 => category
   13 => status
 */
class EPUploadMegaGoods extends EPUploadStandard
{
	const FEED_URL = 'https://www.megagoods.com/export.php?show=format_tab';
	const IMAGES_URL = 'https://www.megagoods.com/pimages/regular.zip';

	public $name = 'MegaGoods';

	public function mapFileLayout(array $filelayout)
	{
		$filelayout[0] = 'products_model';
		$filelayout[5] = 'manufacturers_name';
		$filelayout[6] = 'products_price';
		$filelayout[9] = 'products_weight';
		$filelayout[11] = 'products_image';
		$filelayout[12] = 'categories_name_1';
		$filelayout[13] = 'products_status';
		$filelayout = array_flip($filelayout);
		return $filelayout;
	}

	public function handleRow(array $item)
	{
		$descriptions = array();
		$descriptions['name'] = $item['name'];

		$item['metatags'] = array();

		$item['products_quantity_order_min'] = 1;
		$item['products_quantity_order_units'] = 1;

		$item['products_status'] = 1;
		if ($item['products_status'] == 'soldout') {
			$item['products_status'] == 0;
		}

		$item['products_quantity'] = 999;

		$item['products_image'] = $this->imagePathPrefix . $item['products_image'];
		if (!file_exists(DIR_FS_CATALOG . 'images/' . $item['products_image'])) {
			$item['products_image'] = 'no_picture.gif';
		}

		$description = '<br><em><strong>Retails Online: $' . $item['retails online'] . '</strong></em>' .
							'<br><em><strong>MSRP : $' . $item['MSRP'] . '</strong></em><br><br>' .
							$item['description'] .'<br>' . $item['condition'];

		$item['descriptions'][1]['name'] = $item['name'];
		$item['descriptions'][1]['description'] = $description;

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