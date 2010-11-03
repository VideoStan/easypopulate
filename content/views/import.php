<script type="text/javascript">
handlers_all = <?php echo json_encode($handlers_all, true); ?>
</script>
<form id="upload_form" enctype="multipart/form-data" action="/admin/easypopulate.php/upload" method="POST">
	<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $max_file_size ?>">
		<div>
		<label for="uploaded_file">Upload EP File</label>
		<input id="uploaded_file" name="uploaded_file" type="file" size="50">
		<input type="submit" value="Upload">
		<p class="message"></p>
		</div>
</form>

<form id="import_form" enctype="multipart/form-data" action="/admin/easypopulate.php/import" method="POST">
	<p class="message"></p>
	<fieldset>
		<legend>Import delimited files</legend>
		<div>
		<label for="item_type">Import Item Type</label>
		<?php echo zen_draw_pull_down_menu('item_types', ep_pull_down_menu_options($item_types), $item_type, 'id="item_type"'); ?>
		<div>
		<label for="import_handler">Import File Handler</label>
		<?php 
			$handlers_options = ep_pull_down_menu_options($handlers);
			echo zen_draw_pull_down_menu('import_handler', $handlers_options, $import_handler, 'id="import_handler"');
		?>
		</div>
	</fieldset>
	<fieldset id="config">
	<?php $subTemplate = new Template($this->root, 'import-fields'); echo $subTemplate->render(array('handler' => $handler)); ?>
	</fieldset>
	<fieldset>
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