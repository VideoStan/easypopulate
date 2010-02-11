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
 *
 * All Available Fields:
  Item Title
  Item Status
  Available Inventory
  Item Number
  4Item Short Description
  Parent Number
  Web Description
  Catalog Description
  Item Supplemental Tab 1
  Country of Origin
  Points
  Retail Price
  Major Category
  Minor Category
  Case Count
  Each Price
  Column 2 Price
  Column 2 Break
  Column 3 Price
  Column 3 Break
  Column 4 Price
  Column 4 Break
  Column 5 Price
  Column 5 Break
  Each UPC
  Case UPC
  Catalog Page
  Each Weight (lbs.)
  Each Length (in.)
  Each Width (in.)
  Each Height (in.)
  Case Weight
  Case Length (in.)
  Case Width (in.)
  Case Height (in.)
  Estimated Next Ship Date
  Item Categories
  Item Categories (numeric)
 */
class EPUploadBNFUSA extends EPUploadStandard
{
	public $masterRowCount = 1;
	public function mapFileLayout(array $filelayout)
	{
		$rename = array();
		$rename['v_products_description_1'] = 'Web Description';
		$rename['v_products_name_1']			= 'Item Title';
		$rename['v_products_model']			= 'Parent Number';
		$rename['v_products_price']			= 'Each Price';
		$rename['v_products_quantity']		= 'Available Inventory';
		$rename['v_discount_price_1']			= 'Column 2 Price';
		$rename['v_discount_qty_1']			= 'Column 2 Break';
		$rename['v_discount_price_2']			= 'Column 3 Price';
		$rename['v_discount_qty_2']			= 'Column 3 Break';
		$rename['v_discount_price_3']			= 'Column 4 Price';
		$rename['v_discount_qty_3']			= 'Column 4 Break';
		$rename['v_discount_price_4']			= 'Column 5 Price';
		$rename['v_discount_qty_4']			= 'Column 5 Break';
		$rename['v_products_weight']			= 'Each Weight (lbs.)';
		$rename['v_categories_name_1']		= 'Major Category';
		$rename['v_categories_name_2']		= 'Minor Category';
		$rename['v_categories_name_3']		= 'Item Status';
		$rename['x_size_color_desc']			= 'Item Size-Color Desc';
		$rename['x_size_color_numeric']		= 'Size-Color Key Numeric';
		$rename['x_item_categories_numeric'] = 'Item Categories (numeric)';
		$filelayout = str_replace(array_values($rename), array_keys($rename), $filelayout);

		// Everything below here is dynamic, there is no matching field in the file
		$filelayout[] = 'v_products_image';
		$filelayout[] = 'v_products_discount_type';
		$filelayout[] = 'v_categories_name_1';
		$filelayout[] = 'v_categories_name_2';
		$filelayout[] = 'v_categories_name_3';
		$filelayout[] = 'v_categories_name_4';
		$filelayout[] = 'v_categories_name_5';

		$filelayout = array_flip($filelayout);
		return $filelayout;
	}

	public function handleRow(array $item)
	{
		/*$foo = explode(',', trim($item['Item Categories']));
		$count = 1;
		foreach ($foo as $cat) {
			$item['v_categories_name_' . $count] = $cat;
			$count++;
		}*/
		if (empty($item['v_categories_name_3'])) {
			unset($item['v_categories_name_3']);
		}

		if (empty($item['v_products_quantity_order_min']) || !isset($item['v_products_quantity_order_min'])) {
			$item['v_products_quantity_order_min'] = 1;
		}

		if (empty($item['v_products_quantity_order_units']) || !isset($item['v_products_quantity_order_units'])) {
			$item['v_products_quantity_order_units'] = 1;
		}
		$item['v_products_discount_type'] = 2;

		$model = $item['v_products_model'];
		$item['v_products_image'] = 'products/' . $model . '/' . $model . '_800.jpg';
		$desc = $item['x_size_color_desc'];
		$name = '';
		if (!empty($desc)) {
			$optionValues = array();
			while ($nextItem = $this->current()) {
				if ($nextItem['v_products_model'] != $model) {
					$this->seek($this->key() - 1);
					break;
				}
				$optionsId = $this->key();
				if ((int)$nextItem['x_size_color_numeric'] > 5000) {
					$name = 'Color';
				} else if ((int)$nextItem['x_size_color_numeric'] > 1) {
					$name = 'Size';
				}
				$values = array();
				$values['name'] = array();
				$values['id'] = $this->masterRowCount;
				$values['price'] = 0.00;
				$values['names'][1]  = $nextItem['x_size_color_desc']; // indexed by language_id
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