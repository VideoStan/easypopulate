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

require DIR_WS_CLASSES . 'EasyPopulate/lib/ProcessFile.php';
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