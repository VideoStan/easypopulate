<?php
/**
 * EasyPopulate main administrative interface
 *
 * @package easypopulate
 * @author langer
 * @author too many to list, see history.txt
 * @copyright 200?-2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 */
require_once 'includes/application_top.php';
$langdir = DIR_WS_CLASSES . 'EasyPopulate/lang/' . $_SESSION['language'] . '/';
foreach (glob($langdir . '*php') as $langfile) {
	include $langfile;
}

$original_error_level = error_reporting();
error_reporting(E_ALL ^ E_DEPRECATED); // zencart uses functions deprecated in php 5.3
if (!isset($_SESSION['easypopulate'])) {
	$_SESSION['easypopulate'] = array();
}
if (!isset($_SESSION['easypopulate']['errors'])) {
	$_SESSION['easypopulate']['errors'] = array();
}

$output = array();

if (isset($_POST['installer'])) {
	$f = $_POST['installer'] . '_easypopulate';
	$f();
	zen_redirect(zen_href_link('easypopulate.php'));
	//$messageStack->add(EASYPOPULATE_MSGSTACK_INSTALL_SUCCESS, 'success');
}

if (isset($_GET['preset']) && !empty($_GET['preset'])) {
	echo json_encode(EPFileUploadFactory::getConfig($_GET['preset']));
	error_reporting($original_error_level);
	exit();
}

if (isset($_POST['preset']) && !empty($_POST['preset'])) {
	if (isset($_POST['config']) && is_array($_POST['config'])) {
		EPFileUploadFactory::setConfig($_POST['preset'], $_POST['config']);
	}
	error_reporting($original_error_level);
	exit();
}

if (isset($_GET['dltype'])) {
	$dltype = !empty($_GET['dltype']) ? $_GET['dltype'] : 'full';

	$export = new EasyPopulateExport();
	$export->setFormat($dltype);
	$export->run();

	$ep_dlmethod = isset($_GET['download']) ? $_GET['download'] : 'stream';

	if ($ep_dlmethod == 'stream') {
		$export->streamFile();
		error_reporting($original_error_level);
		exit();
	} else {
		$export->saveFile();
		$messageStack->add(sprintf(EASYPOPULATE_MSGSTACK_FILE_EXPORT_SUCCESS, $export->fileName, ep_get_config('temp_path')), 'success');
		zen_redirect(zen_href_link('easypopulate.php'));
	}
}

//*******************************
// UPLOADING OF FILES STARTS HERE
//*******************************
if (isset($_POST['import'])) {
	$config = array();
	$config['import_handler'] = ep_get_config('import_handler');
	if (isset($_POST['import_handler']) && !empty($_POST['import_handler'])) {
		$config['import_handler'] = $_POST['import_handler'];
	}

	$saved_config = EPFileUploadFactory::getConfig($config['import_handler']);
	$config = array_merge($saved_config, $config);

	if (isset($_POST['local_file']) && !empty($_POST['local_file'])) {
		$config['local_file'] = $_POST['local_file'];
	}

	if (isset($_FILES['uploaded_file'])) {
		if (!empty($_FILES['uploaded_file']['type'])) {
			$result_code = $_FILES['uploaded_file']['error'];
			if ($result_code != UPLOAD_ERR_OK) {
				ep_set_error('uploaded_file', ep_get_upload_error($result_code));
				zen_redirect(zen_href_link('easypopulate.php'));
			} else {
				$config['local_file'] = ep_handle_uploaded_file($_FILES['uploaded_file']);
			}
		}
	}
	$config['local_file'] = ep_get_config('temp_path') . $config['local_file'];
	if (isset($_POST['remote_file']) && !empty($_POST['remote_file'])
	&& !empty($config['local_file']) && isset($config['feed_url'])) {
		if(!@copy($config['feed_url'], $config['local_file'])) {
			$error = error_get_last();
			ep_set_error('local_file', sprintf('Unable to save %s to %s because: %s', $config['feed_url'], $config['local_file'], $error['message']));
			zen_redirect(zen_href_link('easypopulate.php'));
		}
	}

	if (isset($_POST['column_delimiter']) && !empty($_POST['column_delimiter'])) {
		$config['column_delimiter'] = $_POST['column_delimiter'];
	}

	if (isset($_POST['column_enclosure']) && !empty($_POST['column_enclosure'])) {
		$config['column_enclosure'] = $_POST['column_enclosure'];
	}

	if (isset($_POST['price_modifier']) && !empty($_POST['price_modifier'])) {
		$config['price_modifier'] = $_POST['price_modifier'];
	}

	if (isset($_POST['image_path_prefix']) && !empty($_POST['image_path_prefix'])) {
		$config['image_path_prefix'] = $_POST['image_path_prefix'];
	}

	if (isset($_POST['tax_class_title']) && !empty($_POST['tax_class_title'])) {
		$config['tax_class_title'] = $_POST['tax_class_title'];
	}

	if (isset($_POST['metatags_keywords']) && !empty($_POST['metatags_keywords'])) {
		$config['metatags_keywords'] = $_POST['metatags_keywords'];
	}

	if (isset($_POST['metatags_description']) && !empty($_POST['metatags_description'])) {
		$config['metatags_description'] = $_POST['metatags_description'];
	}

	if (isset($_POST['metatags_title']) && !empty($_POST['metatags_title'])) {
		$config['metatags_title'] = $_POST['metatags_title'];
	}

	$fileInfo = new SplFileInfo($config['local_file']);

	if (!$fileInfo->isFile()) {
		ep_set_error('local_file', sprintf(EASYPOPULATE_DISPLAY_FILE_NOT_EXIST, $fileInfo->getFileName()));
		zen_redirect(zen_href_link('easypopulate.php'));
	}

	if (!$fileInfo->isReadable()) {
		ep_set_error('local_file', sprintf(EASYPOPULATE_DISPLAY_FILE_OPEN_FAILED, $fileInfo->getFileName()));
		zen_redirect(zen_href_link('easypopulate.php'));
	}

	$import = new EasyPopulateImport($config);
	$output = $import->run($fileInfo);
	$output['info'] = sprintf(EASYPOPULATE_DISPLAY_FILE_SPEC, $fileInfo->getFileName(), $fileInfo->getSize());
}

if (isset($ep_stack_sql_error) &&  $ep_stack_sql_error) $messageStack->add(EASYPOPULATE_MSGSTACK_ERROR_SQL, 'caution');

/**
* this is a rudimentary date integrity check for references to any non-existant product_id entries
* this check ought to be last, so it checks the tasks just performed as a quality check of EP...
* @todo langer  data present in table products, but not in descriptions.. user will need product info, and decide to add description, or delete product
*/
if (!isset($_GET['dross'])) $_GET['dross'] = 'check';
switch ($_GET['dross']) {
	case !empty($GET['dross']): // we can choose a config option: check always, or only on clicking a button
		$dross = EasyPopulateImport::getDross();
		if (!empty($dross)) {
			$messageStack->add(sprintf(EASYPOPULATE_MSGSTACK_DROSS_DETECTED, count($dross), zen_href_link('easypopulate.php', 'dross=delete')), 'caution');
		} else {
			break;
		}
	case 'delete':
		EasyPopulateImport::purgeDross($dross);
		// now check it is really gone...
		$dross = EasyPopulateImport::getDross();
		if (!empty($dross)) {
			$string = "Product debris corresponding to the following product_id(s) cannot be deleted by EasyPopulate:\n";
			foreach ($dross as $products_id) {
				$string .= $products_id . "\n";
			}
			$string .= "It is recommended that you delete this corrupted data using phpMyAdmin.\n\n";
			write_debug_log($string, 'dross');
			$messageStack->add(EASYPOPULATE_MSGSTACK_DROSS_DELETE_FAIL, 'caution');
		} else {
			$messageStack->add(EASYPOPULATE_MSGSTACK_DROSS_DELETE_SUCCESS, 'success');
		}
		break;
}
/**
 * Changes planned for GUI
 * @todo <johnny> process data via xhr method
 * @todo <johnny> show results via xhr method
 * @todo <langer> 1 input field for local and server updating
 * @todo <langer> default to update directly from HDD, with option to upload to temp, or update from temp
 * @todo <langer> List temp files with delete, etc options
 * @todo <langer> Auto detecting of mods - display list of (only) installed mods, with check-box to include in download
 * @todo <langer> may consider an auto-splitting feature if it can be done.
 *     Will detect speed of server, safe_mode etc and determine what splitting level is required (can be over-ridden of course)
 */
// form defaults
$max_file_size = min(ep_get_bytes(ini_get('upload_max_filesize')), ep_get_bytes(ini_get('post_max_size')));
$price_modifier = 0;
$image_path_prefix = '';
$column_delimiter = ',';
$column_enclosure = '"';
$local_file = '';
$tax_class_title = '';
$feed_url = '';
$metatags_keywords = '';
$metatags_description = '';
$metatags_title = '';
if (defined('EASYPOPULATE_CONFIG_VERSION')) { // EasyPopulate is installed
	ep_update_handlers();
	$config = ep_get_config();
	extract($config); // Brings all the configuration variables into the current symbol table
	extract(EPFileUploadFactory::getConfig($import_handler), EXTR_OVERWRITE);
	$chmod_check = is_dir($temp_path) && is_writable($temp_path);
	if (!$chmod_check) {
		ep_set_error('local_file', sprintf(EASYPOPULATE_MSGSTACK_TEMP_FOLDER_MISSING, $temp_path, DIR_FS_CATALOG));
	}
}
include DIR_WS_CLASSES . 'EasyPopulate/views/layout.php';
require(DIR_WS_INCLUDES . 'application_bottom.php');
?>