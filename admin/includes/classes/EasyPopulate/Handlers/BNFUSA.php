<?php
/**
 * EasyPopulate handler for files generated from http://bnfusa.com
 *
 * @package easypopulate
 * @author John William Robeson, Jr <johnny>
 * @copyright 2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 */

/**
 * BNFUSA Upload csv conversion class
 *
 * @todo provide a sample entry
 */
class EPUploadBNFUSA extends EasyPopulateCsvFileObject
{
	public $masterRowCount = 1;

	public function mapFileLayout(array $filelayout)
	{
		$rename = array();
		$rename['products_model']		= 'Parent Number';
		$rename['products_price']		= 'Each Price';
		$rename['products_quantity']	= 'Available Inventory';
		$rename['discount_price_1']	= 'Column 2 Price';
		$rename['discount_qty_1']		= 'Column 2 Break';
		$rename['discount_price_2']	= 'Column 3 Price';
		$rename['discount_qty_2']		= 'Column 3 Break';
		$rename['discount_price_3']	= 'Column 4 Price';
		$rename['discount_qty_3']		= 'Column 4 Break';
		$rename['discount_price_4']	= 'Column 5 Price';
		$rename['discount_qty_4']		= 'Column 5 Break';
		$rename['products_weight']		= 'Each Weight (lbs.)';
		$rename['categories_name_1']	= 'Major Category';
		$rename['categories_name_2']	= 'Minor Category';
		$rename['categories_name_3']	= 'Item Status';
		$filelayout = str_replace(array_values($rename), array_keys($rename), $filelayout);

		// Everything below here is dynamic, there is no matching field in the file
		$filelayout[] = 'products_image';
		$filelayout[] = 'products_discount_type';

		$filelayout = array_flip($filelayout);
		return $filelayout;
	}

	public function handleRow(array $item)
	{
		$item['metatags'] = array();
		$descriptions = array();

		$descriptions[1]['name'] = $item['Item Title'];
		$descriptions[1]['description'] = $item['Web Description'];
		$item['descriptions'] = $descriptions;

		if (!empty($item['categories_name_3'])) {
			unset($item['categories_name_3']);
		}
		/*$foo = explode(',', trim($item['Item Categories']));
		foreach ($foo as $cat) {
			$item['categories'][] = $cat;
		}*/

		$item['products_discount_type'] = 2;

		$model = $item['products_model'];

		$item['products_image'] = $model . '/' . $model . '_800.jpg';
		if (!file_exists(DIR_FS_CATALOG . 'images/' . $item['products_image'])) {
			$item['products_image'] = 'no_picture.gif';
		}

		$desc = $item['Item Size-Color Desc'];
		$item['attributes'] = array();
		$name = '';
		if (!empty($desc)) {
			$optionValues = array();
			while ($nextItem = $this->current()) {
				if ($nextItem['products_model'] != $model) {
					$this->seek($this->key() - 1);
					break;
				}
				$optionsId = $this->key();
				if ((int)$nextItem['Size-Color Key Numeric'] > 5000) {
					$name = 'Color';
				} else if ((int)$nextItem['Size-Color Key Numeric'] > 1) {
					$name = 'Size';
				}
				$values = array();
				$values['name'] = array();
				$values['id'] = $this->masterRowCount;
				$values['price'] = 0.00;
				$values['names'][1]  = $nextItem['Item Size-Color Desc']; // indexed by language_id
				$optionValues[] = $values;
				$this->masterRowCount++;
				$this->next();
			}

			$attributes = array();
			$attributes['id'] = $optionsId;
			$attributes['names'] = array(); // indexed by language_id;
			$attributes['names'][1] = $name;

			$item['attributes'][0] = array_merge($attributes, array('values' => $optionValues));
		}

		return $item;
	}
}
?>
