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
		$classFiles = array('Standard');
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
		$file = self::baseDirectory() . $name . '.php';
		if ($name == 'Standard' || require($file)) {
			$classname = 'EPUpload' . $name;
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
	public $name = 'Standard';
	public $filelayout = array();
	public $itemCount = 0;
	public $transforms = array();

	function __construct($file)
	{
		@ini_set('auto_detect_line_endings',(int)ep_get_config('detect_line_endings'));
		parent::__construct($file);
		$this->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
	}

	public static function defaultConfig()
	{
		$config = array();
		$config['column_delimiter'] = ',';
		$config['column_enclosure'] = '"';
		$config['price_modifier'] = 0;
		$config['image_path_prefix'] = '';
		$config['tax_class_title'] = '';
		return $config;
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
		foreach ($filelayout as &$column) {
			$column = str_replace('v_', '', $column);
		}
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
		parent::rewind();
		$this->next();
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
	 *
	 * @todo transform all other fields that contain numbers, not just attributes
	 */
	public function handleRow(array $olditem)
	{
		$attributes = array();
		$metatags = array();
		$descriptions = array();
		$item = array();
		foreach ($olditem as $key => $value) {
			$column = explode('_', $key);
			switch ($column[0]) {
				case 'attribute':
					if ($column[1] == 'options') {
						if ($column[2] == 'id') {
							$attributes[$column[3]]['id'] = $value; // attribute_options_id_1
						}
						if ($column[2] == 'name') {
							$attributes[$column[3]]['names'][$column[4]] = $value; // attribute_options_name_1_1
						}
					}
					if ($column[1] == 'values') {
						if ($column[2] == 'id') {
							$attributes[$column[3]]['values'][$column[4]]['id'] = $value; //attribute_values_id_1_1
						}
						if ($column[2] == 'name') {
							$attributes[$column[3]]['values'][$column[4]]['names'][$column[5]] = $value; // attribute_values_name_2_3_1
						}
						if ($column[2] == 'price') {
							$attributes[$column[3]]['values'][$column[4]]['price'] = is_numeric($value) ? $value : 0.00;
						}
					}
					break;
				case 'metatags': // only for title,description,keywords
					if (isset($column[2]) && is_numeric($column[2])) {
						$newvalue = $value;
						if (($column[1] == 'keywords') && isset($this->transforms['metatags_keywords']) && empty($newvalue)) {
							$newvalue = $this->transformPlaceHolders($olditem, $this->transforms['metatags_keywords']);
							$newvalue = trim(strip_tags($newvalue));
						}

						if (empty($newvalue)) break;
						$metatags[$column[2]][$column[1]] = $newvalue; //indexed by language_id
						break;
					}
				case 'products':
					if (in_array($column[1], array('name', 'description', 'url', 'short'))) {
						if ($column[1] == 'short') $column[1] = 'short_desc';
						$descriptions[$column[2]][$column[1]] = $value;
					} // fall through for the rest
				default:
					$item[$key] = $value;
					break;
			}
		}
		$item['metatags'] = $metatags;
		$item['attributes'] = $attributes;
		$item['descriptions'] = $descriptions;

		if ((trim($item['products_quantity']) == '') || !isset($item['products_quantity'])) {
			$item['v_products_quantity'] = 0;
		}
		if ((trim($item['products_image']) == '') || !isset($item['products_image'])) {
			$item['products_image'] = PRODUCTS_IMAGE_NO_IMAGE;
		} else {
			$item['products_image'] = $this->imagePathPrefix . $item['products_image'];
		}
		if (empty($item['products_quantity_order_min']) || !isset($item['products_quantity_order_min'])) {
			$item['products_quantity_order_min'] = 1;
		}
		if (empty($item['products_quantity_order_units']) || !isset($item['products_quantity_order_units'])) {
			$item['products_quantity_order_units'] = 1;
		}

		return $item;
	}

	/**
	 * Transform {} placeholders to the field value
	 *
	 * If v_products_name_1 is foo, then it will transform {products_name_1} to foo
	 *
	 * @param mixed array of values to search
	 * @param string string in which to replace search values
	 * @return string
	 */
	protected function transformPlaceHolders(array $search, $replace)
	{
		return preg_replace("/\{([^\{]{1,100}?)\}/e", '$search[$1]', $replace);
	}


	protected function removeMissingProducts()
	{
		global $db;

		$query = "SELECT * FROM " . TABLE_EASYPOPULATE_FEEDS . " WHERE name = '" . $this->name . "'";

		$result = ep_query($query);
		$row = mysql_fetch_array($result, MYSQL_ASSOC);
		$lastProductIds = unserialize($row['last_run_data']);
		if (!empty($lastProductIds)) {
			$diff = array_diff($lastProductIds, $this->productIds);
			foreach ($diff as $pid) {
				zen_remove_product($pid);
			}
		}

		$data = array();
		$data['last_run_data'] = serialize(array_unique($this->productIds));
		$data['modified'] = 'NOW()';
		$where = 'id = ' . $row['id'];
		$query = ep_db_modify(DB_PREFIX . 'easypopulate_feeds', $data, 'UPDATE', $where);
		$db->Execute($query);
	}

	public function onFileStart()
	{
	}

	/**
	 * Do something when the item is finished
	 *
	 * @todo think about this function signature
	 */
	public function onItemFinish($productId, $productModel)
	{
		$this->itemCount++;
	}

	/**
	 * do something when the file is finished processing
	 */
	public function onFileFinish()
	{
	}
}
?>