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
 */
class EPUploadMegaGoods extends EPUploadStandard
{
	public function handleRow(array $item)
	{
		$descriptions = array();
		$descriptions['name'] = $item['name'];

		$item['products_status'] = ($item['products_status'] == 'instock') ? 1 : 0;

		$item['products_quantity'] = 999;

		$description = '<br><em><strong>Retails Online: $' . $item['retails online'] . '</strong></em>' .
							'<br><em><strong>MSRP : $' . $item['MSRP'] . '</strong></em><br><br>' .
							$item['description'] .'<br>' . $item['condition'];

		$item['descriptions'][1]['name'] = $item['name'];
		$item['descriptions'][1]['description'] = $description;

		return $item;
	}
}
?>
