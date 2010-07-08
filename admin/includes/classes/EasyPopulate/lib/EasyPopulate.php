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
require DIR_WS_CLASSES . 'EasyPopulate/lib/ImportProducts.php';
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
	public static function getConfig($name = NULL)
	{
		global $db;
		$query = "SELECT name, config FROM  " . TABLE_EASYPOPULATE_FEEDS;

		if (!empty($name)) $query .= " WHERE name = '" . zen_db_input($name) . "'";
		$result = $db->Execute($query);

		$configs = array();
		while (!$result->EOF) {
			$method = array(EPFileUploadFactory::get($result->fields['name']), 'defaultConfig');
			$defaultConfig = call_user_func_array($method, array());
			$defaultConfig['name'] = $result->fields['name'];
			$config = json_decode($result->fields['config'], true);
			$configs[] = array_merge($defaultConfig, (array)$config);
			$result->MoveNext();
		}
		if (!empty($name)) $configs = current($configs);
		return $configs;
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

/**
 * CSV file parser
 *
 * The file setup is copied from the main easypopulate code
 *
 * All headings in $filelayout['columnheading'] = columnnumber
 * All values are in $items[$filelayout] = 'value'
 *
 * @todo refactor the easypopulate code so the columnnumber value
 * isn't always required
 *
 * @todo CHECKME the CSV support depends on php > 5.2.0, is this important?
 *       If so, we can subclass $this->current() and call fgetcsv()
 *       and implement setCsvControl
 */
class EasyPopulateCsvFileObject extends SplFileObject
{
	public $filelayout = array();

	public function __construct($file, $mode = 'r')
	{
		parent::__construct($file, $mode);
		$this->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
	}

	/**
	 * Auto detect the line endings of a file (Unix, DOS/Win, Mac)
	 *
	 * Quoting the php manual
	 * http://www.php.net/manual/en/filesystem.configuration.php#ini.auto-detect-line-endings)
	 *
	 * "When turned on, PHP will examine the data read by fgets(),and file()
	 * to see if it is using Unix, MS-Dos or Macintosh line-ending conventions.
	 * This enables PHP to interoperate with Macintosh systems, but defaults to
	 * Off, as there is a very small performance penalty when detecting the EOL
	 * conventions for the first line, and also because people using
	 * carriage-returns as item separators under Unix systems would experience
	 * non-backwards-compatible behaviour."
	 *
	 * @param bool $enable
	 * @return bool
	 */
	public function autoDetectLineEndings($enable = true)
	{
		return @ini_set('auto_detect_line_endings', (int)$enable);
	}

	/**
	 * Rewind to the first data row
	 *
	 * @return void
	 */
	public function rewind()
	{
		parent::rewind();
		$this->next();
	}

	/**
	 * Get the current line array indexed by by the file header
	 *
	 * The keys are provided by the file handler and the data is padded to match
	 * the size of the filelayout, so we get defined but empty values
	 *
	 * @return array
	 * @todo should the values be an empty string or NULL?
	 */
	public function current()
	{
		if (empty($this->filelayout)) return parent::current();
		$row = array_pad(parent::current(), count($this->filelayout), '');
		return array_combine(array_keys($this->filelayout), array_values($row));
	}

	/**
	 * Get the Column Headers
	 */
	public function setFileLayout(array $filelayout = array() , $write = false)
	{
		$this->filelayout = $filelayout;
		if ($write) $this->write($filelayout);
	}

	public function getFormattedFilelayout()
	{
		$filelayout = $this->filelayout;
		foreach ($filelayout as $position => $header) {
			$prepped = preg_replace('/^v_/', '', $header, 1);
			$prepped = ucwords(str_replace('_', ' ', $prepped));
			$filelayout[$position] = $prepped;
		}
		return $filelayout;
	}

	public function getFileLayout()
	{
		if (!empty($this->filelayout)) return $this->filelayout;
		$pos = $this->key();
		$this->seek(0);
		$filelayout = $this->current();
		$this->seek($pos);
		$this->filelayout = $this->mapFileLayout($filelayout);
		return $this->filelayout;
	}

	protected function mapFileLayout(array $filelayout)
	{
		return $filelayout;
	}

	/**
	 * Write CSV record
	 *
	 * This method uses an implementation of fputcsv found
	 * at http://www.php.net/manual/en/function.fputcsv.php#87120
	 *
	 * @param array $fields
	 * @see parent::fwrite()
	 * @todo use native fputcsv when it becomes available
	 * @todo CHECKME make sure this works as well as the native implementation
	 * or try the one at http://php.net/manual/en/function.fputcsv.php#77866
	 */
	public function write(array $fields = array())
	{
		$csvFlags = $this->getCsvControl();
		$delimiter = $csvFlags[0];
		$enclosure = $csvFlags[1];
		$delimiter_esc = preg_quote($delimiter, '/');
		$enclosure_esc = preg_quote($enclosure, '/');

		$output = array();
		foreach ($fields as $field) {
			$output[] = preg_match("/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field) ? (
			$enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure
			) : $field;
		}
		$this->fwrite(join($delimiter, $output) ."\n");
	}

	public function countLines()
	{
		$pos = $this->key();
		$this->seek(0);
		$lineCount = 0;
		while (!$this->eof()) {
			$this->next();
			$lineCount++;
		}
		$this->seek($pos); // Go back to where we were
		return $lineCount;
	}

	/**
	 * Set the delimiter and enclosure character for CSV
	 *
	 * @param string $delimiter
	 * @param string $enclosure
	 * @param string $escape
	 * @todo bring back the escape argument to the parent method when we can depend on a higher php version
	 */
	public function setCsvControl($delimiter = ',', $enclosure = '"', $escape = '\\')
	{
		if ($delimiter == 'tab') $delimiter = "\t";
		parent::setCsvControl($delimiter, $enclosure);
	}
}
?>