<?php
/**
 * EasyPopulate Classes
 *
 * @package easypopulate
 * @author John William Robeson, Jr <johnny>
 * @copyright 2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 */

require DIR_FS_ADMIN . DIR_WS_CLASSES . 'EasyPopulate/lib/EasyPopulateCsvFileObject.php';
require DIR_FS_ADMIN . DIR_WS_CLASSES . 'EasyPopulate/lib/EasyPopulateConfig.php';
require DIR_WS_CLASSES . 'EasyPopulate/lib/ProcessFile.php';
require DIR_WS_CLASSES . 'EasyPopulate/lib/ImportProducts.php';
require DIR_WS_CLASSES . 'EasyPopulate/Export.php';

class EPFileUploadFactory
{
	private static function baseDirectory()
	{
		return DIR_FS_ADMIN . DIR_WS_CLASSES . 'EasyPopulate/Handlers/';
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
		$file =  DIR_WS_CLASSES . 'EasyPopulate/lib/Import' . ucwords($item_type) . '.php';
		if (!require_once($file)) {
			return false;
		}

		$file = DIR_WS_CLASSES . 'EasyPopulate/lib/Import' . ucwords($name) . '.php';
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
