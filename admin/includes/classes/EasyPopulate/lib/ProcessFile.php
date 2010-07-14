<?php
/**
 * EasyPopulate Classes
 *
 * @package easypopulate
 * @author John William Robeson, Jr <johnny>
 * @copyright 2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 */
 
/**
 * Parent class of the import/export file processors
 */
class EasyPopulateProcess
{
	public $itemCount = 0;
	public $tempFile;
	public $importHandler;
	public $error = '';

	protected $errorLevel;
	protected $zenErrorLevel;
	protected $taxClassMultipliers = array();
	protected $config = array();

	public function __construct(array $config = array())
	{
		$time_limit = ep_get_config('time_limit');
		$this->config = $config;
		@set_time_limit($time_limit);
		@ini_set('max_input_time', $time_limit);
		$this->errorLevel = error_reporting();
	}

	public function setImportHandler($handler)
	{
		if (!$this->dependenciesMet()) return false;
		$this->importHandler = $handler;
		return true;
	}

	public function openTempFile()
	{
		$tempFName = ep_get_config('temp_path') . 'EP-'. $this->importHandler . '-'. date(DATE_ATOM) . '.csv';
		$tempFile = new EasyPopulateCsvFileObject($tempFName , 'w+');
		$tempFile->setCsvControl(',', '"');
		$this->tempFile = $tempFile;
	}
	/**
	 * Flatten array
	 *
	 * @param array $array array to flatten
	 * @param string $prefix prefix all array keys with $prefix
	 * @return array
	 * @todo replace with an iterator
	 */
	protected function flattenArray($array, $prefix = null)
	{
		$items = array();

		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$items = array_merge($items,  $this->flattenArray($value, $prefix . $key));
			} else {
				$items[$prefix . $key] = $value;
			}
		}
		return $items;
	}

	/**
	 * Get tax class rate
	 * @param int $taxClassId
	 * @return int tax rate
	 */
	protected function getTaxClassRate($taxClassId)
	{
		if (isset($this->taxClassMultipliers[$taxClassId])) {
			return $this->taxClassMultipliers[$taxClassId];
		}
		$multiplier = 0;
		$query = "SELECT SUM(tax_rate) AS tax_rate FROM " . TABLE_TAX_RATES . "
		WHERE  tax_class_id = '" . zen_db_input($taxClassId) . "' GROUP BY tax_priority";
		$result = mysql_query($query);
		if (mysql_num_rows($result)) {
			while ($row = mysql_fetch_array($result)) {
				$multiplier += $row['tax_rate'];
			}
		}
		$this->taxClassMultipliers[$taxClassId] = $multiplier;
		return $multiplier;
	}

	/**
	 * Get Manufacturer Name By ID
	 *
	 * @param int $id manufacturer id
	 * @return string manufacturer name
	 */
	protected function getManufacturerName($id = NULL)
	{
		if (empty($id)) return '';
		$query = "SELECT manufacturers_name FROM ".TABLE_MANUFACTURERS."
		WHERE manufacturers_id = " . zen_db_input($id);
		$result = ep_query($query);
		$row =  mysql_fetch_array($result);
		if (empty($row)) return '';
		return $row['manufacturers_name'];
	}

	/**
	 * Get product categories
	 *
	 * @param int $categoryId
	 * @return array
	 * @todo CHECKME: this method could use a rewrite, it is essentially the same as the original code
	 */
	protected function getCategories($categoryId)
	{
		$langId = ep_get_config('epdlanguage_id'); // @todo store this in EasyPopulateProcess
		$categories = array();
		for ($categorylevel=1; ; $categorylevel++) {
			if (empty($categoryId)) break;
			$query = "SELECT categories_name FROM ".TABLE_CATEGORIES_DESCRIPTION."
			WHERE
			categories_id = " . $categoryId . " AND
			language_id = " . $langId;
			$result = ep_query($query);
			$row = mysql_fetch_array($result);
			$categories[$categorylevel] = $row['categories_name'];

			$query2 = "SELECT parent_id FROM ".TABLE_CATEGORIES."
			WHERE categories_id = " . $categoryId;
			$result2 = ep_query($query2);
			$row2 =  mysql_fetch_array($result2);
			$parentId = $row2['parent_id'];
			if ($parentId != '') {
				// there was a parent ID, lets set $categoryId to get the next level
				$categoryId = $parentId;
			} else {
				// we have found the top level category for this item,
				$categoryId = false;
			}
		}
		return array_reverse($categories);
	}

	/**
	 * Clean out newlines/carriage returns from products
	 * and optionally apply a regular expression
	 *
	 * @param string $string
	 * @param array $tags key => value list of regular expressions to apply
	 * @param bool $doit whether to apply tags or not
	 * @return string modified string
 	*/  
	protected function smartTags($string, $doit = true)
	{
		if ($doit) {
			$tags = ep_get_config('smart_tags');
			if (ep_get_config('enable_advanced_smart_tags')) {
				$advancedSmartTags = ep_get_config('advanced_smart_tags');
				$tags = array_merge($advancedSmartTags, $smartTags);
			}
			foreach ($tags as $tag => $new) {
				$tag = '/('.$tag.')/';
				$string = preg_replace($tag,$new,$string);
			}
		}

		$string = preg_replace("/(\r\n|\n|\r)/", "", $string);
		return $string;
	}
}
?>