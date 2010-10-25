<?php
/**
 * EasyPopulate Standard import
 *
 * @package easypopulate
 * @author John William Robeson, Jr <johnny>
 * @copyright 2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 */

/**
 * Easy Populate standard product record format
 *
 * All headings in $filelayout['columnheading'] = columnnumber
 * All values are in $items[$filelayout] = 'value'
 */
class EPUploadStandard extends EasyPopulateCsvFileObject
{
	/**
	 * Map csv column header names to column names
	 *
	 * @param array
	 * @return array
	 */
	public function mapFileLayout($filelayout)
	{
		$filelayout = parent::mapFileLayout($filelayout);
		$filelayout = array_flip($filelayout); // @todo don't flip twice
		foreach ($filelayout as &$column) {
			$column = str_replace('v_', '', $column);
			// Make item type grouping a little simpler in handleRow() by renaming some columns
			if ($column == 'status') $column = 'products_status';
			if ($column == 'date_added') $column = 'products_date_added';
			if ($column == 'date_avail') $column = 'products_date_available';
			//if ($column == 'tax_class_title') $column = 'products_tax_class_title';
			//if ($column == 'specials_date_avail') $column = 'specials_date_available';
			//if ($column == 'specials_price') $column = 'specials_new_products_price');
		}
		return array_flip($filelayout);
	}

	/**
	 * Map row values to columns
	 *
	 * @todo transform all other fields that contain numbers, not just attributes
	 */
	public function handleRow(array $olditem)
	{
		$attributes = array();
		$item = array();
		foreach ($olditem as $key => $value) {
			$column = explode('_', $key);
			// don't touch already existing arrays
			if (!is_string($key)) break;
			switch ($column[0]) {
				case 'attribute':
					if ($column[1] == 'options') {
						if ($column[2] == 'id') {
							$attributes[$column[3]]['id'] = $value; // attribute_options_id_1
						}
						if ($column[2] == 'name') {
							$attributes[$column[3]]['names'][$column[4]] = $value; // attribute_options_name_1_1
						}
					}
					if ($column[1] == 'values') {
						if ($column[2] == 'id') {
							$attributes[$column[3]]['values'][$column[4]]['id'] = $value; //attribute_values_id_1_1
						}
						if ($column[2] == 'name') {
							$attributes[$column[3]]['values'][$column[4]]['names'][$column[5]] = $value; // attribute_values_name_2_3_1
						}
						if ($column[2] == 'price') {
							$attributes[$column[3]]['values'][$column[4]]['price'] = is_numeric($value) ? $value : 0.00;
						}
					}
					break;
				case 'metatags': // only for title,description,keywords
					if (isset($column[2]) && is_numeric($column[2])) {
						if (empty($value)) break;
						$item['metatags'][$column[2]][$column[1]] = $value; //indexed by language_id
						break;
					} else { // it goes with products
						//$item['products'][$key] = $value;
						break; // @todo don't break here
					}
				/*case 'specials':
					if ($column[1] == 'expires') {
						$item['specials']['expires_date'] = $value;
					} elseif ($column[1] == 'status') {
						$item['specials']['status'] = $value;
					} else {
						$item['specials'][$key] = $value;
					}
					break;
				*/
				case 'products':
					/*$fields = array('model', 'image', 'price', 'weight','quantity','tax');
					if (in_array($column[1], $fields)) {
						$item['products'][$key] = $value;
					}*/
					if (in_array($column[1], array('name', 'description', 'url', 'short'))) {
						if ($column[1] == 'short') $column[1] = 'short_desc';
						$item['descriptions'][$column[2]][$column[1]] = $value;
						//$item['descriptions'][$column[2]][$key] = $value; NEW, use this
						break;
					} // fall through for the rest

				/*case 'discount':
					if ($column[1] == 'type')) {
						$item['products'][$column[1]] = $value;
						break;
					}
					$item['discounts'][$column[2]][$column[1]] = $value;
					break;*/
				/*case 'categories':
					$item['categories'][$column[2][$column[1]] = $value;
					break;*/
				/*case 'manufacturers';
					$item['manufacturers'][$column[1]] = $value;
					break;*/
				default:
					$item[$key] = $value;
					break;
			}
		}
		$item['attributes'] = $attributes;

		return $item;
	}
}
?>
