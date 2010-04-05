<?php
/**
 * EasyPopulate admin menu link
 *
 * If you want to provide a language specific defintion of BOX_TOOLS_EASYPOPULATE
 * create a file in
 * admin/includes/languages/$lang/extra_definitions/easypopulate.php like this:
 * <?php define('BOX_TOOLS_EASYPOPULATE', 'Your Definition'); ?>
 *
 * @package easypopulate
 * @author langer
 * @copyright 2003
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Publice License (v2 only)
 */
if (file_exists(DIR_FS_ADMIN . 'easypopulate.php')) {
	if (!defined('BOX_TOOLS_EASYPOPULATE')) {
		define('BOX_TOOLS_EASYPOPULATE', 'Easy Populate');
	}
	$za_contents[] = array('text' => BOX_TOOLS_EASYPOPULATE,
	'link' => zen_href_link('easypopulate.php', '', 'NONSSL'));
}
?>