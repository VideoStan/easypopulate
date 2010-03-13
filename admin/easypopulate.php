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

require_once ('includes/application_top.php');
$original_error_level = error_reporting();
error_reporting(E_ALL ^ E_DEPRECATED); // zencart uses functions deprecated in php 5.3
$output = array();
$products_with_attributes = false; // langer - this will be redundant after html renovation
$ep_stack_sql_error = false; // function returns true on any 1 error, and notifies user of an error


if (defined('EASYPOPULATE_CONFIG_TEMP_DIR')) { // EasyPopulate is installed
	$config = ep_get_config();
	extract($config); // Brings all the configuration variables into the current symbol table

	if ($log_queries) {
		// new blank log file on each page impression for full testing log (too big otherwise!!)
		$fp = fopen($temp_path . 'ep_debug_log.txt','w');
		fclose($fp);
	}

	// @todo move this to where the file processing actually takes place
	@set_time_limit($time_limit);
	@ini_set('max_input_time', $time_limit);
	$chmod_check = is_dir($temp_path) && is_writable($temp_path);
	if (!$chmod_check) {
		$messageStack->add(sprintf(EASYPOPULATE_MSGSTACK_TEMP_FOLDER_MISSING, $temp_path, DIR_FS_CATALOG), 'warning');
	}

	ep_update_handlers();
}


/**
 * START check for existence of various mods
 */
// all mods go in this array as 'name' => 'true' if exist. eg $ep_supported_mods['psd'] = true; means it exists.
// @todo scan array in future to reveal if any mods for inclusion in downloads
$ep_supported_mods = array();
$ep_supported_mods['psd'] = false; //ep_field_name_exists(TABLE_PRODUCTS_DESCRIPTION,'products_short_desc');
$ep_supported_mods['uom'] = false; //ep_field_name_exists(TABLE_PRODUCTS_DESCRIPTION,'products_price_as'); // uom = unit of measure
$ep_supported_mods['upc'] = false; //ep_field_name_exists(TABLE_PRODUCTS_DESCRIPTION,'products_upc'); // upc = UPC Code
/**
 * END check for existance of various mods
 */

if (isset($_POST['installer'])) {
	$f = $_POST['installer'] . '_easypopulate';
	$f();
	zen_redirect(zen_href_link(FILENAME_EASYPOPULATE));
	//$messageStack->add(EASYPOPULATE_MSGSTACK_INSTALL_SUCCESS, 'success');
}

$ep_dltype = (isset($_GET['dltype'])) ? $_GET['dltype'] : NULL;
if (zen_not_null($ep_dltype)) {
   require DIR_WS_CLASSES . 'easypopulate/Export.php';

	$export_file = 'EP-' . $ep_dltype . strftime('%Y%b%d-%H%M%S');
	// now either stream it to them or put it in the temp directory
	if ($ep_dlmethod == 'stream') {
		header("Content-type: text/csv");
		//header("Content-type: application/vnd.ms-excel"); // @todo make this configurable
		header("Content-disposition: attachment; filename=$export_file" . (($col_delimiter == ",")?".csv":".txt"));
		// Changed if using SSL, helps prevent program delay/timeout (add to backup.php also)
		if ($request_type== 'NONSSL'){
			header("Pragma: no-cache");
		} else {
			header("Pragma: ");
		}
		header("Expires: 0");

		$fp = fopen("php://temp", "w+");
		foreach ($filestring as $line) {
			fputcsv($fp, $line, $col_delimiter, $col_enclosure);
		}
		rewind($fp);
		echo stream_get_contents($fp);
		zen_exit();
	} else {
		//*******************************
		// PUT FILE IN TEMP DIR
		//*******************************
		$tmpfpath = $temp_path . $export_file . (($col_delimiter == ",")?".csv":".txt");
		$fp = fopen( $tmpfpath, "w+");
		foreach ($filestring as $line) {
			fputcsv($fp, $line, $col_delimiter, $col_enclosure);
		}
		fclose($fp);
		$messageStack->add(sprintf(EASYPOPULATE_MSGSTACK_FILE_EXPORT_SUCCESS, $export_file, $tempdir), 'success');
	}
}


//*******************************
// UPLOADING OF FILES STARTS HERE
//*******************************
if (isset($_POST['import'])) {
	require DIR_WS_CLASSES . 'easypopulate/Import.php';
}

// END FILE UPLOADS

if ($ep_stack_sql_error == true) $messageStack->add(EASYPOPULATE_MSGSTACK_ERROR_SQL, 'caution');

/**
* this is a rudimentary date integrity check for references to any non-existant product_id entries
* this check ought to be last, so it checks the tasks just performed as a quality check of EP...
* @todo langer  data present in table products, but not in descriptions.. user will need product info, and decide to add description, or delete product
*/
if (!isset($_GET['dross'])) $_GET['dross'] = NULL;
if ($_GET['dross'] == 'delete') {
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
} else { // elseif ($_GET['dross'] == 'check')
	// we can choose a config option: check always, or only on clicking a button
	// default action when not deleting existing debris is to check for it and alert when discovered..
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
	<script language="javascript" type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.1/jquery.min.js"></script>

	<script type="text/javascript">
	$(document).ready(function() {
		cssjsmenu('navbar');
		$('#hoverJS').attr('disabled', 'disabled');

		$("#installer :button").click(function() {
			$("#installer input[name=installer]").val($(this).attr('name'));
			$("#installer").submit();
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
	</style>
</head>
<body>
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<div id="ep_header">
	<h2>Easy Populate <?php echo EASYPOPULATE_VERSION ?></h2>
	<form id="installer" enctype="multipart/form-data" action="easypopulate.php" method="POST">
		<input type="hidden" name="installer" value="">
	   <?php if (defined('EASYPOPULATE_CONFIG_TEMP_DIR')) { ?>
	   <input type="button" name="remove" value="Remove EasyPopulate">
	   <!-- @todo <input type="button" name="upgrade" value="Upgrade"> -->
	   <?php } else { ?>
	   <span><?php echo EASYPOPULATE_ERROR_NOT_INSTALLED ?></span>
		<input type="button" name="install" value="Install EasyPopulate">
		<?php } ?>
	</form>
</div>

<?php if (defined('EASYPOPULATE_CONFIG_TEMP_DIR')) { ?>
<div>
	<form enctype="multipart/form-data" action="easypopulate.php" method="POST">
		<input type="hidden" name="MAX_FILE_SIZE" value="100000000">
		<input type="hidden" name="import" value="1">
		<fieldset>
			<legend>Import delimited files</legend>
			<div>
			<label for="uploaded_file">Upload EP File</label>
			<input id="uploaded_file" name="uploaded_file" type="file" size="50">
			</div>
			<div>
			<label for="local_file">Import from Temp Dir (<?php echo $tempdir; ?>)</label>
			<input type="text" id="local_file" name="local_file" size="50">
			</div>
			<div>
			<label for="column_delimiter">Column Delimiter</label>
			<?php $delimiters = array();
			foreach (ep_get_config('col_delimiters') as $v) {
				$delimiters[] = array('id' => $v, 'text' => $v);
			} ?>
			<?php echo zen_draw_pull_down_menu('column_delimiter', $delimiters, ep_get_config('col_delimiter')); ?>
			</div>
			<div>
			<label for="column_enclosure">Column Enclosure</label>
			<input type="text" id="column_enclosure" name="column_enclosure" size="1" value="<?php echo htmlspecialchars(ep_get_config('col_enclosure')) ?>">
			</div>
			<div>
			<label for="price_modifier">Price Modifier (use % for percentage)</label>
			<input type="text" id="price_modifier" name="price_modifier" size="5" value="">
			</div>
			<div>
			<label for="import_handler">Import File Handler</label>
			<?php $handlers = array();
			foreach (EPFileUploadFactory::find() as $v) {
				$handlers[] = array('id' => $v, 'text' => $v);
			} ?>
			<?php echo zen_draw_pull_down_menu('import_handler', $handlers, ep_get_config('import_handler')); ?>
			</div>
			<div id="transforms">
				<div>
				<label for="transforms_metatags_keywords">Meta Keywords Patterns</label>
				<input type="text" id="transforms_metatags_keywords" name="transforms[metatags][keywords]" size="50">
				</div>
				<div>
				<label for="image_path_prefix">Image Path Prefix</label>
				<input type="text" id="image_path_prefix" name="image_path_prefix" size="30" value="">
				</div>
			</div>
			<input type="submit" name="import" value="Import">
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
			<div><h2><?php echo EASYPOPULATE_DISPLAY_HEADING; ?></h2> Items Uploaded(<?php echo $file->itemCount;?>)</div>
			<table id="uploaded_products" class="results_table">
				<thead>
				<tr>
					<th><?php echo EASYPOPULATE_DISPLAY_STATUS; ?></th>
					<th><?php echo EASYPOPULATE_DISPLAY_MESSAGE; ?></th>
					<!-- @todo make sure the headers line up with the text in all cases -->
					<?php foreach (array_keys($filelayout) as $header) { ?>
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
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>