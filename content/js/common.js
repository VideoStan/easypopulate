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

/* @todo don't hardcode these urls */
$(document).ready(function() {
	$("#import_handler").change(function() {
		$("#config").load("/zenmagick/apps/admin/web/import/import_handler/" + $(this).val(), function(response, status, xhr) {
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
		var name = $("#import_form #import_handler").val();
		var vals = { "name" : name, "config" : config };
		$.post("/zenmagick/apps/admin/web/preset", vals);
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

