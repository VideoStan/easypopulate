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
	public $filelayout;
	public $originalFileLayout = array();
	// use static var instead of constant, since php won't let us use constant arrays
	public static $DELIMITERS = array(',', 'tab', '|', ':', ';', '^');

	protected $headerSize;
	protected $mode; //@todo shouldn't need this

	public function __construct($file, $mode = 'r')
	{
		$this->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
		parent::__construct($file,$mode);
		$this->mode = $mode;
	}

	public function init($columnDelimiter = ',', $columnEnclosure = '"', $detectLineEndings = false)
	{
		$this->setCsvControl($columnDelimiter, stripslashes($columnEnclosure));

		$hasLines = in_array($this->mode, array('r','r+','x','x+')) && ($this->getSize() > 0);
		$isReadable = in_array($this->mode, array('a+','c')) && $this->isFile();
		
		$this->autoDetectLineEndings($detectLineEndings);

		if ($isReadable || $hasLines) {
			$this->getFileLayoutFromFile();
			$this->filelayout = $this->originalFileLayout;
		}
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
	 * Detect CSV delimiter
	 *
	 * @param string file
	 * @return string $character detected CSV delimiter
	 */ 
	public static function detectDelimiter($file , $tries = 10)
	{
		$counts = array();

		foreach (self::$DELIMITERS as $delimiter) {
			$fileObject = new EasyPopulateCsvFileObject($file);
			$fileObject->setCsvControl($delimiter, '"');
			$headerSize = count($fileObject->current());
			foreach (new LimitIterator($fileObject, 1, $tries) as $line) {
				$lineSize = count($line);
    			if (is_array($line) && ($lineSize > 1) && ($headerSize == $lineSize)) {
    				$counts[$delimiter]++;
    			}
			}
		}

		if (empty($counts)) return false;
		$character =  current(array_keys($counts, max($counts)));
		return $character;
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
	 * @todo use $mode instead of $write ?
	 */
	public function setFileLayout($filelayout = array(), $write = false)
	{
		if ($write && is_string($filelayout)) $this->write($filelayout);

		if (is_string($filelayout)) $filelayout = explode(',', $filelayout);

		if (!$write) {
			$filelayout = $this->mapFileLayout($filelayout); // @todo check to make sure this doesn't screw up export
		}
		$this->filelayout = $filelayout;
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

	public function getFileLayoutFromFile()
	{
		if (!empty($this->originalFileLayout)) return $this->originalFileLayout;
		$pos = $this->key();
		$this->seek(0);
		$this->originalFileLayout = $this->current();
		$this->seek($pos);
	}

	public function getFileLayout()
	{
		return $this->filelayout;
	}

	/**
	 * Map file header aliases from their original names to the internal names
	 */
	protected function mapFileLayout(array $filelayout = array())
	{
		foreach ($this->originalFileLayout as $key => $header) {
			if (!isset($filelayout[$key]) || empty($filelayout[$key])) {
				$filelayout[$key] = $header;
			}
		}
		$filelayout = array_flip($filelayout);
		return $filelayout;
	}

	public function fileLayoutValidated($filelayout)
	{
		if ($this->originalFileLayout != $filelayout) return true;
		return false;
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

	/**
	 * Subclass to modify the row in transet
	 *
	 * @param array $row row values indexed by column 
	 * @param return array 
	 */
    public function handleRow(array $row)
	{
		return $row;
	}
}
