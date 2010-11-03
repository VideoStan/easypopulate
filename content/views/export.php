<?php if (!empty($output['errors'])) { ?>
	<p>Errors:</p>
	<?php foreach ($output['errors'] as $error) { ?>
		<p class="fail"><?php echo $error; ?></p>
	<?php } ?>
<?php } ?>
<?php echo zen_draw_form('custom', 'easypopulate.php', 'id="custom"', 'get'); ?>
	<!--  <form ENCTYPE="multipart/form-data" ACTION="easypopulate.php?download=stream&dltype=full" METHOD="POST"> -->
	<div align="left">
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

		echo zen_draw_input_field('format', 'full', ' style="padding: 0px"', false, 'submit');
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
			<td><a href="/admin/easypopulate.php/export/<?php echo $key ?>/stream"><?php echo $value ?></a></td>
			<td><a href="/admin/easypopulate.php/export/<?php echo $key ?>/tempfile"><?php echo $value ?></a></td>
		</tr>
	<?php } ?>
	</tbody>
</table>