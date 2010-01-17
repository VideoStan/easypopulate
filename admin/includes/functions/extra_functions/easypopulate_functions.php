<?php
/**
 * EasyPopulate functions 
 *
 * A set of utility functions used by the EasyPopulate admin page
 *
 * @package easypopulate
 * @author langer
 * @copyright ????-2009
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Publice License (v2 only)
 * @todo document these functions
 * @todo handle tab and other characters that need to be escaped for EASYPOPULATE_CONFIG_COLUMN_DELIMITER
 */

if (!function_exists('fputcsv')) {
    /**
     * Add csv formatted line to a file
     *
     * required for PHP < 5.1.0
     * @author phazei??
     * @todo find out the original author or remove it
     */
    function fputcsv(&$handle, $fields = array(), $delimiter = ',', $enclosure = '"') {
        $str = '';
        $escape_char = '\\';
        foreach ($fields as $value) {
            settype($value, 'string');
            if (strpos($value, $delimiter) !== false ||
                strpos($value, $enclosure) !== false ||
                strpos($value, "\n") !== false ||
                strpos($value, "\r") !== false ||
                strpos($value, "  ") !== false ||
                strpos($value, ' ') !== false) {

                $str2 = $enclosure;
                $escaped = 0;
                $len = strlen($value);
                for ($i=0;$i<$len;$i++) {
                    if ($value[$i] == $escape_char) {
                        $escaped = 1;
                    } else if (!$escaped && $value[$i] == $enclosure) {
                        $str2 .= $enclosure;
                    } else {
                        $escaped = 0;
                    }
                    $str2 .= $value[$i];
                }
                $str2 .= $enclosure;
                $str .= $str2.$delimiter;
            } else {
                $str .= $value.$delimiter;
            }
        }
        $str = substr($str,0,-1);
        $str .= "\n";
        return fwrite($handle, $str);
    }
}

function ep_get_uploaded_file($filename) {
	if (isset($_FILES[$filename])) {
		$uploaded_file = array('name' => $_FILES[$filename]['name'],
		'type' => $_FILES[$filename]['type'],
		'size' => $_FILES[$filename]['size'],
		'tmp_name' => $_FILES[$filename]['tmp_name']);
	} elseif (isset($_POST[$filename])) {
		$uploaded_file = array('name' => $_POST[$filename],
		);
	} else {
		$uploaded_file = array('name' => $GLOBALS[$filename . '_name'],
		'type' => $GLOBALS[$filename . '_type'],
		'size' => $GLOBALS[$filename . '_size'],
		'tmp_name' => $GLOBALS[$filename]);
	}
	return $uploaded_file;
}

// the $filename parameter is an array with the following elements:
// name, type, size, tmp_name
function ep_copy_uploaded_file($filename, $target) {
	if (substr($target, -1) != '/') $target .= '/';
	$target .= $filename['name'];
	move_uploaded_file($filename['tmp_name'], $target);
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

function print_el($item2) {
	//$output_display = " | " . substr(strip_tags($item2), 0, 10);
	$output_display = substr(strip_tags($item2), 0, 10) . " | ";
	return $output_display;
}

function print_el1($item2) {
	$output_display = sprintf("| %'.4s ", substr(strip_tags($item2), 0, 80));
	return $output_display;
}

function smart_tags($string,$tags,$crsub,$doit) {
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

function write_debug_log($string) {
	global $ep_debug_log_path;
	$logFile = $ep_debug_log_path . 'ep_debug_log.txt';
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

function install_easypopulate() {
	global $db;
	$db->Execute("INSERT INTO " . TABLE_CONFIGURATION_GROUP . " VALUES ('', 'Easy Populate', 'Config options for Easy Populate', '1', '1')");
	$group_id = mysql_insert_id();
	$db->Execute("UPDATE " . TABLE_CONFIGURATION_GROUP . " SET sort_order = " . $group_id . " WHERE configuration_group_id = " . $group_id);
	$query = "INSERT INTO " . TABLE_CONFIGURATION . " VALUES
		('', 'Column Delimiter', 'EASYPOPULATE_CONFIG_COLUMN_DELIMITER', ',', 'Single character used to separate fields'," . $group_id . ", 0, NULL, now(), NULL, NULL),
		('', 'Column Enclosure', 'EASYPOPULATE_CONFIG_COLUMN_ENCLOSURE', '\"', 'Single character used to enclose fields'," . $group_id . ", 0, NULL, now(), NULL, NULL),
		('', 'Uploads Directory', 'EASYPOPULATE_CONFIG_TEMP_DIR', 'tempEP/', 'Name of directory for your uploads (default: tempEP/).', " . $group_id . ", '0', NULL, now(), NULL, NULL),
		('', 'Upload File Date Format', 'EASYPOPULATE_CONFIG_FILE_DATE_FORMAT', 'm-d-y', 'Choose order of date values that corresponds to your uploads file, usually generated by MS Excel. Raw dates in your uploads file (Eg 2005-09-26 09:00:00) are not affected, and will upload as they are.', " . $group_id . ", '1', NULL, now(), NULL, 'zen_cfg_select_option(array(\"m-d-y\", \"d-m-y\", \"y-m-d\"),'),
		('', 'Default Raw Time', 'EASYPOPULATE_CONFIG_DEFAULT_RAW_TIME', '09:00:00', 'If no time value stipulated in upload file, use this value. Useful for ensuring specials begin after a specific time of the day (default: 09:00:00)', " . $group_id . ", '2', NULL, now(), NULL, NULL),
		('', 'Split File On # Records', 'EASYPOPULATE_CONFIG_SPLIT_MAX', '300', 'Default number of records for split-file uploads. Used to avoid timeouts on large uploads (default: 300).', " . $group_id . ", '3', NULL, now(), NULL, NULL),
		('', 'Maximum Category Depth', 'EASYPOPULATE_CONFIG_MAX_CATEGORY_LEVELS', '7', 'Maximum depth of categories required for your store. Is the number of category columns in downloaded file (default: 7).', " . $group_id . ", '4', NULL, now(), NULL, NULL),
		('', 'Upload/Download Prices Include Tax', 'EASYPOPULATE_CONFIG_PRICE_INC_TAX', 'false', 'Choose to include or exclude tax, depending on how you manage prices outside of Zen Cart.', " . $group_id . ", '5', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
		('', 'Make Zero Qty Products Inactive', 'EASYPOPULATE_CONFIG_ZERO_QTY_INACTIVE', 'false', 'When uploading, make the status Inactive for products with zero qty (default: false).', " . $group_id . ", '6', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
		('', 'Smart Tags Replacement of Newlines', 'EASYPOPULATE_CONFIG_SMART_TAGS', 'true', 'Allows your description fields in your uploads file to have carriage returns and/or new-lines converted to HTML line-breaks on uploading, thus preserving some rudimentary formatting (default: true).', " . $group_id . ", '7', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
		('', 'Advanced Smart Tags', 'EASYPOPULATE_CONFIG_ADV_SMART_TAGS', 'false', 'Allow the use of complex regular expressions to format descriptions, making headings bold, add bullets, etc. Configuration is in ADMIN/easypopulate.php (default: false).', " . $group_id . ", '8', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
		('', 'Debug Logging', 'EASYPOPULATE_CONFIG_DEBUG_LOGGING', 'true', 'Allow Easy Populate to generate an error log on errors only (default: true)', " . $group_id . ", '9', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
		('', 'Custom Products Fields', 'EASYPOPULATE_CONFIG_CUSTOM_FIELDS', '', 'Enter a comma seperated list of fields to be automatically added to import/export file(ie: products_length, products_width). Please make sure field exists in PRODUCTS table.', " . $group_id . ", '10', NULL, now(), NULL, NULL)
		";
	$db->Execute($query);
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
	return true;
}

function ep_chmod_check($tempdir) {
	global $messageStack;
	
	if (!@file_exists(DIR_FS_CATALOG . $tempdir . ".")) {
		// directory does not exist, or may be unwritable
		@chmod(DIR_FS_CATALOG . $tempdir, 0700); // attempt to make writable - supress error as dir may not exist..
		if (!@file_exists(DIR_FS_CATALOG . $tempdir . ".")) {
			// still can't see it... let's try chmod 777
			@chmod(DIR_FS_CATALOG . $tempdir, 0777); // attempt to make chmod 777 - supress error as dir may not exist..
			if (!@file_exists(DIR_FS_CATALOG . $tempdir . ".")) {
				// still can't see it, so it is probably not there, or is windows server..
				$messageStack->add(sprintf(EASYPOPULATE_MSGSTACK_TEMP_FOLDER_MISSING, $tempdir, DIR_FS_CATALOG), 'warning');
				$chmod_check = false;
			} else {
				// succeeded only with chmod 777 - add msg to ensure index.html is included to prevent file browsing
				$messageStack->add(EASYPOPULATE_MSGSTACK_TEMP_FOLDER_PERMISSIONS_SUCCESS_777, 'success');
				$chmod_check = true;
			}
		} else {
			// we successfully changed to writable @ chmod 700
			$messageStack->add(EASYPOPULATE_MSGSTACK_TEMP_FOLDER_PERMISSIONS_SUCCESS, 'success');
			$chmod_check = true;
		}
	} else {
		$chmod_check = true;
	}
	return $chmod_check;
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
