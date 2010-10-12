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
 */
class EPUploadEvertek extends EasyPopulateCsvFileObject
{
	private $category = '';

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
		$item['products_image'] = basename($item['products_image']);

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
}
?>
