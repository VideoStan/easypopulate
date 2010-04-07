<?php
/**
 * EasyPopulate functions
 *
 * A set of utility functions used by EasyPopulate
 *
 * @package easypopulate
 * @author langer
 * @author John William Robeson Jr <johnny@localmomentum.net>
 * @author see history.txt
 * @copyright 2002-2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 * @todo document these functions
 */
if (!defined('EASYPOPULATE_CONFIG_IMPORT_HANDLER')) define('EASYPOPULATE_CONFIG_IMPORT_HANDLER', 'Standard');
define('EASYPOPULATE_VERSION', '3.9.5');
/**
 * EasyPopulate extra configuration
 * @todo move these defines elsewhere
 */

define('EASYPOPULATE_CONFIG_COLUMN_DELIMITERS', serialize(
	array(',', 'tab', '|', ':', ';', '^')
));
define('EASYPOPULATE_CONFIG_SMART_TAGS_LIST', serialize(
	array("\r\n|\r|\n" => '<br />')
));

/**
 * Configure Advanced Smart Tags - activated/de-activated in Zencart admin
 *
 * @todo move this somewhere that doesn't require the user to edit this file to make upgrades easier
 *
 * Only activate advanced smart tags if you really know what you are doing, and understand regular expressions.
 * Disable if things go awry.
 * If you wish to add your own smart-tags below, please ensure that you understand the following:
 *
 * 1) ensure that the expressions you use avoid repetitive behaviour from one
 *    upload to the next using  existing data, as you may end up with this sort of thing:
 *   <strong><strong><strong><strong>thing</strong></strong></strong></strong> ...etc
 *   for each update. This is caused for each output that qualifies as an input for any expression..
 * 2) remember to place the tags in the order that you want them to occur, as
 *    each is done in turn and may remove characters you rely on for a later tag
 * 3) the smart_tags function is executed after this array is used,
 *   so you have all of your carriage-returns and line-breaks to play with below
 * 4) make sure you escape the following metacharacters if you are using them a
 *   s string literals: ^  $  \  *  +  ?  (  )  |  .  [  ]  / etc..
 * Uncomment the examples that you want to use
 * for regex help see: http://www.quanetic.com/regex.php or http://www.regular-expressions.info
 */
define('EASYPOPULATE_CONFIG_ADV_SMART_TAGS_LIST', serialize(
	array(
	// replaces "Description:" at beginning of new lines with <br /> and same in bold
	//"\r\nDescription:|\rDescription:|\nDescription:" => '<br /><strong>Description:</strong>',

	// replaces at beginning of description fields "Description:" with same in bold
	//"^Description:" => '<strong>Description:</strong>',

	// just make "Description:" bold wherever it is...must use both lines to prevent duplicates!
	//"<strong>Description:<\/strong>" => 'Description:',
	//"Description:" => '<strong>Description:</strong>',

	// replaces "Specification:" at beginning of new lines with <br /> and same in bold.
	//"\r\nSpecifications:|\rSpecifications:|\nSpecifications:" => '<br /><strong>Specifications:</strong>',

	// replaces at beginning of descriptions "Specifications:" with same in bold
	//"^Specifications:" => '<strong>Specifications:</strong>',

	// just make "Specifications:" bold wherever it is...must use both lines to prevent duplicates!
	//"<strong>Specifications:<\/b>" => 'Specifications:',
	//"Specifications:" => '<strong>Specifications:</strong>',

	// replaces in descriptions any asterisk at beginning of new line with a <br /> and a bullet.
	//"\r\n\*|\r\*|\n\*" => '<br />&bull;',

	// replaces in descriptions any asterisk at beginning of descriptions with a bullet.
	//"^\*" => '&bull;',

	// returns/newlines in description fields replaced with space, rather than <br /> further below
	//"\r\n|\r|\n" => ' ',

	// the following should produce paragraphs between double breaks, and line breaks for returns/newlines
	//"^<p>" => '', // this prevents duplicates
	//"^" => '<p>',
	//"^<p style=\"desc-start\">" => '', // this prevents duplicates
	//"^" => '<p style="desc-start">',
	//"<\/p>$" => '', // this prevents duplicates
	//"$" => '</p>',
	//"\r\n\r\n|\r\r|\n\n" => '</p><p>',
	// if not using the above 5(+2) lines, use the line below instead..
	//"\r\n\r\n|\r\r|\n\n" => '<br /><br />',
	//"\r\n|\r|\n" => '<br />',

	// ensures "Description:" followed by single <br /> is fllowed by double <br />
	//"<strong>Description:<\/b><br \/>" => '<br /><strong>Description:</strong><br /><br />',
	)
));

require_once DIR_WS_CLASSES . 'EasyPopulate.php';

function ep_handle_uploaded_file($file)
{
	$target = '';
	$temp_path = ep_get_config('temp_path');
	if (is_array($file) && !empty($file)) {
		if (is_uploaded_file($file['tmp_name'])) {
			$target = $file['name'];
			move_uploaded_file($file['tmp_name'], $temp_path . $target);
		}
	}
	return $target;
}

function ep_get_upload_error($error_code = 0)
{
	switch ($error_code) {
		case UPLOAD_ERR_OK:
			return 'The file was successfully uploaded';
		case UPLOAD_ERR_INI_SIZE:
			return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
		case UPLOAD_ERR_FORM_SIZE:
				return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
		case UPLOAD_ERR_PARTIAL:
				return 'The file was only partially uploaded';
		case UPLOAD_ERR_NO_FILE:
				return 'No file was uploaded';
		case UPLOAD_ERR_NO_TMP_DIR:
				return 'System (not EasyPopulate) temp directory was not found';
		case UPLOAD_ERR_CANT_WRITE:
				return 'Failed to write file to disk';
		case UPLOAD_ERR_EXTENSION:
				return 'File upload stopped by extension';
		default:
				return 'Unknown upload error';
	}
}

function ep_set_error($field = '', $error = '')
{
	if (empty($field)) return;
	$_SESSION['easypopulate']['errors'][$field] = $error;
	return true;
}

function ep_get_error($field = '')
{
	if (!empty($field) && isset($_SESSION['easypopulate']['errors'][$field])) {;
		return $_SESSION['easypopulate']['errors'][$field];
	}
	return '';
}

/**
 * Get bytes from K/M/G sizes
 *
 * echo ini_get('post_max_size'); // 8M
 * echo ep_get_bytes('8M'); // 8388608
 *
 * @param string $val number with g/k/m suffix
 * @return int bytes from $val
 */
function ep_get_bytes($val)
{
	$val = trim($val);
	$unit = strtolower(substr($val,strlen($val/1),1));
	switch($unit) {
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
		case 'k':
			$val *= 1024;
	}
	return $val;
}

function ep_get_tax_class_titles()
{
	$result = mysql_query('SELECT tax_class_title FROM ' . TABLE_TAX_CLASS);
	$titles = array();
	while($row = mysql_fetch_array($result)) {
		$titles[] = $row['tax_class_title'];
	}
	return $titles;
}

/**
 * Write debugging information to a log file
 *
 * @param string $string string to write to the log file
 * @param string $type type of log to write
 * @todo use a log class
 */
function write_debug_log($string, $type = 'debug')
{
	static $fopenFlags = 'w';
	$logFile = ep_get_config('debug_log_path') . 'log_' . $type . '.txt';
	$fp = fopen($logFile, $fopenFlags);
	fwrite($fp, $string);
	fclose($fp);
	$fopenFlags = 'ab';
	return;
}

function ep_query($query, $log = false)
{
	global $ep_stack_sql_error;
	$result = mysql_query($query);
	if (mysql_errno()) {
		$ep_stack_sql_error = true;
		if (ep_get_config('ep_debug_logging')) {
			// @todo langer - will add time & date..
			$string = "MySQL error ".mysql_errno().": ".mysql_error()."\nWhen executing:\n$query\n";
			write_debug_log($string, 'sql_errors');
		}
	} elseif (ep_get_config('log_queries') || $log) {
		$string = "MySQL PASSED\nWhen executing:\n$query\n";
		write_debug_log($string, 'queries');
	}
	return $result;
}

/**
 * Create a SQL query for INSERT and UPDATE
 *
 * Inspired by zen_db_perform
 *
 * @param string $table
 * @param array $data list of column => value mappings
 * @param string $action INSERT|UPDATE
 * @param string $parameters parameters to pass to the WHERE
 * @return string
 * @todo should we use ep_query here?
 * @todo use bindvars here
 */
function ep_db_modify($table, $data, $action = 'INSERT', $parameters = '')
{
	$action = strtoupper($action);
	if ($action == 'INSERT') {
		$query = 'INSERT INTO ' . $table . ' SET';
	} elseif ($action == 'UPDATE') {
		$query = 'UPDATE ' . $table . ' SET ';
	} else {
		return '';
	}

	if (!is_array($data)) return '';

	$mysql_functions= array('CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()',
	'NOW()');
	foreach ($data as $column => $value) {
		if (in_array(strtoupper($value), $mysql_functions) || strtoupper($value) == 'NULL' || is_numeric($value)) {
			$query .= " $column = $value , ";
		} else {
			$query .= " $column = '" . zen_db_input($value) . "' , ";
		}
	}
	// Chop off the ') '
	$query = substr($query, 0, -2);
	if (!empty($parameters)) $query .=' WHERE ' . $parameters;

	return $query;
}

/**
 * Install Easy Populate configuration entries
 *
 * @todo do something with the db errors
 */
function install_easypopulate()
{
	global $db;
	$data = array();
	$data['configuration_group_id'] = '';
	$data['configuration_group_title'] = 'Easy Populate';
	$data['configuration_group_description'] = 'Configuration options for Easy Populate';

	$query = ep_db_modify(TABLE_CONFIGURATION_GROUP, $data, 'INSERT');
	$db->Execute($query);
	$group_id = mysql_insert_id();
	$data = array('sort_order' => $group_id);
	$query = ep_db_modify(TABLE_CONFIGURATION_GROUP, $data, 'UPDATE', "configuration_group_id = $group_id");
	$db->Execute($query);

	$delimiters = unserialize(EASYPOPULATE_CONFIG_COLUMN_DELIMITERS);

	$entries = array();
	$entries[] = array('title' => 'Uploads Directory',
							'key' => 'EASYPOPULATE_CONFIG_TEMP_DIR',
							'value' => 'tempEP/',
							'description' => 'Name of directory for your uploads (default: tempEP/).');
	/*$entries[] = array('title' => 'Default Upload File Format',
							'key' => 'EASYPOPULATE_CONFIG_IMPORT_HANDLER',
							'value' => 'Standard',
							'description' => 'Import File Handler (default: Standard).',
							'set_function' => 'zen_cfg_select_option(EPFileUploadFactory::find(),');*/
	$entries[] = array('title' => 'Upload File Date Format',
							'key' => 'EASYPOPULATE_CONFIG_FILE_DATE_FORMAT',
							'value' => 'm-d-y',
							'description' => 'Choose order of date values that corresponds to your uploads file, usually generated by MS Excel. Raw dates in your uploads file (Eg 2005-09-26 09:00:00) are not affected, and will upload as they are.',
							'set_function' => 'zen_cfg_select_option(array(\"m-d-y\", \"d-m-y\", \"y-m-d\"),');
	$entries[] = array('title' => 'Default Raw Time',
							'key' => 'EASYPOPULATE_CONFIG_DEFAULT_RAW_TIME',
							'value' => '09:00:00',
							'description' => 'If no time value stipulated in upload file, use this value. Useful for ensuring specials begin after a specific time of the day (default: 09:00:00)');
	$entries[] = array('title' => 'Split File On # Records',
							'key' => 'EASYPOPULATE_CONFIG_SPLIT_MAX',
							'value' => '300',
							'description' => 'Default number of records for split-file uploads. Used to avoid timeouts on large uploads (default: 300)');
	$entries[] = array('title' => 'Maximum Category Depth',
							'key' => 'EASYPOPULATE_CONFIG_MAX_CATEGORY_LEVELS',
							'value' => '7',
							'description' => 'Maximum depth of categories required for your store. Is the number of category columns in downloaded file (default: 7)');
	$entries[] = array('title' => 'Upload/Download Prices Include Tax',
							'key' => 'EASYPOPULATE_CONFIG_PRICE_INC_TAX',
							'value' => 'false',
							'description' => 'Choose to include or exclude tax, depending on how you manage prices outside of Zen Cart',
							'set_function' => 'zen_cfg_select_option(array(\"true\", \"false\"),');
	$entries[] = array('title' => 'Make Zero Quantity Products Inactive',
							'key' => 'EASYPOPULATE_CONFIG_ZERO_QTY_INACTIVE',
							'value' => 'false',
							'description' => 'When uploading, make the status Inactive for products with zero quantity (default: false)',
							'set_function' => 'zen_cfg_select_option(array(\"true\", \"false\"),');
	$entries[] = array('title' => 'Smart Tags Replacement of Newlines',
							'key' => 'EASYPOPULATE_CONFIG_SMART_TAGS',
							'value' => 'true',
							'description' => 'Allows your description fields in your uploads file to have carriage returns and/or new-lines converted to HTML line-breaks on uploading, thus preserving some rudimentary formatting (default: true)',
							'set_function' => 'zen_cfg_select_option(array(\"true\", \"false\"),');
	$entries[] = array('title' => 'Advanced Smart Tags',
							'key' => 'EASYPOPULATE_CONFIG_ADV_SMART_TAGS',
							'value' => 'false',
							'description' => 'Allow the use of complex regular expressions to format descriptions, making headings bold, add bullets, etc. Configuration is in \'admin/includes/extra_datafiles/easypopulate_config.php\' (default: false)',
							'set_function' => 'zen_cfg_select_option(array(\"true\", \"false\"),');
	$entries[] = array('title' => 'Log Errors',
							'key' => 'EASYPOPULATE_CONFIG_DEBUG_LOGGING',
							'value' => 'true',
							'description' => 'Log Errors  (default: true)',
							'set_function' => 'zen_cfg_select_option(array(\"true\", \"false\"),');
	$entries[] = array('title' => 'Log All Queries',
							'key' => 'EASYPOPULATE_CONFIG_LOG_QUERIES',
							'value' => 'false',
							'description' => 'Log all SQL queries - useful for debugging (default: false)',
							'set_function' => 'zen_cfg_select_option(array(\"true\", \"false\"),');
	$entries[] = array('title' => 'Custom Products Fields',
							'key' => 'EASYPOPULATE_CONFIG_CUSTOM_FIELDS',
							'value' => '',
							'description' => 'Enter a comma separated list of fields to be automatically added to import/export file(ie: products_length, products_width). Please make sure field exists in PRODUCTS table');
	$entries[] = array('title' => 'Detect Line Endings',
							'key' => 'EASYPOPULATE_CONFIG_DETECT_LINE_ENDINGS',
							'value' => 'true',
							'description' => 'Detect whether lines end with Mac/DOS/Unix line endings. See the <a target="_blank" href="http://php.net/manual/filesystem.configuration.php#ini.auto-detect-line-endings">PHP Manual</a> for more details(Default: true)',
							'set_function' => 'zen_cfg_select_option(array(\"true\", \"false\"),');
	$entries[] = array('title' => 'File Processing Time Limit',
							'key' => 'EASYPOPULATE_CONFIG_TIME_LIMIT',
							'value' => '1200',
							'description' => '(In Seconds) You can change this if your script is taking too long to process. This functionality may be not always be enabled by your server administrator (Default: 1200)');
	$entries[] = array('title' => 'Easy Populate Version',
							'key' => 'EASYPOPULATE_CONFIG_VERSION',
							'value' => '3.9.5',
							'description' => 'Easy Populate version. DO NOT TOUCH!!!');
	$count = 1;
	foreach($entries as $entry) {
		$data = array();
		if (!isset($entry['set_function'])) $entry['set_function'] = 'NULL';
		if (!isset($entry['use_function'])) $entry['use_function'] = 'NULL';
		$data['configuration_title'] = $entry['title'];
		$data['configuration_key'] = $entry['key'];
		$data['configuration_value'] = $entry['value'];
		$data['configuration_description'] = $entry['description'];
		$data['configuration_group_id'] = $group_id;
		$data['sort_order'] = $count;
		$data['last_modified'] = 'NOW()';
		$data['date_added'] = 'NOW()';
		$data['set_function'] = $entry['set_function'];
		$data['use_function'] = $entry['use_function'];
		$query = ep_db_modify(TABLE_CONFIGURATION, $data, 'INSERT');
		$db->Execute($query);
		$count++;
	}

	$query = 'CREATE TABLE IF NOT EXISTS ' . TABLE_EASYPOPULATE_FEEDS . ' (
				id int(3) NOT NULL AUTO_INCREMENT,
				name varchar(64),
				config text,
				last_run_data text,
				created datetime,
				modified datetime,
				PRIMARY KEY (id)
				)';
	$db->Execute($query);
}

/**
 * Remove Easy Populate configuration entries
 */
function remove_easypopulate()
{
	global $db;

	$sql = "SELECT configuration_group_id
		FROM " . TABLE_CONFIGURATION_GROUP . "
		WHERE configuration_group_title = 'Easy Populate'";

	$result = ep_query($sql);
	if (mysql_num_rows($result)) {
		$ep_groups =  mysql_fetch_array($result);
		foreach ($ep_groups as $ep_group) {
			$db->Execute("DELETE FROM " . TABLE_CONFIGURATION_GROUP . "
			WHERE configuration_group_id = '" . (int)$ep_group . "'");
			$db->Execute("DELETE FROM " . TABLE_CONFIGURATION . "
			WHERE configuration_group_id = '" . $ep_group . "'");
		}
	}
	$db->Execute('DROP TABLE IF EXISTS ' . TABLE_EASYPOPULATE_FEEDS);

	return true;
}

function ep_update_handlers()
{
	global $db;

	$query = "SELECT name FROM " . TABLE_EASYPOPULATE_FEEDS;
	$result = ep_query($query);

	$handlers_db = array();
	while ($row = mysql_fetch_array($result)) {
		$handlers_db[] = $row['name'];
	}

	$handlers = EPFileUploadFactory::find();
	foreach ($handlers as $handler) {
		if (in_array($handler,$handlers_db)) continue;
		$className = EPFileUploadFactory::get($handler);
		$config = call_user_func_array(array($className, 'defaultConfig'), array());
		$data = array();
		$data['name'] = $handler;
		$data['config'] = json_encode($config);
		$data['last_run_data'] = json_encode(array());
		$data['modified'] = 'NOW()';
		$data['created'] = 'NOW()';
		$query = ep_db_modify(TABLE_EASYPOPULATE_FEEDS, $data, 'INSERT');
		$db->Execute($query);
	}
}
/**
 * Get one or all config vars
 *
 * @param string $var get a specific config var (optional)
 * @return mixed config vars or a single var
 */
function ep_get_config($var = NULL)
{
	static $config = array();
	if (!empty($config)) {
		return !empty($var) ? $config[$var] : $config;
	}

	$config['column_delimiters'] = unserialize(EASYPOPULATE_CONFIG_COLUMN_DELIMITERS);
	// @todo do we actually need this if we can query the qty discount table for the MAX() value?
	// If so, we need to put it in the installer
	$config['max_qty_discounts'] = 6;
	$config['ep_date_format'] = EASYPOPULATE_CONFIG_FILE_DATE_FORMAT;
	$config['ep_raw_time'] = EASYPOPULATE_CONFIG_DEFAULT_RAW_TIME;
	$config['ep_debug_logging'] = ((EASYPOPULATE_CONFIG_DEBUG_LOGGING == 'true') ? true : false);
	$config['log_queries'] = ((EASYPOPULATE_CONFIG_LOG_QUERIES == 'true') ? true : false);
	$config['maxrecs'] = EASYPOPULATE_CONFIG_SPLIT_MAX;
	$config['price_with_tax'] = ((EASYPOPULATE_CONFIG_PRICE_INC_TAX == 'true') ? true : false);
	$config['max_categories'] = EASYPOPULATE_CONFIG_MAX_CATEGORY_LEVELS;
	$config['strip_smart_tags'] = ((EASYPOPULATE_CONFIG_SMART_TAGS == 'true') ? true : false);
	$config['smart_tags'] = unserialize(EASYPOPULATE_CONFIG_SMART_TAGS_LIST);
	$config['enable_advanced_smart_tags'] = ((EASYPOPULATE_CONFIG_ADV_SMART_TAGS == 'true') ? true : false);
	$config['advanced_smart_tags'] = unserialize(EASYPOPULATE_CONFIG_ADV_SMART_TAGS_LIST);
	$config['detect_line_endings'] = ((EASYPOPULATE_CONFIG_DETECT_LINE_ENDINGS == 'true') ? true : false);
	$config['deactivate_on_zero_qty'] = ((EASYPOPULATE_CONFIG_ZERO_QTY_INACTIVE == 'true') ? true : false);
	// @todo FIXME Currently just works on TABLE_PRODUCTS
	$config['custom_fields'] = explode(',',trim(EASYPOPULATE_CONFIG_CUSTOM_FIELDS,','));
	$config['time_limit'] = EASYPOPULATE_CONFIG_TIME_LIMIT;
	$config['import_handler'] = EASYPOPULATE_CONFIG_IMPORT_HANDLER;
	$tempdir = EASYPOPULATE_CONFIG_TEMP_DIR;
	if (substr($tempdir, -1) != '/') $tempdir .= '/';
	if (substr($tempdir, 0, 1) == '/') $tempdir = substr($tempdir, 1);
	$config['tempdir'] = $tempdir;
	$config['temp_path'] = DIR_FS_CATALOG . $tempdir;
	$config['debug_log_path'] = $config['temp_path'];

	$langcode = zen_get_languages();
	// start array at one, the rest of the code expects it that way
	$config['langcode'] = array_combine(range(1, count($langcode)), array_values($langcode));

	foreach ($config['langcode'] as $value) {
		if ($value['code'] == DEFAULT_LANGUAGE) {
			$epdlanguage_id = $value['id'];
			break;
		}
	}
	$config['epdlanguage_id'] = $epdlanguage_id;

	return ep_get_config($var);
}
?>