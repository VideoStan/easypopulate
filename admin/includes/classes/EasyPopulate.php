<?php
/**
 * EasyPopulate Classes
 *
 * @package easypopulate
 * @author John William Robeson, Jr <johnny>
 * @copyright 2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 */

define('TABLE_EASYPOPULATE_FEEDS', DB_PREFIX . 'easypopulate_feeds');

class EasyPopulateProcess
{
	protected $taxClassMultipliers = array();
	protected $config = array();

	public function __construct(array $config = array())
	{
		$time_limit = ep_get_config('time_limit');
		$this->config = $config;
		@set_time_limit($time_limit);
		@ini_set('max_input_time', $time_limit);	
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

require DIR_WS_CLASSES . 'EasyPopulate/Import.php';
require DIR_WS_CLASSES . 'EasyPopulate/Export.php';

class EPFileUploadFactory
{
	private static function baseDirectory()
	{
		return DIR_FS_ADMIN . DIR_WS_CLASSES . 'EasyPopulate/Handlers/';
	}

	/**
	 * Find and return list of handlers
	 *
	 * @return array
	 */
	public static function find()
	{
		foreach (new DirectoryIterator(self::baseDirectory()) as $classFile) {
			if ($classFile->isDot() && !(preg_match('/\.php$/', $classFile->getFilename()))) continue;
			$classFiles[] = $classFile->getBaseName('.php');
		}
		return $classFiles;
	}

	/**
	 * Get a handler by name and include it
	 *
	 * @param string $name
	 * @return string name of the handler class
	 */
	public static function get($name)
	{
		// @todo make most of Standard an abstract class so we don't have to unconditonally include it here
		require_once self::baseDirectory() . 'Standard.php';
		$file = self::baseDirectory() . $name . '.php';
		if (require_once($file)) {
			$classname = 'EPUpload' . $name;
			return $classname;
		}
	}

	/**
	 * Get handler config
	 *
	 * @param string $name
	 * @return array array of config values
	 */
	public static function getConfig($name)
	{
		global $db;
		$query = "SELECT config FROM  " . TABLE_EASYPOPULATE_FEEDS . "
					WHERE name = '" . zen_db_input($name) . "'";
		$result = $db->Execute($query);
		$defaultConfig = call_user_func_array(array(EPFileUploadFactory::get($name), 'defaultConfig'), array());
		$config = array();
		while (!$result->EOF) {
			$config = json_decode($result->fields['config'], true);
			$result->MoveNext();
		}
		$config = array_merge($defaultConfig, $config);
		return $config;
	}

	/**
	 * Set handler config
	 *
	 * @param string $name
	 * @param array $config array of config entries
	 * @return bool
	 */
	public static function setConfig($name, array $config = array())
	{
		global $db;
		unset($config['feed_url']);
		unset($config['images_url']);
		$defaultConfig = call_user_func_array(array(EPFileUploadFactory::get($name), 'defaultConfig'), array()); 
		$config = array_merge($defaultConfig, $config);
		$data = array();
		$data['config'] = json_encode($config);
		$where = "name = '" . zen_db_input($name) . "'";
		$query = ep_db_modify(TABLE_EASYPOPULATE_FEEDS, $data, 'UPDATE', $where);
		$db->Execute($query);
		return true;
	}
}
?>