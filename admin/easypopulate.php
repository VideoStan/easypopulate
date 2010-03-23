<?php
/**
 * EasyPopulate main administrative interface
 *
 * @package easypopulate
 * @author langer
 * @author too many to list, see history.txt
 * @copyright 200?-2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 *
 * @todo <chadd> change v_products_price_as to v_products_price_uom
 */

require_once 'includes/application_top.php';
$original_error_level = error_reporting();
error_reporting(E_ALL ^ E_DEPRECATED); // zencart uses functions deprecated in php 5.3
if (!isset($_SESSION['easypopulate'])) {
	$_SESSION['easypopulate'] = array();
}
if (!isset($_SESSION['easypopulate']['errors'])) {
	$_SESSION['easypopulate']['errors'] = array();
}

$output = array();
$products_with_attributes = false; // langer - this will be redundant after html renovation
$ep_stack_sql_error = false; // function returns true on any 1 error, and notifies user of an error


if (defined('EASYPOPULATE_CONFIG_VERSION')) { // EasyPopulate is installed
	$config = ep_get_config();
	extract($config); // Brings all the configuration variables into the current symbol table

	if ($log_queries) {
		// new blank log file on each page impression for full testing log (too big otherwise!!)
		$fp = fopen($temp_path . 'ep_debug_log.txt', 'w');
		fclose($fp);
	}

	// @todo move this to where the file processing actually takes place
	@set_time_limit($time_limit);
	@ini_set('max_input_time', $time_limit);
	$chmod_check = is_dir($temp_path) && is_writable($temp_path);
	if (!$chmod_check) {
		$messageStack->add(sprintf(EASYPOPULATE_MSGSTACK_TEMP_FOLDER_MISSING, $temp_path, DIR_FS_CATALOG), 'warning');
	}
	$max_file_size = min(ep_get_bytes(ini_get('upload_max_filesize')), ep_get_bytes(ini_get('post_max_size')));

	$price_modifier = 0;
	$image_path_prefix = '';
	$column_delimiter = ',';
	$column_enclosure = '"';
	$local_file = '';
	$tax_class_title = '';
	$feed_url = '';
	ep_update_handlers();
	extract(EPFileUploadFactory::getConfig($import_handler), EXTR_OVERWRITE);
}

if (isset($_POST['installer'])) {
	$f = $_POST['installer'] . '_easypopulate';
	$f();
	zen_redirect(zen_href_link(FILENAME_EASYPOPULATE));
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

	require DIR_WS_CLASSES . 'EasyPopulate/Export.php';
	$export = new EasyPopulateExport($dltype);
	$export->run();

	$ep_dlmethod = isset($_GET['download']) ? $_GET['download'] : 'stream';

	if ($ep_dlmethod == 'stream') {
		$export->streamFile();
		error_reporting($original_error_level);
		exit();
	} else {
		$export->saveFile();
		$messageStack->add(sprintf(EASYPOPULATE_MSGSTACK_FILE_EXPORT_SUCCESS, $export->fileName, ep_get_config('temp_path')), 'success');
		zen_redirect(zen_href_link(FILENAME_EASYPOPULATE));
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

	if (isset($_POST['local_file']) && !empty($_POST['local_file'])) {
		$config['local_file'] = $_POST['local_file'];
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

	if (isset($_POST['transform']) && !empty($_POST['transforms'])) {
		$config['transforms'] = $_POST['transforms'];
	}

	$saved_config = EPFileUploadFactory::getConfig($config['import_handler']);
	$config = array_merge($saved_config, $config);

	$file_location = '';
	if (isset($config['local_file']) && !empty($config['local_file'])) {
		$file_location = ep_handle_uploaded_file($config['local_file']);
	}
	if (isset($_FILES['uploaded_file'])) {
		if (!empty($_FILES['uploaded_file']['type'])) {
			$error = $_FILES['uploaded_file']['error'];
			if ($error > 0) {
				ep_set_error('uploaded_file', ep_get_upload_error($error));
				zen_redirect(zen_href_link(FILENAME_EASYPOPULATE));
			} else {
				$file_location = ep_handle_uploaded_file($_FILES['uploaded_file']);
			}
		}
	}

	$fileInfo = new SplFileInfo($file_location);

	if (!$fileInfo->isFile()) {
		ep_set_error('local_file', sprintf(EASYPOPULATE_DISPLAY_FILE_NOT_EXIST, $file_location));
		zen_redirect(zen_href_link(FILENAME_EASYPOPULATE));
	}

	if (!$fileInfo->isReadable()) {
		ep_set_error('local_file', sprintf(EASYPOPULATE_DISPLAY_FILE_OPEN_FAILED, $file_location));
		zen_redirect(zen_href_link(FILENAME_EASYPOPULATE));
	}

	require DIR_WS_CLASSES . 'EasyPopulate/Import.php';
	$import = new EasyPopulateImport($config);
	$output = $import->run($fileInfo);
	$output['info'] = sprintf(EASYPOPULATE_DISPLAY_FILE_SPEC, $fileInfo->getFileName(), $fileInfo->getSize());
}

if ($ep_stack_sql_error == true) $messageStack->add(EASYPOPULATE_MSGSTACK_ERROR_SQL, 'caution');

/**
* this is a rudimentary date integrity check for references to any non-existant product_id entries
* this check ought to be last, so it checks the tasks just performed as a quality check of EP...
* @todo langer  data present in table products, but not in descriptions.. user will need product info, and decide to add description, or delete product
*/
if (!isset($_GET['dross'])) $_GET['dross'] = NULL;
switch ($_GET['dross']) {
	case 'delete':
		ep_purge_dross();
		// now check it is really gone...
		$dross = ep_get_dross();
		if (zen_not_null($dross)) {
			$string = "Product debris corresponding to the following product_id(s) cannot be deleted by EasyPopulate:\n";
			foreach ($dross as $products_id => $langer) {
				$string .= $products_id . "\n";
			}
			$string .= "It is recommended that you delete this corrupted data using phpMyAdmin.\n\n";
			write_debug_log($string);
			$messageStack->add(EASYPOPULATE_MSGSTACK_DROSS_DELETE_FAIL, 'caution');
		} else {
			$messageStack->add(EASYPOPULATE_MSGSTACK_DROSS_DELETE_SUCCESS, 'success');
		}
		break;
	case 'check': // we can choose a config option: check always, or only on clicking a button
	default:
		$dross = ep_get_dross();
		if (zen_not_null($dross)) {
			$messageStack->add(sprintf(EASYPOPULATE_MSGSTACK_DROSS_DETECTED, count($dross), zen_href_link(FILENAME_EASYPOPULATE, 'dross=delete')), 'caution');
		}
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
?>
<!DOCTYPE html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
	<title><?php echo TITLE; ?> - Easy Populate</title>
	<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
	<link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
	<script language="javascript" type="text/javascript" src="includes/menu.js"></script>
	<script language="javascript" type="text/javascript" src="includes/general.js"></script>
	<script language="javascript" type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>

	<script type="text/javascript">
	/*! jQuery serializeObject - v0.2 - 1/20/2010 http://benalman.com/projects/jquery-misc-plugins/
	* Copyright (c) 2010 "Cowboy" Ben Alman  Dual licensed under the MIT and GPL licenses.*/
	(function($,undefined){
	'$:nomunge';
	$.fn.serializeObject = function(){
		var obj = {};
		$.each( this.serializeArray(), function(i,o){
			var n = o.name, v = o.value;
			obj[n] = obj[n] === undefined ? v
				: $.isArray( obj[n] ) ? obj[n].concat( v )
				: [ obj[n], v ];
		});
		return obj;
	};
	})(jQuery);

	$(document).ready(function() {
		cssjsmenu('navbar');
		$('#hoverJS').attr('disabled', 'disabled');

		$("#installer :button").click(function() {
			$("#installer input[name=installer]").val($(this).attr('name'));
			$("#installer").submit();
		});

		$("#import_handler").change(function() {
			$.getJSON("easypopulate.php?preset=" + $(this).val(), function(json){
				$.each(json, function(k, v){
					$("#" + k).val(unescape(v));
				});
			});
		});

		$("#import_form input[name=setconfig]").click(function() {
			var config = $("#import_form .config").serializeObject();
			var preset = $("#import_form #import_handler").val();
			var vals = { "preset" : preset, "config" : config };
			$.post("easypopulate.php", vals);
		});

		$(".results_table tr").mouseover(function(){
			$(this).addClass("over");
		}).mouseout(function(){
			$(this).removeClass("over");
		});
		$(".results_table tr:nth-child(even)").addClass("alt");

		$("#show_uploaded_files").click(function() {
			$("#uploaded_files").toggle();
		});
	});
	</script>
	<!--@todo: move this css to some other file -->
	<style type="text/css">
	#ep_header {
		margin-top: 5px;
		height: 25px;
	}
	#ep_header h2 {
		display: inline;
	}
	#installer {
		float: right;
		margin-bottom: 0;
	}

	label {
		font-weight: bold;
		width: 22em;
		float: left;
	}

	#uploaded_files {
		display: none;
	}

	.results_table {
		border-collapse: collapse;
		border:1px solid #000;
	}
	.results_table th {
		padding-right: 0.5em;
		background-color: #D7D6CC;
	}
	.results_table tr.fail td.status {
		color: #E68080;
	}
	.results_table tr.success td.status {
		color: #599659;
	}
	td.status {
		font-weight: bold;
	}
	.results_table tr.alt {
		background-color: #E7E6E0;
	}
	.error {
		color: red;
		font-weight: bold;
	}
	</style>
</head>
<body>
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<div id="ep_header">
	<h2>Easy Populate <?php echo EASYPOPULATE_VERSION ?></h2>
	<form id="installer" enctype="multipart/form-data" action="easypopulate.php" method="POST">
		<input type="hidden" name="installer" value="">
		<?php if (defined('EASYPOPULATE_CONFIG_VERSION')) { ?>
		<input type="button" name="remove" value="Remove EasyPopulate">
		<!-- @todo <input type="button" name="upgrade" value="Upgrade"> -->
		<?php // Old version detected ?>
		<?php } else if(defined('EASYPOPULATE_CONFIG_TEMP_DIR') && !defined('EASYPOPULATE_CONFIG_VERSION')) { ?>
			<input type="button" name="remove" value="Remove Old Version">
		<?php } else { ?>
		<span><?php echo EASYPOPULATE_ERROR_NOT_INSTALLED ?></span>
		<input type="button" name="install" value="Install EasyPopulate">
		<?php } ?>
	</form>
</div>

<?php if (defined('EASYPOPULATE_CONFIG_VERSION')) { ?>
<div>
	<form id="import_form" enctype="multipart/form-data" action="easypopulate.php" method="POST">
		<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $max_file_size ?>">
		<input type="hidden" name="import" value="1">
		<fieldset>
			<legend>Import delimited files</legend>
			<div>
			<label for="import_handler">Import File Handler</label>
			<?php $handlers = array();
			foreach (EPFileUploadFactory::find() as $v) {
				$handlers[] = array('id' => $v, 'text' => $v);
			} ?>
			<?php echo zen_draw_pull_down_menu('import_handler', $handlers, $import_handler, 'id="import_handler"'); ?>
			</div>
			<div>
			<label for="uploaded_file">Upload EP File</label>
			<input id="uploaded_file" name="uploaded_file" type="file" size="50">
			<span class="error"><?php echo ep_get_error('uploaded_file'); ?></span>
			</div>
			<div>
			<label for="local_file">Import from Temp Dir (<?php echo $tempdir; ?>)</label>
			<input type="text" class="config" id="local_file" name="local_file" size="50" value="<?php echo $local_file; ?>">
			<span class="error"><?php echo ep_get_error('local_file'); ?></span>
			</div>
			<div>
			<label for="column_delimiter">Column Delimiter</label>
			<?php $delimiters = array();
			foreach (ep_get_config('column_delimiters') as $v) {
				$delimiters[] = array('id' => $v, 'text' => $v);
			} ?>
			<?php echo zen_draw_pull_down_menu('column_delimiter', $delimiters, $column_delimiter, 'class="config" id="column_delimiter"'); ?>
			</div>
			<div>
			<label for="column_enclosure">Column Enclosure</label>
			<input type="text" class="config" id="column_enclosure" name="column_enclosure" size="1" value="<?php echo htmlspecialchars($column_enclosure); ?>">
			</div>
			<div>
			<label for="price_modifier">Price Modifier (use % for percentage)</label>
			<input type="text" class="config" id="price_modifier" name="price_modifier" size="5" value="<?php echo $price_modifier ?>">
			</div>
			<div>
			<label for="tax_class_title">Tax Class</label>
			<?php $tax_class_titles = array(array('id' => '', 'text' => ''));
			foreach (ep_get_tax_class_titles() as $v) {
				$tax_class_titles[] = array('id' => $v, 'text' => $v);
			} ?>
			<?php echo zen_draw_pull_down_menu('tax_class_title', $tax_class_titles, $tax_class_title, 'class="config" id="tax_class_title"'); ?>
			</div>
			<div id="transforms">
				<div>
				<label for="transforms_metatags_keywords">Meta Keywords Patterns</label>
				<input type="text" class="config" id="transforms_metatags_keywords" name="transforms[metatags_keywords]" size="50">
				</div>
				<div>
				<label for="image_path_prefix">Image Path Prefix</label>
				<input type="text" class="config" id="image_path_prefix" name="image_path_prefix" size="30" value="<?php echo $image_path_prefix; ?>">
				</div>
			</div>
			<input type="submit" name="import" value="Import">
			<input type="button" name="setconfig" value="Save Handler Configuration">
		</fieldset>
		<?php if (is_dir($temp_path)) { ?>
			<fieldset>
			<legend><a id="show_uploaded_files" href="#">Show Uploaded Files</a></legend>
			<table id="uploaded_files">
				<thead>
				<tr>
					<th>Import</th>
					<th>File</th>
					<th>Size</th>
					<th>Last Modified</th>
				</tr>
				</thead>
				<?php $linkBase = HTTP_SERVER .  DIR_WS_CATALOG . $tempdir; ?>
				<!-- @todo replace the onclick with unobtrusive js when we use jquery -->
				<?php foreach (new DirectoryIterator($temp_path) as $tempFile) { ?>
				<?php if (!$tempFile->isDot() && ($tempFile->getFilename() != 'index.html')) { ?>
					<tr>
						<td><input type="button" onclick="this.form.local_file.value='<?php echo $tempFile->getFileName() ?>';" value="Choose"></td>
						<td><a href="<?php echo $linkBase . $tempFile->getFileName(); ?>"><?php echo $tempFile->getFileName(); ?></a></td>
						<td><?php echo round(($tempFile->getSize() / 1024)); ?> KB</td>
						<td><?php echo strftime(DATE_FORMAT_LONG, $tempFile->getMTime()); ?></td>
					</tr>
				<?php } ?>
				<?php } ?>
			</table>
		</fieldset>
		<?php } ?>
	</form>
		<?php echo zen_draw_form('custom', 'easypopulate.php', 'id="custom"', 'get'); ?>
			<!--  <form ENCTYPE="multipart/form-data" ACTION="easypopulate.php?download=stream&dltype=full" METHOD="POST"> -->
					<div align = "left">
					<?php
					$manufacturers_array = array();
					$manufacturers_array[] = array( "id" => '', 'text' => "Manufacturers" );
					$manufacturers_query = mysql_query("SELECT manufacturers_id, manufacturers_name FROM " . TABLE_MANUFACTURERS . " ORDER BY manufacturers_name");
					while ($manufacturers = mysql_fetch_array($manufacturers_query)) {
						$manufacturers_array[] = array( "id" => $manufacturers['manufacturers_id'], 'text' => $manufacturers['manufacturers_name'] );
					}
					$status_array = array(array( "id" => '1', 'text' => "status" ),array( "id" => '1', 'text' => "active" ),array( "id" => '0', 'text' => "inactive" ));
					echo "Filter Complete Download by: " . zen_draw_pull_down_menu('ep_category_filter', array_merge(array( 0 => array( "id" => '', 'text' => "Categories" )), zen_get_category_tree()));
					echo ' ' . zen_draw_pull_down_menu('ep_manufacturer_filter', $manufacturers_array) . ' ';
					echo ' ' . zen_draw_pull_down_menu('ep_status_filter', $status_array) . ' ';

					$download_array = array(array( "id" => 'download', 'text' => "download" ),array( "id" => 'stream', 'text' => "stream" ),array( "id" => 'tempfile', 'text' => "tempfile" ));
					echo ' ' . zen_draw_pull_down_menu('download', $download_array) . ' ';

					echo zen_draw_input_field('dltype', 'full', ' style="padding: 0px"', false, 'submit');
					?>
					</div>
			</form>

			<b>Download Easy Populate Files</b>
			<?php
			// Add your custom fields here
			$ep_exports = array();
			$ep_exports['full'] = 'Complete';
			$ep_exports['priceqty'] = 'Model/Price/Qty';
			$ep_exports['pricebreaks'] = 'Model/Price/Breaks';
			$ep_exports['modqty'] = 'Model/Price/Qty/Last Modified/Status';
			$ep_exports['category'] = 'Model/Category';
			$ep_exports['attrib'] = 'Detailed Products Attributes (single-line)';
			$ep_exports['attrib_basic'] = 'Basic Products Attributes (multi-line)';
			$ep_exports['options'] = 'Attribute Options Names';
			$ep_exports['values'] = 'Attribute Options Values';
			$ep_exports['optionvalues'] = 'Attribute Options-Names-to-Values';
			$ep_exports['froogle'] = 'Froogle';
			?>
			<table>
			<thead>
			<tr>
				<th>Download</th>
				<th>Create in Temp dir (<?php echo $tempdir ?>)</th>
			</tr>
			</thead>
			<tbody>
			<?php foreach($ep_exports as $key => $value) { ?>
				<tr>
					<td><a href="easypopulate.php?download=stream&amp;dltype=<?php echo $key ?>"><?php echo $value ?></a></td>
					<td><a href="easypopulate.php?download=tempfile&amp;dltype=<?php echo $key ?>"><?php echo $value ?></a></td>
				</tr>
			<?php } ?>
			</tbody>
			</table>
			<?php if ($products_with_attributes) { ?>
					<span class="fieldRequired"> * Attributes Included in Complete</span>
			<?php } else { ?>
					<span class="fieldRequired"> * Attributes Not Included in Complete</span>
			<?php } ?>
			<br />
			<?php if (isset($output['info'])) echo '<p>' . $output['info'] . '</p>'; ?>
			<?php if (!empty($output['errors'])) { ?>
				<p>Errors:</p>
				<?php foreach ($output['errors'] as $error) { ?>
					<p class="fail"><?php echo $error; ?></p>
				<?php } ?>
			<?php } ?>
			<?php if (!empty($output['items'])) { ?>
			<div><h2><?php echo EASYPOPULATE_DISPLAY_HEADING; ?></h2> Items Uploaded(<?php echo $import->itemCount;?>)</div>
			<table id="uploaded_products" class="results_table">
				<thead>
				<tr>
					<th><?php echo EASYPOPULATE_DISPLAY_STATUS; ?></th>
					<th><?php echo EASYPOPULATE_DISPLAY_MESSAGE; ?></th>
					<!-- @todo make sure the headers line up with the text in all cases -->
					<?php foreach (array_keys($import->filelayout) as $header) { ?>
						<th><?php echo ucwords(str_replace('_', ' ', $header)); ?></th>
					<?php } ?>
				</tr>
				</thead>
				<?php foreach ($output['items'] as $item) { ?>
					<tr class="<?php echo $item['class'] ?>">
						<td class="status"><?php echo $item['status'] ?></td>
						<td class="message"><?php echo $item['message'] ?></td>
						<?php foreach ($item['data'] as $data) { ?>
							<?php if (!is_array($data)) { ?>
								<td><?php echo print_el($data); ?></td>
							<?php } ?>
						<?php } ?>
					</tr>
				<?php } ?>
			</table>
			<div><h2><?php echo EASYPOPULATE_DISPLAY_RESULT_UPLOAD_COMPLETE; ?></h2></div>
			<?php } ?>
			<?php if (!empty($output['specials'])) { ?>
			<div><h2><?php echo EASYPOPULATE_SPECIALS_HEADING ?></h2></div>
			<table id="uploaded_specials" class="results_table">
				<thead>
				<tr>
					<th><?php echo EASYPOPULATE_DISPLAY_STATUS; ?></th>
					<th><?php echo EASYPOPULATE_DISPLAY_MESSAGE; ?></th>
					<th><?php echo EASYPOPULATE_DISPLAY_MODEL; ?></th>
					<th><?php echo EASYPOPULATE_DISPLAY_NAME; ?></th>
					<th><?php echo EASYPOPULATE_DISPLAY_PRICE; ?></th>
					<th><?php echo EASYPOPULATE_SPECIALS_PRICE; ?></th>
				</tr>
				</thead>
				<?php foreach ($output['specials'] as $item) { ?>
					<tr class="<?php echo $item['class'] ?>">
						<td class="status"><?php echo $item['status'] ?></td>
						<td class="message"><?php echo $item['message'] ?></td>
						<?php foreach ($item['data'] as $data) { ?>
							<td><?php echo print_el($data); ?></td>
						<?php } ?>
					</tr>
				<?php } ?>
			</table>
			<?php } ?>
</div>
<?php } ?>
<?php error_reporting($original_error_level); ?>
<?php $_SESSION['easypopulate']['errors'] = array(); ?>
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>