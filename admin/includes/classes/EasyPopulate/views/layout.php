<!DOCTYPE html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
	<title><?php echo TITLE; ?> - Easy Populate</title>
	<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
	<link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
	<link rel="stylesheet" type="text/css" href="includes/classes/EasyPopulate/public/style.css" media="all" id="hoverJS">
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
				$("#remote_file").attr("disabled", "disabled");
				$.each(json, function(k, v){
					$("#" + k).val(unescape(v));
				});
				if (json["feed_url"] != null) {
					$("#remote_file").removeAttr("disabled");
				}
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
</head>
<body>
<?php
error_reporting($original_error_level);
require(DIR_WS_INCLUDES . 'header.php'); 
error_reporting(E_ALL ^ E_DEPRECATED);
?>
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
		<span class="error"><?php echo EASYPOPULATE_ERROR_NOT_INSTALLED ?></span>
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
				<label for="remote_file">Update from Supplier List URL</label>
				<?php $enabled = !empty($config['feed_url']) ? '' : 'disabled="disabled"'; ?>
				<?php echo zen_draw_checkbox_field('remote_file', '', (bool)$feed_url, '', 'id="remote_file" ' . $enabled) ?>
				<span class="error"><?php echo ep_get_error('remote_file'); ?></span>

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
				<label for="metatags_keywords">Meta Keywords Patterns</label>
				<input type="text" class="config" id="metatags_keywords" name="metatags_keywords" size="50" value="<?php echo $metatags_keywords; ?>">
				</div>
				<div>
				<label for="metatags_description">Meta Description Patterns</label>
				<input type="text" class="config" id="metatags_description" name="metatags_description" size="50" value="<?php echo $metatags_description; ?>">
				</div>
				<div>
				<label for="metatags_title">Meta Title Patterns</label>
				<input type="text" class="config" id="metatags_title" name="metatags_title" size="50" value="<?php echo $metatags_title; ?>">
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
								<td><?php echo substr(strip_tags($data), 0, 10); ?></td>
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
							<td><?php echo substr(strip_tags($data), 0, 10); ?></td>
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