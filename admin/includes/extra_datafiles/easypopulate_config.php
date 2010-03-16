<?php
/**
 * EasyPopulate extra configuration
 *
 * @package easypopulate
 * @author langer? and other contributors
 * @copyright 2003?
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 */

define('EASYPOPULATE_CONFIG_COLUMN_DELIMITERS', serialize(
	array(',', 'tab', '|', ':', ';', '^')
));
define('EASYPOPULATE_CONFIG_SMART_TAGS_LIST', serialize(
	array("\r\n|\r|\n" => '<br />')
));

/**
 * Configure Advanced Smart Tags - activated/de-activated in Zencart admin
 *
 * @todo move this somewhere that doesn't require the user to edit this file to make upgrades easier
 *
 * Only activate advanced smart tags if you really know what you are doing, and understand regular expressions.
 * Disable if things go awry.
 * If you wish to add your own smart-tags below, please ensure that you understand the following:
 *
 * 1) ensure that the expressions you use avoid repetitive behaviour from one
 *    upload to the next using  existing data, as you may end up with this sort of thing:
 *   <strong><strong><strong><strong>thing</strong></strong></strong></strong> ...etc
 *   for each update. This is caused for each output that qualifies as an input for any expression..
 * 2) remember to place the tags in the order that you want them to occur, as
 *    each is done in turn and may remove characters you rely on for a later tag
 * 3) the smart_tags function is executed after this array is used,
 *   so you have all of your carriage-returns and line-breaks to play with below
 * 4) make sure you escape the following metacharacters if you are using them a
 *   s string literals: ^  $  \  *  +  ?  (  )  |  .  [  ]  / etc..
 * Uncomment the examples that you want to use
 * for regex help see: http://www.quanetic.com/regex.php or http://www.regular-expressions.info
 */
define('EASYPOPULATE_CONFIG_ADV_SMART_TAGS_LIST', serialize(
	array(
	// replaces "Description:" at beginning of new lines with <br /> and same in bold
	//"\r\nDescription:|\rDescription:|\nDescription:" => '<br /><strong>Description:</strong>',

	// replaces at beginning of description fields "Description:" with same in bold
	//"^Description:" => '<strong>Description:</strong>',

	// just make "Description:" bold wherever it is...must use both lines to prevent duplicates!
	//"<strong>Description:<\/strong>" => 'Description:',
	//"Description:" => '<strong>Description:</strong>',

	// replaces "Specification:" at beginning of new lines with <br /> and same in bold.
	//"\r\nSpecifications:|\rSpecifications:|\nSpecifications:" => '<br /><strong>Specifications:</strong>',

	// replaces at beginning of descriptions "Specifications:" with same in bold
	//"^Specifications:" => '<strong>Specifications:</strong>',

	// just make "Specifications:" bold wherever it is...must use both lines to prevent duplicates!
	//"<strong>Specifications:<\/b>" => 'Specifications:',
	//"Specifications:" => '<strong>Specifications:</strong>',

	// replaces in descriptions any asterisk at beginning of new line with a <br /> and a bullet.
	//"\r\n\*|\r\*|\n\*" => '<br />&bull;',

	// replaces in descriptions any asterisk at beginning of descriptions with a bullet.
	//"^\*" => '&bull;',

	// returns/newlines in description fields replaced with space, rather than <br /> further below
	//"\r\n|\r|\n" => ' ',

	// the following should produce paragraphs between double breaks, and line breaks for returns/newlines
	//"^<p>" => '', // this prevents duplicates
	//"^" => '<p>',
	//"^<p style=\"desc-start\">" => '', // this prevents duplicates
	//"^" => '<p style="desc-start">',
	//"<\/p>$" => '', // this prevents duplicates
	//"$" => '</p>',
	//"\r\n\r\n|\r\r|\n\n" => '</p><p>',
	// if not using the above 5(+2) lines, use the line below instead..
	//"\r\n\r\n|\r\r|\n\n" => '<br /><br />',
	//"\r\n|\r|\n" => '<br />',

	// ensures "Description:" followed by single <br /> is fllowed by double <br />
	//"<strong>Description:<\/b><br \/>" => '<br /><strong>Description:</strong><br /><br />',
	)
));

?>