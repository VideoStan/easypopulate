<?php 
/*
 * @todo move reusable stuff out
 * @todo take care of special characters in column_enclosure (via htmlspecialchars and the like)
 */
?>

<?php if (!is_array($handler) || !isset($handler)) { ?>
	<div class="error">Could not load handler config</div>
<?php } else { ?>
	<legend>Handler Configuration</legend>
<?php foreach ($handler as $field => $options) { ?>
	<?php if (!isset($options['input'])) $options['input'] = true;
		if(!$options['input']) continue;
	?>
	<div>
		<label for="<?php echo $field; ?>">
	    <?php
			$title_key = 'EASYPOPULATE_CONFIG_' . strtoupper($field) . '_TITLE';
			if (defined($title_key)) {
				echo constant($title_key);
			} else {
				echo $title_key;
			} 
		?>
		</label>
		<?php
		$attributes = 'id="'. $field . '" class="config" ';
		switch(true) {
			case (is_bool($options['value'])): 
				echo zen_draw_checkbox_field($field, '', (bool)$options['value'], '', $attributes);
				break;
			case (isset($options['options'])):
				$pull_down_options = ep_pull_down_menu_options($options['options']);
				echo zen_draw_pull_down_menu($field, $pull_down_options, $options['value'], $attributes);
				break;
			default:
				if (isset($options['size'])) $attribues .= 'size="' . $options['size'] . '" ';
				echo zen_draw_input_field($field, (string)$options['value'], $attributes, false /*,$type = 'text'*/);
		}
		?>
		<span class="error"></span>
		</div>
<?php } ?>
<?php } ?>
