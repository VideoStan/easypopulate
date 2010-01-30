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
		$rename['x_size_color_desc']			= 'Item Size-Color Desc'; 
		$rename['x_size_color_numeric']		= 'Size-Color Key Numeric';
		$rename['x_item_categories_numeric'] = 'Item Categories (numeric)';
		$filelayout = str_replace(array_values($rename), array_keys($rename), $filelayout);

		// Everything below here is dynamic, there is no matching field in the file
		$filelayout[] = 'v_products_image';
		$filelayout[] = 'v_categories_name_1';
		$filelayout[] = 'v_categories_name_2';
		$filelayout[] = 'v_categories_name_3';
		$filelayout[] = 'v_categories_name_4';
		$filelayout[] = 'v_categories_name_5';

		$filelayout[] = 'v_attribute_options_id_1';
		$filelayout[] = 'v_attribute_options_name_1_1';
		$filelayout[] = 'v_attribute_values_id_1_1';
		$filelayout[] = 'v_attribute_values_price_1_1';
		$filelayout[] = 'v_attribute_values_name_1_1_1';
		$filelayout[] = 'v_attribute_values_id_1_2';
		$filelayout[] = 'v_attribute_values_price_1_2';
		$filelayout[] = 'v_attribute_values_name_1_2_1';
		$filelayout[] = 'v_attribute_values_id_1_3';
		$filelayout[] = 'v_attribute_values_price_1_3';
		$filelayout[] = 'v_attribute_values_name_1_3_1';
		$filelayout[] = 'v_attribute_values_id_1_4';
		$filelayout[] = 'v_attribute_values_price_1_4';
		$filelayout[] = 'v_attribute_values_name_1_4_1';
		$filelayout[] = 'v_attribute_values_id_1_5';
		$filelayout[] = 'v_attribute_values_price_1_5';
		$filelayout[] = 'v_attribute_values_name_1_5_1';
		$filelayout[] = 'v_attribute_values_id_1_6';
		$filelayout[] = 'v_attribute_values_price_1_6';
		$filelayout[] = 'v_attribute_values_name_1_6_1';

		$filelayout = array_flip($filelayout);
		return $filelayout;
	}

	public function handleRow(array $item)
	{
		$foo = explode(',', trim($item['Item Categories']));	
		$count = 1;
		foreach ($foo as $cat) {
			$item['v_categories_name_' . $count] = $cat;
			$count++;
		}
		
		$model = $item['v_products_model'];
		$item['v_products_image'] = 'products/' . $model . '/' . $model . '_800.jpg'
		$desc = $item['x_size_color_desc'];
		$name = '';
		if (!empty($desc)) {
			$att = array();
			$pos = $this->key();
			$this->next();
			while ($nextItem = $this->current()) {
				if ($nextItem['v_products_model'] != $model) break;
				if ((int)$nextItem['x_size_color_numeric'] > 5000) {
					$name = 'Color';
				} else if ((int)$nextItem['x_size_color_numeric'] > 1) {
					$name = 'Size';
				}
				$att[] = $nextItem['x_size_color_desc'];
				$this->next();
			}

			$item['v_attribute_options_id_1'] = 1;
			$item['v_attribute_options_name_1_1'] = $name;
			$count = 1;
			foreach ($att as $value) {
				if ($value == '') continue;
				$item['v_attribute_values_id_1_' . $count] = $count;
				$item['v_attribute_values_price_1_' . $count] = 0.00;
				$item['v_attribute_values_name_1_' . $count . '_1'] = $value;
				$count++;
			}
		}
		return $item;
	}
}
?>