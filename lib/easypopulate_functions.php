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
/**
 * EasyPopulate extra configuration
 * @todo move these defines elsewhere
 */
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
		$string = $query ."\n";
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

	$config['ep_debug_logging'] = ((EASYPOPULATE_CONFIG_DEBUG_LOGGING == 'true') ? true : false);
	$config['log_queries'] = ((EASYPOPULATE_CONFIG_LOG_QUERIES == 'true') ? true : false);
	$config['time_limit'] = EASYPOPULATE_CONFIG_TIME_LIMIT;
	$config['import_handler'] = EASYPOPULATE_CONFIG_IMPORT_HANDLER;
	$config['smart_tags_list'] = EASYPOPULATE_CONFIG_SMART_TAGS_LIST;
	$config['adv_smart_tags_list'] = EASYPOPULATE_CONFIG_ADV_SMART_TAGS_LIST;
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
			$language = $value['directory'];
			break;
		}
	}
	$config['language'] = $language;
	$config['epdlanguage_id'] = $epdlanguage_id;

	return ep_get_config($var);
}

/**
 * Hacky function to create a select box with multiple data sources
 */
function ep_pull_down_menu_options($options = array())
{
	if (empty($options)) return array();

	$pull_down_options = array();

	if (is_string($options)) {
		// @todo restrict to a common class/function prefix
		if ((false !== strpos($options, '(')) || (false !== strpos($options, '$'))) {
			$options = eval('return ' . $options . ';');
		} else {
			$options = explode(',', $options);
		}
	}

	if (is_null($options)) $options = array();

	foreach ($options as $k => $v) {
		if (is_numeric($k)) {
			$pull_down_options[] = array('id' => $v, 'text' => $v);
		} else {
			$pull_down_options[] = array('id' => $k, 'text' => $v);
		}
	}					
	return $pull_down_options;
}
?>
