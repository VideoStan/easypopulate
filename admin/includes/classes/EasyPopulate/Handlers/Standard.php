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
 * Easy Populate record format
 *
 * All headings in $filelayout['columnheading'] = columnnumber
 * All values are in $items[$filelayout] = 'value'
 *
 * @todo CHECKME the CSV support depends on php > 5.2.0, is this important?
 *       If so, we can replace the subclass the current method to call fgetcsv
 *       and implement setCsvControl
 */
class EPUploadStandard extends EasyPopulateCsvFileObject
{
	public $name = 'Standard';
	public $itemCount = 0;
	public $transforms = array();

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
			if ($column == 'status') $column = 'products_status';
			if ($column == 'date_added') $column = 'products_date_added';
			if ($column == 'date_avail') $column = 'products_date_available';
		}
		return array_flip($filelayout);
	}

	/**
	 * Get column value by name
	 *
	 * @param string $name
	 * @return mixed $column
	 */
	public function get($column)
	{
		$line = $this->current();
		return $line[$column];
	}

	/**
	 * Map row values to columns
	 *
	 * @todo transform all other fields that contain numbers, not just attributes
	 */
	public function handleRow(array $olditem)
	{
		$attributes = array();
		$metatags = array();
		$descriptions = array();
		$item = array();
		foreach ($olditem as $key => $value) {
			$column = explode('_', $key);
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
					// @todo don't hardcode which fields we can use with the placeholders
					if (isset($column[2]) && is_numeric($column[2])) {
						if (empty($value)) break;
						$metatags[$column[2]][$column[1]] = $value; //indexed by language_id
						break;
					}
				case 'products':
					if (in_array($column[1], array('name', 'description', 'url', 'short'))) {
						if ($column[1] == 'short') $column[1] = 'short_desc';
						$descriptions[$column[2]][$column[1]] = $value;
						break;
					} // fall through for the rest
				default:
					$item[$key] = $value;
					break;
			}
		}
		$item['metatags'] = $metatags;
		$item['attributes'] = $attributes;
		$item['descriptions'] = $descriptions;

		if ((trim($item['products_quantity']) == '') || !isset($item['products_quantity'])) {
			$item['v_products_quantity'] = 0;
		}

		return $item;
	}
}
?>
