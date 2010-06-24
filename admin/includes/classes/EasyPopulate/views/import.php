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
	<input type="hidden" name="import" value="1">
	<p class="message"></p>
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
		<label for="local_file">Import from Temp Dir (<?php echo $tempdir; ?>)</label>
		<input type="text" class="config" id="local_file" name="local_file" size="50" value="<?php echo $local_file; ?>">
		<span class="error"></span>
		</div>
		<div>
			<label for="feed_fetch">Update from Supplier List URL</label>
			<?php $enabled = $feed_fetch ? '' : 'disabled="disabled"'; ?>
			<?php echo zen_draw_checkbox_field('feed_fetch', '', (bool)$feed_fetch, '', 'class="config" id="feed_fetch" ' . $enabled) ?>
			<span class="error"></span>
		</div>
		<div>
			<label for="images_fetch">Enable Supplier Images Update</label>
			<?php $enabled = $images_fetch ? '' : 'disabled="disabled"'; ?>
			<?php echo zen_draw_checkbox_field('images_fetch', '', (bool)$images_fetch, '', 'class="config" id="images_fetch" ' . $enabled) ?>
			<span class="error"></span>
		</div>
		<?php if (function_exists('get_sites')) { ?>
			<div>
			<label for="site">Site</label>
			<?php echo zen_draw_pull_down_menu('site', get_sites(), $site, 'class="config" id="site"'); ?>
			</div>
		<?php } ?>

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
			<div>
			<label for="feed_send_email">Send email notification of results</label>
			<?php echo zen_draw_checkbox_field('feed_send_email', '', (bool)$feed_send_email, '', 'id="images_fetch" class="config" ') ?>
			<span class="error"></span>
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