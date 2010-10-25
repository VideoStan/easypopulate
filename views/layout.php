<!DOCTYPE html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
	<?php $adminUrl = EasyPopulate::baseUrl() . DIR_WS_ADMIN; // @todo use app mountpoint? ?>
	<title><?php echo TITLE; ?> - Easy Populate</title>
	<!-- <base href=<?php echo DIR_WS_ADMIN; ?>"> -->
	<link rel="stylesheet" type="text/css" href="<?php echo $adminUrl; ?>includes/stylesheet.css">
	<link rel="stylesheet" type="text/css" href="<?php echo $adminUrl; ?>includes/cssjsmenuhover.css" media="all" id="hoverJS">
	<link rel="stylesheet" type="text/css" href="<?php echo $adminUrl; ?>includes/classes/EasyPopulate/public/style.css" media="all">
	<script type="text/javascript" src="<?php echo $adminUrl; ?>includes/menu.js"></script>
	<script type="text/javascript" src="<?php echo $adminUrl; ?>includes/general.js"></script>
	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
	<script type="text/javascript" src="<?php echo $adminUrl; ?>includes/classes/EasyPopulate/public/form/jquery.form.js"></script>

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
		$("#tabs li a[href=" + window.location.pathname + "]").parent().addClass("current");

		$("#installer :button").click(function() {
			$("#installer input[name=action]").val($(this).attr('name'));
			$("#installer").submit();
		});
	});
	$(document).ready(function() {
		$("#import_handler").change(function() {
			$("#config").load("/admin/easypopulate.php/import/" + $(this).val(), function(response, status, xhr) {
				if (status == "error") {
					var msg = "Sorry but there was an error: ";
					$(this).html(msg + xhr.status + " " + xhr.statusText);
				}
			});

		});
		$("#item_type").change(function() {
			var curItemType = $(this).val();
			$("#import_handler").empty();
			$.each(handlers_all, function(itemType, handlers) {
				if (itemType == curItemType) {
					$.each(handlers, function(index, option) {
						$("#import_handler").append("<option value=" + option + ">" + option + "</option>");
					});
				}
			});
			$("#import_handler").change();
		});

		$("#import_form input[name=setconfig]").click(function() {
			var config = $("#import_form .config").serializeObject();
			var preset = $("#import_form #import_handler").val();
			var vals = { "preset" : preset, "config" : config };
			$.post("/admin/easypopulate.php/preset", vals);
		});

		$(".results_table tr").mouseover(function(){
			$(this).addClass("over");
		}).mouseout(function(){
			$(this).removeClass("over");
		});
		$(".results_table tr:nth-child(even)").addClass("alt");

		var options = {
			target: "#upload_form .message",
			data: { ajax : true },
			beforeSubmit: function(formData, jqForm, options) {
				$("#import_form :submit").attr('disabled', 'disabled');
			},
			success: function(responseString) {
				local_file = $("#uploaded_file").val().replace(/^.*[\/\\]/g, '');
				$("#upload_form .message").addClass('success');
				$("#import_form :submit").removeAttr('disabled');
				$("#local_file").val(local_file);
			}
		}
		$('#upload_form').ajaxForm(options);

		var options = {
			target: "#import_form .message",
			beforeSubmit: function(formData, jqForm, options) {
				$("#import_form :submit").attr('disabled', 'disabled');
				$("#import_form .message").html('<strong>Importing...</strong>');
			},
			success: function(responseString) {
				/* @todo do this better, use a throbbler? */
				$("#import_form .message").html('');
				$("#import_form .message").removeClass('error');
				$("#import_form .message").addClass('success');
				$("#import_form .message").append('Success: ' + responseString);
				$("#import_form :submit").removeAttr('disabled');
			},
			error: function (xhr, status) {
				$("#import_form .message").html('');
				$("#import_form .message").addClass('error');
				$("#import_form .message").html(xhr.responseText);
				$("#import_form :submit").removeAttr('disabled');
			}
		}
		$("#import_form").ajaxForm(options);
	});
	</script>
</head>
<body>
<?php echo EasyPopulate::header(); ?>
<div id="ep_header">
	<h2>Easy Populate <?php echo EASYPOPULATE_VERSION ?></h2>
	<form id="installer" action="/admin/easypopulate.php/installer" method="POST">
		<input type="hidden" name="action" value="">
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
<div>
<ul id="tabs">
	<li><a href="/admin/easypopulate.php/">Help</a></li>
	<li><a href="/admin/easypopulate.php/import">Import</a></li>
	<li><a href="/admin/easypopulate.php/export">Export</a></li>
</ul>
<?php if (defined('EASYPOPULATE_CONFIG_VERSION')) { ?>
	<?php echo $content; ?>
<?php } ?>
</div>
</body>
</html>
