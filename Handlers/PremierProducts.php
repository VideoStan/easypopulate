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

 */
class EPUploadPremierProducts extends EPUploadStandard
{
	public function handleRow(array $item)
	{
		$descriptions = array();
		$descriptions['name'] = $item['Item'];

		$item['manufacturers_name'] = str_replace('?', '', $item['manufacturers_name']);

		$descriptions['description'] =  $item['TAGLINE'] . '<br>' .
		'<em><strong>Retail Price: $' . $item['Retailprice'] .'</strong></em><br>' .
		$item['Description'];
		if (strpos($item['products_model'], '(R)') !== false) {
			$descriptions['description'] .= '<br> Reconditioned';
		}

		$item['descriptions'][1] = $descriptions;

		return $item;
	}
}
?>
