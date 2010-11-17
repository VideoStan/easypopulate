<?php
/**
 * EasyPopulate Classes
 *
 * @package easypopulate
 * @author John William Robeson, Jr <johnny>
 * @copyright 2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (version 2 or any later version)
 * @todo deprecated replace with zenmagick loader
 */

class EPFileUploadFactory
{
	private static function baseDirectory()
	{
		return ZM_BASE_PATH . '/plugins/easyPopulate/Handlers/';
	}

	/**
	 * Get a handler by name and include it
	 *
	 * @param string $name
	 * @return string name of the handler class
	 */
	public static function get($name)
	{
		require_once self::baseDirectory() . 'Standard.php';
		$file = self::baseDirectory() . $name . '.php';
		if (require_once($file)) {
			$classname = 'EPUpload' . $name;
			return $classname;
		} else {
		    return 'EasyPopulateCsvFileObject';
		}
	}

	/**
	 * Get File Processor
	 * @todo support export as well
	 * @todo use something else, this is just temporary.
	 *
	 * @param string $name name of import type
	 * @param object $config instance of EasyPopulateConfig
	 * @param string $item_type item sub type
	 * @return object instance of the import class
	 */
	public static function getProcessFile($name, EasyPopulateConfig $config, $item_type = '')
	{
		$file =  ZM_BASE_PATH . '/plugins/easyPopulate/lib/Import' . ucwords($item_type) . '.php';
		if (!require_once($file)) {
			return false;
		}

		$file = ZM_BASE_PATH . '/plugins/easyPopulate/lib/Import' . ucwords($name) . '.php';
		if (file_exists($file)) {
			require_once($file);
		}
		$clazz = 'EasyPopulateImport' . ucwords($name);
		if (!class_exists($clazz)) {
			$clazz = 'EasyPopulateImport' . ucwords($item_type);
		}
		return new $clazz($config);
	}
}
?>
