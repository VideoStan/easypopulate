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
		$attributes = array('class' => 'config');
		switch(true) {
			case (is_bool($options['value'])): 
				echo $this->widget('BooleanFormWidget', $field, (bool)$options['value'], $attributes);
				break;
			case (isset($options['options'])):
				$attributes['options'] = ep_pull_down_menu_options($options['options']);
				echo $this->widget('SelectFormWidget', $field, $options['value'], $attributes);
				break;
			case (isset($options['size']) && is_array($options['size'])):
				if (!isset($options['size']['columns'])) $options['size']['columns'] = '60';
				if (!isset($options['size']['rows'])) $options['size']['rows'] = '7';
				$attributes = array_merge($attributes, $options['size']);
				echo $this->widget('TextAreaFormWidget', $field, (string)$options['value'], $attributes);
				break;
			default:
				if (isset($options['size'])) $attributes['size'] = $options['size'];
				//echo zen_draw_input_field($field, (string)$options['value'], $attributes, false /*,$type = 'text'*/);
				echo $this->widget('TextFormWidget', $field, (string)$options['value'], $attributes);
		}
		?>
		<span class="error"></span>
		</div>
<?php } ?>
<?php } ?>
