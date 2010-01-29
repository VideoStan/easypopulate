<?php
/**
 * EasyPopulate Classes
 *
 * @package easypopulate
 * @author John William Robeson, Jr <johnny>
 * @copyright 2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 */

class EPFileUploadFactory
{
	public static function get($type)
	{
		$file = DIR_FS_ADMIN . DIR_WS_CLASSES . 'easypopulate/' . strtolower($type) . '.php';
		if ($type == 'Standard' || include($file)) {
			$classname = 'EPUpload' . $type;
			return $classname;
		}
	}
}

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
class EPUploadStandard extends SplFileObject
{
	public $filelayout = array();

	function __construct($file)
	{
		$col_delimiter = ep_get_config('col_delimiter');
		$col_enclosure = ep_get_config('col_enclosure');
		@ini_set('auto_detect_line_endings',ep_get_config('detect_line_endings'));

		parent::__construct($file);

		$this->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject:: DROP_NEW_LINE);
		$this->setCsvControl($col_delimiter, $col_enclosure);
	}

	/**
	 *
	 * @todo rework how the caller handles filelayout
	 */
	function getFileLayout()
	{
		$pos = $this->key();
		$this->seek(0);
		$filelayout = $this->current();
		$this->seek($pos);
		$this->filelayout = $this->mapFileLayout($filelayout);
		return $this->filelayout;
	}

	/**
	 * Map csv column header names to column names
	 *
	 * @param array
	 * @return array
	 */
	public function mapFileLayout($filelayout)
	{
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
	 * Rewind to the first data row
	 *
	 * @return void
	 */
	function rewind()
	{
		$this->seek(1);
	}

	/**
	 * Get the current line array indexed by filelayout column names
	 *
	 * The keys are provided by the file handler and the data is padded to match
	 * the size of the filelayout, so we get defined but empty values
	 *
	 * @return array
	 * @todo should the values be an empty string or NULL?
	 */
	function current()
	{
		if (empty($this->filelayout)) return parent::current();
		$row = array_pad(parent::current(), count($this->filelayout), '');
		return array_combine(array_keys($this->filelayout), array_values($row));
	}

	/**
	 * Map row values to columns
	 */
	public function handleRow($item)
	{
		return $item;
	}
}
?>