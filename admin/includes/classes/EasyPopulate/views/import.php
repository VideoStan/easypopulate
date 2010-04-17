<form id="import_form" enctype="multipart/form-data" action="/admin/easypopulate.php/import" method="POST">
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