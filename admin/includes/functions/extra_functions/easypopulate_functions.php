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

define('EASYPOPULATE_VERSION', '3.9.5');
require_once DIR_WS_CLASSES . 'easypopulate.php';

function ep_handle_uploaded_file($file)
{
	$temp_path = ep_get_config('temp_path');
	if (is_array($file) && !empty($file)) {
		if (is_uploaded_file($file['tmp_name'])) {
			$target = $temp_path . $file['name'];
			move_uploaded_file($file['tmp_name'], $target);
		}
	} else if (is_string($file) && !empty($file) && file_exists($temp_path . $file)) {
		$target = $temp_path . $file;
	} else {
		$target = $file;
	}
	return $target;
}

/**
 * Modifiy a price by a flat price or a percentage
 *
 * @param int $price a price
 * @param mixed $modifier positive or negative value
 *
 * @return int
 */
function ep_modify_price($price , $modifier = 0)
{
	if (strpos($modifier, '%') !== false) {
		$modifier = str_replace('%', '', $modifier);
		$modifier = $price * ((int)$modifier / 100);
	}
	return $price += $modifier;
}

function ep_get_tax_class_rate($tax_class_id) {
	$tax_multiplier = 0;
	$tax_query = mysql_query("select SUM(tax_rate) as tax_rate from " . TABLE_TAX_RATES . " WHERE  tax_class_id = '" . zen_db_input($tax_class_id) . "' GROUP BY tax_priority");
	if (mysql_num_rows($tax_query)) {
		while ($tax = mysql_fetch_array($tax_query)) {
			$tax_multiplier += $tax['tax_rate'];
		}
	}
	return $tax_multiplier;
}

function ep_get_tax_title_class_id($tax_class_title) {
	$classes_query = mysql_query("select tax_class_id from " . TABLE_TAX_CLASS . " WHERE tax_class_title = '" . zen_db_input($tax_class_title) . "'" );
	$tax_class_array = mysql_fetch_array($classes_query);
	$tax_class_id = $tax_class_array['tax_class_id'];
	return $tax_class_id ;
}

/**
 * Print a table row of item values
 *
 * @param string $value
 * @
 */
function print_el($value)
{
	return substr(strip_tags($value), 0, 10);
}

function smart_tags($string,$tags, $doit) {
	if ($doit == true) {
		foreach ($tags as $tag => $new) {
			$tag = '/('.$tag.')/';
			$string = preg_replace($tag,$new,$string);
		}
	}
	// we remove problem characters here anyway as they are not wanted..
	$string = preg_replace("/(\r\n|\n|\r)/", "", $string);
	// $crsub is redundant - may add it again later though..
	return $string;
}

function ep_field_name_exists($tbl,$fld) {
  if (zen_not_null(zen_field_type($tbl,$fld))) {
  	return true;
  } else {
  	return false;
  }
}

function ep_remove_product($product_model) {
  global $db, $ep_debug_logging, $ep_debug_logging_all, $ep_stack_sql_error;

  $sql = "select products_id
                           from " . TABLE_PRODUCTS . "
                           where products_model = '" . zen_db_input($product_model) . "'";
  $products = $db->Execute($sql);

	if (mysql_errno()) {
		$ep_stack_sql_error = true;
		if ($ep_debug_logging == true) {
			// langer - will add time & date..
			$string = "MySQL error ".mysql_errno().": ".mysql_error()."\nWhen executing:\n$sql\n";
			write_debug_log($string);
		}
	} elseif ($ep_debug_logging_all == true) {
		$string = "MySQL PASSED\nWhen executing:\n$sql\n";
		write_debug_log($string);
	}

  while (!$products->EOF) {
    zen_remove_product($products->fields['products_id']);
    $products->MoveNext();
  }
  return;
}

function ep_purge_dross() {
	$dross = ep_get_dross();
	foreach ($dross as $products_id => $langer) {
		zen_remove_product($products_id);
	}
}

function ep_get_dross() {
	global $db;
	$target_tables = array(TABLE_PRODUCTS_DESCRIPTION,
												TABLE_SPECIALS,
												TABLE_PRODUCTS_TO_CATEGORIES,
												TABLE_PRODUCTS_ATTRIBUTES,
												TABLE_FEATURED,
												TABLE_CUSTOMERS_BASKET,
												TABLE_CUSTOMERS_BASKET_ATTRIBUTES,
												TABLE_PRODUCTS_DISCOUNT_QUANTITY);
												// can add others I guess, though this probably catches all possible data debris...
												// reviews uses reviews_id, but if it is in reviews, it is probably detected above anyway
												// This array needs to work with all versions - could break EP on older versions I think.. with each additional table, test on older versions

	$dross = array();
	foreach ($target_tables as $table) {
		//lets check the tables for deleted products
		$sql = "select distinct t.products_id from " . $table . " as t left join " . TABLE_PRODUCTS . " as p on t.products_id = p.products_id where p.products_id is NULL";
		$products = $db->Execute($sql);
		while (!$products->EOF) {
			$dross[$products->fields['products_id']] = 'dross';
			$products->MoveNext();
		}
	}
	// our array has product_id => "dross", so duplicate products simply over-write same in array
	//print_r($dross);
  return $dross;
}

function ep_update_cat_ids() {
  // reset products master categories ID
	global $db;

  $sql = "select products_id from " . TABLE_PRODUCTS;
  $check_products = $db->Execute($sql);
  while (!$check_products->EOF) {

    $sql = "select products_id, categories_id from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id='" . $check_products->fields['products_id'] . "'";
    $check_category = $db->Execute($sql);

    $sql = "update " . TABLE_PRODUCTS . " set master_categories_id='" . $check_category->fields['categories_id'] . "' where products_id='" . $check_products->fields['products_id'] . "'";
    $update_viewed = $db->Execute($sql);

    $check_products->MoveNext();
  }
}

function ep_update_prices() {
	global $db;

  // reset products_price_sorter for searches etc.
  $sql = "select products_id from " . TABLE_PRODUCTS;
  $update_prices = $db->Execute($sql);

  while (!$update_prices->EOF) {
    zen_update_products_price_sorter($update_prices->fields['products_id']);
    $update_prices->MoveNext();
  }
}

function ep_update_attributes_sort_order() {
	global $db;
	$all_products_attributes= $db->Execute("select p.products_id, pa.products_attributes_id from " .
	TABLE_PRODUCTS . " p, " .
	TABLE_PRODUCTS_ATTRIBUTES . " pa " . "
	where p.products_id= pa.products_id"
	);
	while (!$all_products_attributes->EOF) {
	  $count++;
	  //$product_id_updated .= ' - ' . $all_products_attributes->fields['products_id'] . ':' . $all_products_attributes->fields['products_attributes_id'];
	  zen_update_attributes_products_option_values_sort_order($all_products_attributes->fields['products_id']);
	  $all_products_attributes->MoveNext();
	}
}

/**
 * Return the filelayout for attributes
 *
 * @return array
 */
function ep_filelayout_attributes()
{
	$filelayout = array();
	$languages = zen_get_languages();

	$attribute_options_count = 1;
	foreach ($attribute_options_array as $attribute_options_values) {
		$key1 = 'v_attribute_options_id_' . $attribute_options_count;
		$filelayout[] = $key1;

		for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
			$l_id = $languages[$i]['id'];
			$key2 = 'v_attribute_options_name_' . $attribute_options_count . '_' . $l_id;
			$filelayout[] = $key2;
		}

		$attribute_values_query = "SELECT products_options_values_id
											FROM " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . "
											WHERE products_options_id = '" . (int)$attribute_options_values['products_options_id'] . "'
											ORDER BY products_options_values_id";
		$attribute_values_values = ep_query($attribute_values_query);

		$attribute_values_count = 1;
		while ($attribute_values = mysql_fetch_array($attribute_values_values)) {
			$key3 = 'v_attribute_values_id_' . $attribute_options_count . '_' . $attribute_values_count;
			$filelayout[] = $key3;

			$key4 = 'v_attribute_values_price_' . $attribute_options_count . '_' . $attribute_values_count;
			$filelayout[] = $key4;

			for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
				$l_id = $languages[$i]['id'];

				$key5 = 'v_attribute_values_name_' . $attribute_options_count . '_' . $attribute_values_count . '_' . $l_id;
				$filelayout[] = $key5;
			}

			$attribute_values_count++;
		}
		$attribute_options_count++;
	}
	return $filelayout;
}

function write_debug_log($string)
{
	$logFile = ep_get_config('debug_log_path') . 'ep_debug_log.txt';
	$fp = fopen($logFile,'ab');
	fwrite($fp, $string);
	fclose($fp);
	return;
}

function ep_query($query) {
	global $ep_debug_logging, $ep_debug_logging_all, $ep_stack_sql_error;
	$result = mysql_query($query);
	if (mysql_errno()) {
		$ep_stack_sql_error = true;
		if ($ep_debug_logging == true) {
			// langer - will add time & date..
			$string = "MySQL error ".mysql_errno().": ".mysql_error()."\nWhen executing:\n$query\n";
			write_debug_log($string);
		}
	} elseif ($ep_debug_logging_all == true) {
		$string = "MySQL PASSED\nWhen executing:\n$query\n";
		write_debug_log($string);
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
function install_easypopulate() {
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
	$entries[] = array('title' => 'Column Delimiter',
							'key' => 'EASYPOPULATE_CONFIG_COLUMN_DELIMITER',
							'value' => ',',
							'set_function' => 'zen_cfg_select_option(' . var_export($delimiters, true) . ',',
							'description' => 'Single character used to separate fields');
	$entries[] = array('title' => 'Column Enclosure',
							'key' => 'EASYPOPULATE_CONFIG_COLUMN_ENCLOSURE',
							'value' => '"',
							'Single character used to enclose fields');
	$entries[] = array('title' => 'Uploads Directory',
							'key' => 'EASYPOPULATE_CONFIG_TEMP_DIR',
							'value' => 'tempEP/',
							'description' => 'Name of directory for your uploads (default: tempEP/).');
	$entries[] = array('title' => 'Default Upload File Format',
							'key' => 'EASYPOPULATE_CONFIG_IMPORT_HANDLER',
							'value' => 'Standard',
							'description' => 'Import File Handler (default: Standard).',
							'set_function' => 'zen_cfg_select_option(array(EPFileUploadFactory::find(),');
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

	$query = 'CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'easypopulate_feeds (
				id int(3) NOT NULL AUTO_INCREMENT,
				name varchar(64),
				config text,
				last_run_data text,
				created datetime,
				modified datetime,
				PRIMARY KEY (id)
				)';
	$db->Execute($query);
	ep_update_handlers();
}

/**
 * Remove Easy Populate configuration entries
 */
function remove_easypopulate() {
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
	$db->Execute('DROP TABLE ' . DB_PREFIX . 'easypopulate_feeds');
	return true;
}

function ep_update_handlers()
{
	global $db;

	$query = "SELECT name FROM " . DB_PREFIX . "easypopulate_feeds";
	$result = ep_query($query);

	$handlers_db = array();
	while ($row = mysql_fetch_array($result)) {
		$handlers_db[] = $row['name'];
	}

	$handlers = EPFileUploadFactory::find();
	foreach ($handlers as $handler) {
		if (in_array($handler,$handlers_db)) continue;

		$data = array();
		$data['name'] = $handler;
		$data['config'] = serialize(array());
		$data['last_run_data'] = serialize(array());
		$data['modified'] = 'NOW()';
		$data['created'] = 'NOW()';
		$query = ep_db_modify(DB_PREFIX . 'easypopulate_feeds', $data, 'INSERT');
		$db->Execute($query);
	}
}
/**
 * Get all Easy Populate config vars or a single one
 *
 * @param string $config get a specific config var (optional)
 * @return array|string all config vars | single var
 * @todo cache the config array
 */
function ep_get_config($var = '')
{
	$config = array();
	$config['col_delimiter'] = EASYPOPULATE_CONFIG_COLUMN_DELIMITER;
	$config['col_delimiters'] = unserialize(EASYPOPULATE_CONFIG_COLUMN_DELIMITERS);
	$config['col_enclosure']   = EASYPOPULATE_CONFIG_COLUMN_ENCLOSURE;
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
	if (!empty($var)) {
		return $config[$var];
	} else {
		return $config;
	}
}

/**
 * Kills all line breaks and tabs
 *
 * Used for Froogle (Google Products)
 *
 * @param string $line line to kill breaks on
 */
function kill_breaks($line) {
	if (is_array($line)) return array_map('kill_breaks', $line);
	return str_replace(array("\r","\n","\t")," ",$line);
}

/**
* The following functions are for testing purposes only
*/
// available zen functions of use..
/*
function zen_get_category_name($category_id, $language_id)
function zen_get_category_description($category_id, $language_id)
function zen_get_products_name($product_id, $language_id = 0)
function zen_get_products_description($product_id, $language_id)
function zen_get_products_model($products_id)
*/
?>