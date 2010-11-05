<?php $admin2->title() ?>
<form action="<?php echo $request->url('export'); ?>" name="custom" id="custom">
	<div align="left">
    	Filter Complete Download by: 
		<?php echo $this->widget('ManufacturerSelectFormWidget#title=None&options=0= --- ','ep_manufacturer_filter'); ?> 
		<?php echo $this->widget('SelectFormWidget#title=None&options=1=active&0=inactive','ep_status_filter'); ?> 
		<?php echo $this->widget('SelectFormWidget#title=None&options=stream&tempfile','download'); ?> 
		<?php // @todo <johnny> implement this as widget: zen_draw_pull_down_menu('ep_category_filter', array_merge(array( 0 => array( "id" => '', 'text' => "Categories" )), zen_get_category_tree())); ?>
		<input type="submit" name="format" value="full" />
	</div>
</form>

<strong>Download Easy Populate Files</strong>
<?php
// Add your custom fields here
// @todo allow custom file output types similiar to how import handler configs work
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
?>
<table>
	<thead>
		<tr>
			<th>Download</th>
			<th>Create in Temp dir (<?php echo $temp_dir ?>)</th>
		</tr>
	</thead>
	<tbody>
	<?php foreach($ep_exports as $key => $value) { ?>
		<tr>
			<td><a href="<?php echo $request->url('export', "format=$key&download=stream"); ?>"><?php echo $value ?></a></td>
			<td><a href="<?php echo $request->url('export', "format=$key&download=tempfile"); ?>"><?php echo $value ?></a></td>
		</tr>
	<?php } ?>
	</tbody>
</table>
