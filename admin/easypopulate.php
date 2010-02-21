<?php
/**
 * EasyPopulate main administrative interface
 *
 * @package easypopulate
 * @author langer
 * @author too many to list, see history.txt
 * @copyright 200?-2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 *
 * @todo <chadd> change v_products_price_as to v_products_price_uom
 */

// START INITIALIZATION
require_once ('includes/application_top.php');
require(DIR_WS_CLASSES . 'easypopulate.php');

$output = array();

if (!isset($_GET['epinstaller'])) $_GET['epinstaller'] = '';
if (!isset($_GET['dross'])) $_GET['dross'] = '';

if (!defined(EASYPOPULATE_CONFIG_TEMP_DIR) && !empty($_GET['epinstaller'])) { // admin area config not installed
    $messageStack->add(sprintf(EASYPOPULATE_MSGSTACK_INSTALL_KEYS_FAIL, '<a href="' . zen_href_link(FILENAME_EASYPOPULATE, 'epinstaller=install') . '">', '</a>'), 'warning');
}

// START installation
if ($_GET['epinstaller'] == 'remove') {
    remove_easypopulate();
    zen_redirect(zen_href_link(FILENAME_EASYPOPULATE));
}

if ($_GET['epinstaller'] == 'install') {
	remove_easypopulate();
	install_easypopulate();
	//$messageStack->add(EASYPOPULATE_MSGSTACK_INSTALL_CHMOD_SUCCESS, 'success');
	zen_redirect(zen_href_link(FILENAME_EASYPOPULATE));
}
// END installation

/**
 * Initialise vars
 */
$config = ep_get_config();
// Brings all the configuration variables into the current symbol table
extract($config);

// @todo move this to where the file processing actually takes place
@set_time_limit($time_limit);
@ini_set('max_input_time', $time_limit);

// @todo move this define to somewhere not dependent on the admin interface being loaded
define('EASYPOPULATE_VERSION', '3.9.5');

$ep_stack_sql_error = false; // function returns true on any 1 error, and notifies user of an error
$products_with_attributes = false; // langer - this will be redundant after html renovation
// @todo CHECK: maybe below can go in array eg $ep_processed['attributes'] = true, etc.. cold skip all post-upload tasks on check if isset var $ep_processed.
$has_attributes = false;


// all mods go in this array as 'name' => 'true' if exist. eg $ep_supported_mods['psd'] = true; means it exists.
// @todo scan array in future to reveal if any mods for inclusion in downloads
$ep_supported_mods = array();

$ep_debug_logging_all = $log_queries;
if ($log_queries) {
	// new blank log file on each page impression for full testing log (too big otherwise!!)
	$fp = fopen($temp_path . 'ep_debug_log.txt','w');
	fclose($fp);
}

/**
 * Pre-flight checks start here
 */
$chmod_check = is_dir($temp_path) && is_writable($temp_path);
if (!$chmod_check) {
	$messageStack->add(sprintf(EASYPOPULATE_MSGSTACK_TEMP_FOLDER_MISSING, $temp_path, DIR_FS_CATALOG), 'warning');
}

/**
 * START check for existence of various mods
 */
$ep_supported_mods['psd'] = false; //ep_field_name_exists(TABLE_PRODUCTS_DESCRIPTION,'products_short_desc');
$ep_supported_mods['uom'] = false; //ep_field_name_exists(TABLE_PRODUCTS_DESCRIPTION,'products_price_as'); // uom = unit of measure
$ep_supported_mods['upc'] = false; //ep_field_name_exists(TABLE_PRODUCTS_DESCRIPTION,'products_upc'); // upc = UPC Code
/**
 * END check for existance of various mods
 */

$category_strlen_max = zen_field_length(TABLE_CATEGORIES_DESCRIPTION, 'categories_name');

/**
 * Pre-flight checks finish here
 */

$langcode = zen_get_languages();
// start array at one, the rest of the code expects it that way
$langcode = array_combine(range(1, count($langcode)), array_values($langcode));

foreach ($langcode as $value) {
	if ($value['code'] == DEFAULT_LANGUAGE) {
		$epdlanguage_id = $value['id'];
		break;
	}
}

$ep_dltype = (isset($_GET['dltype'])) ? $_GET['dltype'] : NULL;

if (zen_not_null($ep_dltype)) {


	// START Create attributes array
	$attribute_options_array = array();
	if (isset($attribute_options_select) && is_array($attribute_options_select) && (count($attribute_options_select) > 0)) {
		// this limits the size of files where there are many options/attributes
		// Maybe we can automatically creat multiple files where column count is likely to exceed 256?
		foreach ($attribute_options_select as $value) {
			$attribute_options_query = "select distinct products_options_id from " . TABLE_PRODUCTS_OPTIONS . " where products_options_name = '" . zen_db_input($value) . "'";
			$attribute_options_values = ep_query($attribute_options_query);

			if ($attribute_options = mysql_fetch_array($attribute_options_values)){
				$attribute_options_array[] = array('products_options_id' => $attribute_options['products_options_id']);
			}
		}
	} else {
		$attribute_options_query = "select distinct products_options_id from " . TABLE_PRODUCTS_OPTIONS . " order by products_options_id";
		$attribute_options_values = ep_query($attribute_options_query);

		while ($attribute_options = mysql_fetch_array($attribute_options_values)){
			$attribute_options_array[] = array('products_options_id' => $attribute_options['products_options_id']);
		}
	}
	// END Create attributes array

	$filelayout = array();
	$fileheaders = array();

    // build filters
    $sql_filter = '';
    if (!empty($_GET['ep_category_filter'])) {
      $sub_categories = array();
      $categories_query_addition = 'ptoc.categories_id = ' . (int)$_GET['ep_category_filter'] . '';
      zen_get_sub_categories($sub_categories, $_GET['ep_category_filter']);
      foreach ($sub_categories AS $key => $category ) {
        $categories_query_addition .= ' OR ptoc.categories_id = ' . (int)$category . '';
      }
      $sql_filter .= ' AND (' . $categories_query_addition . ')';
    }
    if ($_GET['ep_manufacturer_filter']!='') {
      $sql_filter .= ' and p.manufacturers_id = ' . (int)$_GET['ep_manufacturer_filter'];
    }
    if ($_GET['ep_status_filter']!='') {
      $sql_filter .= ' AND p.products_status = ' . (int)$_GET['ep_status_filter'];
    }

	switch($ep_dltype){
	case 'full': // FULL products download
		// The file layout is dynamically made depending on the number of languages
		$fileMeta = array();

		$filelayout[] = 'v_products_model';
		$filelayout[] = 'v_products_image';

	 	$fileMeta[] = 'v_metatags_products_name_status';
		$fileMeta[] = 'v_metatags_title_status';
		$fileMeta[] = 'v_metatags_model_status';
		$fileMeta[] = 'v_metatags_price_status';
		$fileMeta[] = 'v_metatags_title_tagline_status';

		foreach ($langcode as $key => $lang){
			$l_id = $lang['id'];

			$filelayout[] = 'v_products_name_' . $l_id;
			$filelayout[] = 'v_products_description_' . $l_id;

			if ($ep_supported_mods['psd']) {
				$filelayout[] = 'v_products_short_desc_' . $l_id;
			}

			$filelayout[] = 'v_products_url_' . $l_id;

			$fileMeta[] = 'v_metatags_title_' . $l_id;
			$fileMeta[] = 'v_metatags_keywords_' . $l_id;
			$fileMeta[] = 'v_metatags_description_' . $l_id;
		}

		$filelayout[] = 'v_specials_price';
		$filelayout[] = 'v_specials_last_modified';
		$filelayout[] = 'v_specials_expires_date';
		$filelayout[] = 'v_products_price';

		if ($ep_supported_mods['uom']) {
			$filelayout[] = 'v_products_price_as';
		}

		if ($ep_supported_mods['upc']) {
			$filelayout[] = 'v_products_upc';
		}

		$filelayout[] = 'v_products_weight';
		$filelayout[] = 'v_product_is_call';
		$filelayout[] = 'v_products_sort_order';
		$filelayout[] = 'v_products_quantity_order_min';
		$filelayout[] = 'v_products_quantity_order_units';
		$filelayout[] = 'v_date_avail';
		$filelayout[] = 'v_date_added';
		$filelayout[] = 'v_products_quantity';

		if ($products_with_attributes) {
			$attributes_layout = ep_filelayout_attributes();
			$filelayout = array_merge($filelayout, $attributes_layout);
		}

		$filelayout[] = 'v_manufacturers_name';

		// build the categories name options based on the max categories configuration setting
		for($i=1;$i<$max_categories+1;$i++){
			$filelayout[] = 'v_categories_name_' . $i;
		}

		$filelayout[] = 'v_tax_class_title';
		$filelayout[] = 'v_status';

		//	START custom fields
		$custom_layout_sql = ' ';
		if(count($custom_fields) > 0) {
			foreach($custom_fields as $f) {
				if (empty($f)) continue;
				$filelayout[] = 'v_'.$f;
				$custom_filelayout_sql .= ', p.'.$f.' as v_'.$f.' ';
			}
		}
		// END custom fields

		$filelayout = array_merge($filelayout, $fileMeta);

		$filelayout_sql = 'SELECT
			p.products_id as v_products_id,
			p.products_model as v_products_model,
			p.products_image as v_products_image,
			p.products_price as v_products_price,';

		if ($ep_supported_mods['uom'] == true) {
			$filelayout_sql .=  'p.products_price_as as v_products_price_as,';
		}
		if ($ep_supported_mods['upc']) {
			$filelayout_sql .=  'p.products_upc as v_products_upc,';
		}

			$filelayout_sql .= 'p.products_weight as v_products_weight,
			p.products_last_modified as v_last_modified,
			p.product_is_call as v_product_is_call,
			p.products_sort_order as v_products_sort_order,
			p.products_quantity_order_min as v_products_quantity_order_min,
			p.products_quantity_order_units	as v_products_quantity_order_units,
			p.products_date_available as v_date_avail,
			p.products_date_added as v_date_added,
			p.products_tax_class_id as v_tax_class_id,
			p.products_quantity as v_products_quantity,
			p.manufacturers_id as v_manufacturers_id,
			subc.categories_id as v_categories_id,
			p.products_status as v_status,
			p.metatags_title_status as v_metatags_title_status,
			p.metatags_products_name_status as v_metatags_products_name_status,
			p.metatags_model_status as v_metatags_model_status,
			p.metatags_price_status as v_metatags_price_status,
			p.metatags_title_tagline_status as v_metatags_title_tagline_status'.
			$custom_filelayout_sql.
			' FROM
			' . TABLE_PRODUCTS . ' as p,
			' . TABLE_CATEGORIES . ' as subc,
			' . TABLE_PRODUCTS_TO_CATEGORIES . ' as ptoc
			WHERE
			p.products_id = ptoc.products_id AND
			ptoc.categories_id = subc.categories_id' . $sql_filter;
		break;

	case 'priceqty':

		$filelayout[] = 'v_products_model';
		$filelayout[] = 'v_specials_price';
		$filelayout[] = 'v_specials_date_avail';
		$filelayout[] = 'v_specials_expires_date';
		$filelayout[] = 'v_products_price';
		if ($ep_supported_mods['uom']) {
			$filelayout[] = 'v_products_price_as';
		}
		$filelayout[] = 'v_products_quantity';

		/*
		$filelayout[] = 'v_customer_price_1';
		$filelayout[] = 'v_customer_group_id_1';
		$filelayout[] = 'v_customer_price_2';
		$filelayout[] = 'v_customer_group_id_2';
		$filelayout[] = 'v_customer_price_3';
		$filelayout[] = 'v_customer_group_id_3';
		$filelayout[] = 'v_customer_price_4';
		$filelayout[] = 'v_customer_group_id_4';
		$filelayout[] = 'v_last_modified';
		$filelayout[] = 'v_status';
		//*/

		$filelayout_sql = 'SELECT
			p.products_id as v_products_id,
			p.products_model as v_products_model,
			p.products_price as v_products_price,';

		if ($ep_supported_mods['uom']) {
			$filelayout_sql .=  'p.products_price_as as v_products_price_as,';
		}

		$filelayout_sql .= 'p.products_tax_class_id as v_tax_class_id,
			p.products_quantity as v_products_quantity
			FROM ' . TABLE_PRODUCTS . ' as p';
		break;

	case 'modqty':
		$filelayout[] = 'v_products_model';
		$filelayout[] = 'v_products_price';
		$filelayout[] = 'v_products_quantity';
		$filelayout[] = 'v_last_modified';
		$filelayout[] = 'v_status';

		/**
		 * uncomment the customer_price and customer_group to support multi-price per product contrib
		 * @todo modularize this
		$filelayout[] = 'v_customer_price_1';
		$filelayout[] = 'v_customer_group_id_1';
		$filelayout[] = 'v_customer_price_2';
		$filelayout[] = 'v_customer_group_id_2';
		$filelayout[] = 'v_customer_price_3';
		$filelayout[] = 'v_customer_group_id_3';
		$filelayout[] = 'v_customer_price_4';
		$filelayout[] = 'v_customer_group_id_4';
		$filelayout[] = 'v_last_modified';
		$filelayout[] = 'v_status';
		*/

		$filelayout_sql = 'SELECT
			p.products_id as v_products_id,
			p.products_model as v_products_model,
			p.products_price as v_products_price,
			p.products_quantity as v_products_quantity,
			p.products_last_modified as v_last_modified,
			p.products_status as v_status
			FROM '
			.TABLE_PRODUCTS.' as p';

		break;

	// @todo <chadd> quantity price breaks file layout
	// 09-30-09 Need a configuration variable to set the MAX discounts level
	//          then I will be able to generate $filelayout() dynamically
	case 'pricebreaks':
		$filelayout[] =	'v_products_model';
		$filelayout[] =	'v_products_price';

		if ($ep_supported_mods['uom']) {
			$filelayout[] = 'v_products_price_as';
		}

		$filelayout[] =	'v_products_discount_type';
		$filelayout[] =	'v_products_discount_type_from';

		for ($i=1;$i<$max_qty_discounts+1;$i++) {
			$filelayout[] = 'v_discount_id_' . $i;
			$filelayout[] = 'v_discount_qty_' . $i;
			$filelayout[] = 'v_discount_price_' . $i;
		}

		$filelayout_sql = 'SELECT
			p.products_id            as v_products_id,
			p.products_model         as v_products_model,
			p.products_price         as v_products_price,';

		if ($ep_supported_mods['uom']) {
			$filelayout_sql .=  'p.products_price_as as v_products_price_as,';
		}

		$filelayout_sql .= 'p.products_discount_type as v_products_discount_type,
			p.products_discount_type_from as v_products_discount_type_from
			FROM '
			.TABLE_PRODUCTS.' as p';
	break;

	case 'category':
		// The file layout is dynamically made depending on the number of languages
		$filelayout[] = 'v_products_model';

		// build the categories name section of the array based on the number of categories the user wants to have
		for($i=1;$i<$max_categories+1;$i++){
			$filelayout[] = 'v_categories_name_' . $i;
		}


		$filelayout_sql = 'SELECT
			p.products_id as v_products_id,
			p.products_model as v_products_model,
			subc.categories_id as v_categories_id
			FROM '
			.TABLE_PRODUCTS.'   as p,'
			.TABLE_CATEGORIES.' as subc,'
			.TABLE_PRODUCTS_TO_CATEGORIES.' as ptoc
			WHERE
			p.products_id = ptoc.products_id AND
			ptoc.categories_id = subc.categories_id';
		break;

	case 'froogle':
		// Map easypopulate field names to froogle names
		// The file layout is dynamically made depending on the number of languages

		$filetemp = array();

		$filetemp['product_url'] = 'v_froogle_products_url_1';
		$filetemp['name'] = 'v_froogle_products_name_1';
		$filetemp['description'] = 'v_froogle_products_description_1';
		$filetemp['price'] = 'v_products_price';
		$filetemp['image_url'] = 'v_products_fullpath_image';
		$filetemp['category'] = 'v_category_fullpath';
		$filetemp['offer_id'] = 'v_froogle_offer_id';
		$filetemp['instock'] = 'v_froogle_instock';
		$filetemp['shipping'] = 'v_froogle_shipping';
		$filetemp['brand'] = 'v_manufacturers_name';
		$filetemp['upc'] = 'v_froogle_upc';
		//$filetemp['color'] = 'v_froogle_color';
		//$filetemp['size'] = 'v_froogle_size';
		//$filetemp['quantity'] = 'v_froogle_quantitylevel';
		//$filetemp['product_id'] = 'v_froogle_product_id';
		$filetemp['manufacturer_id'] = 'v_froogle_manufacturer_id';
		//$filetemp['exp_date'] = 'v_froogle_exp_date';
		$filetemp['product_type'] = 'v_froogle_product_type';
		//$filetemp['delete'] = 'v_froogle_delete';
		$filetemp['currency'] = 'v_froogle_currency';


		$fileheaders = array_keys($filetemp);
		$filelayout = array_values($filetemp);

		$filelayout_sql = "SELECT
			p.products_id as v_products_id,
			p.products_model as v_products_model,
			p.products_image as v_products_image,
			p.products_price as v_products_price,
			p.products_weight as v_products_weight,
			p.products_date_added as v_date_added,
			p.products_last_modified as v_last_modified,
			p.products_tax_class_id as v_tax_class_id,
			p.products_quantity as v_products_quantity,
			p.manufacturers_id as v_manufacturers_id,
			subc.categories_id as v_categories_id".
			$custom_filelayout_sql.
			" FROM
			".TABLE_PRODUCTS." as p,
			".TABLE_CATEGORIES." as subc,
			".TABLE_PRODUCTS_TO_CATEGORIES." as ptoc
			WHERE
			p.products_id = ptoc.products_id AND
			ptoc.categories_id = subc.categories_id AND
			p.products_status = '1'
			";
		break;

	case 'attrib':

		$filelayout[] = 'v_products_model';
		$attribute_layout = ep_filelayout_attributes();
		$filelayout = array_merge($filelayout, $attributes_layout);

		$filelayout_sql = "SELECT
			p.products_id as v_products_id,
			p.products_model as v_products_model
			FROM
			".TABLE_PRODUCTS." as p
			";

		break;

	case 'attrib_basic':
		$filelayout[] =	'v_products_attributes_id';
		$filelayout[] =	'v_products_id';
		$filelayout[] =	'v_products_model'; // product model from table PRODUCTS
		$filelayout[] =	'v_options_id';
		$filelayout[] =	'v_products_options_name'; // options name from table PRODUCTS_OPTIONS
		$filelayout[] =	'v_products_options_type'; // 0-drop down, 1=text , 2=radio , 3=checkbox, 4=file, 5=read only
		$filelayout[] =	'v_options_values_id';
		$filelayout[] =	'v_products_options_values_name'; // options values name from table PRODUCTS_OPTIONS_VALUES

		$filelayout_sql = 'SELECT
			a.products_attributes_id            as v_products_attributes_id,
			a.products_id                       as v_products_id,
			p.products_model				    as v_products_model,
			a.options_id                        as v_options_id,
			o.products_options_id               as v_products_options_id,
			o.products_options_name             as v_products_options_name,
			o.products_options_type             as v_products_options_type,
			a.options_values_id                 as v_options_values_id,
			v.products_options_values_id        as v_products_options_values_id,
			v.products_options_values_name      as v_products_options_values_name
			FROM '
			.TABLE_PRODUCTS_ATTRIBUTES.     ' as a,'
			.TABLE_PRODUCTS.                ' as p,'
			.TABLE_PRODUCTS_OPTIONS.        ' as o,'
			.TABLE_PRODUCTS_OPTIONS_VALUES. ' as v
			WHERE
			a.products_id       = p.products_id AND
			a.options_id        = o.products_options_id AND
			a.options_values_id = v.products_options_values_id'
			;
		break;

	case 'options':
		$filelayout[] =	'v_products_options_id';
		$filelayout[] =	'v_language_id';
		$filelayout[] =	'v_products_options_name';
		$filelayout[] =	'v_products_options_sort_order';
		$filelayout[] =	'v_products_options_type';
		$filelayout[] =	'v_products_options_length';
		$filelayout[] =	'v_products_options_comment';
		$filelayout[] =	'v_products_options_size';
		$filelayout[] =	'v_products_options_images_per_row';
		$filelayout[] =	'v_products_options_images_style';
		$filelayout[] =	'v_products_options_rows';

		$filelayout_sql = 'SELECT
			o.products_options_id             AS v_products_options_id,
			o.language_id                     AS v_language_id,
			o.products_options_name           AS v_products_options_name,
			o.products_options_sort_order     AS v_products_options_sort_order,
			o.products_options_type           AS v_products_options_type,
			o.products_options_length         AS v_products_options_length,
			o.products_options_comment        AS v_products_options_comment,
			o.products_options_size           AS v_products_options_size,
			o.products_options_images_per_row AS v_products_options_images_per_row,
			o.products_options_images_style   AS v_products_options_images_style,
			o.products_options_rows           AS v_products_options_rows '
			.' FROM '
			.TABLE_PRODUCTS_OPTIONS. ' AS o';
		break;

	case 'values':
		$filelayout[] =	'v_products_options_values_id';
		$filelayout[] =	'v_language_id';
		$filelayout[] =	'v_products_options_values_name';
		$filelayout[] =	'v_products_options_values_sort_order';

		$filelayout_sql = 'SELECT
			v.products_options_values_id         AS v_products_options_values_id,
			v.language_id                        AS v_language_id,
			v.products_options_values_name       AS v_products_options_values_name,
			v.products_options_values_sort_order AS v_products_options_values_sort_order '
			.' FROM '
			.TABLE_PRODUCTS_OPTIONS_VALUES. ' AS v';
		break;

	case 'optionvalues':
		$filelayout[] =	'v_products_options_values_to_products_options_id';
		$filelayout[] =	'v_products_options_id';
		$filelayout[] =	'v_products_options_name';
		$filelayout[] =	'v_products_options_values_id';
		$filelayout[] =	'v_products_options_values_name';

		$filelayout_sql = 'SELECT
			otv.products_options_values_to_products_options_id AS v_products_options_values_to_products_options_id,
			otv.products_options_id           AS v_products_options_id,
			o.products_options_name           AS v_products_options_name,
			otv.products_options_values_id    AS v_products_options_values_id,
			v.products_options_values_name    AS v_products_options_values_name '
			.' FROM '
			.TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS. ' AS otv, '
			.TABLE_PRODUCTS_OPTIONS.        ' AS o, '
			.TABLE_PRODUCTS_OPTIONS_VALUES. ' AS v
			WHERE
			otv.products_options_id        = o.products_options_id AND
			otv.products_options_values_id = v.products_options_values_id';
		break;
	}

	$filelayout = array_flip($filelayout);
	$fileheaders = array_flip($fileheaders);
	$filelayout_count = count($filelayout);

}

//*******************************
//*******************************
// END INITIALIZATION
//*******************************
//*******************************

$ep_dlmethod = isset($_GET['download']) ? $_GET['download'] : NULL;
if ($ep_dlmethod == 'stream' or  $ep_dlmethod == 'tempfile'){
	//*******************************
	//*******************************
	// DOWNLOAD FILE
	//*******************************
	//*******************************

	//if ($_GET['dltype']=='froogle'){
		// set the things froogle wants at the top of the file
//    $filestring .= " html_escaped=YES\n";
//    $filestring .= " updates_only=NO\n";
//    $filestring .= " product_type=OTHER\n";
//    $filestring .= " quoted=YES\n";
	//}

	$result = ep_query($filelayout_sql);

	/**
	 * Here we need to allow for the mapping of internal field names to external field names
	 * default to all headers named like the internal ones
	 * the field mapping array only needs to cover those fields that need to have their name changed
	 */
	if (count($fileheaders) != 0 ) {
		// if they gave us fileheaders for the dl, then use them; only overridden by froogle atm
		// @todo <johnny> make it configurable
		$filelayout_header = $fileheaders;
	} else {
		// if no mapping was specified; use the internal field names for header names
		$filelayout_header = $filelayout;
	}

	$filestring = array();
	$filestring[] = array_keys($filelayout_header);

	$num_of_langs = count($langcode);

	while ($row = mysql_fetch_array($result, MYSQL_ASSOC)){

		// build the long full froogle image path
		// check for a large image else use medium else use small else no link
		// thanks to Tim Kroeger - www.breakmyzencart.com
		$products_image = (($row['v_products_image'] == PRODUCTS_IMAGE_NO_IMAGE) ? '' : $row['v_products_image']);
		$products_image_extension = substr($products_image, strrpos($products_image, '.'));
		$products_image_base = ereg_replace($products_image_extension . '$', '', $products_image);
		$products_image_medium = $products_image_base . IMAGE_SUFFIX_MEDIUM . $products_image_extension;
		$products_image_large = $products_image_base . IMAGE_SUFFIX_LARGE . $products_image_extension;
		if (!file_exists(DIR_FS_CATALOG_IMAGES . 'large/' . $products_image_large)) {
			if (!file_exists(DIR_FS_CATALOG_IMAGES . 'medium/' . $products_image_medium)) {
				$image_url = (($products_image == '') ? '' : DIR_WS_CATALOG_IMAGES . $products_image);
			} else {
				$image_url = DIR_WS_CATALOG_IMAGES . 'medium/' . $products_image_medium;
			}
		} else {
			$image_url = DIR_WS_CATALOG_IMAGES . 'large/' . $products_image_large;
		}

		$row['v_products_fullpath_image'] = $image_url;

		// Other froogle defaults go here for now
		$row['v_froogle_instock']     = 'Y';
		$row['v_froogle_shipping']    = '';
		$row['v_froogle_upc']       = '';
//		$row['v_froogle_color']     = '';
//		$row['v_froogle_size']      = '';
//		$row['v_froogle_quantitylevel']   = '';
		$row['v_froogle_manufacturer_id'] = '';
//		$row['v_froogle_exp_date']    = '';
		$row['v_froogle_product_type']    = 'OTHER';
//		$row['v_froogle_delete']    = '';
		$row['v_froogle_currency']    = 'usd';
		$row['v_froogle_offer_id']    = $row['v_products_model'];
//		$row['v_froogle_product_id']    = $row['v_products_model'];

		foreach ($langcode as $key => $lang) {
			$lid = $lang['id'];

			// START product meta tags
			$sqlMeta = 'SELECT * FROM '.TABLE_META_TAGS_PRODUCTS_DESCRIPTION.'
							WHERE products_id = '.$row['v_products_id'].
							' AND language_id = '.$lid.' LIMIT 1 ';
			$resultMeta = ep_query($sqlMeta);
			$rowMeta = mysql_fetch_array($resultMeta);
			$row['v_metatags_title_' . $lid] = $rowMeta['metatags_title'];
			$row['v_metatags_keywords_' . $lid] = $rowMeta['metatags_keywords'];
			$row['v_metatags_description_' . $lid] = $rowMeta['metatags_description'];
			//END product meta tags

			// for each language, get the description and set the vals
			$sql2 = 'SELECT * FROM ' . TABLE_PRODUCTS_DESCRIPTION . ' WHERE
					products_id = ' . $row['v_products_id'] . ' AND
					language_id = ' . $lid . ' LIMIT 1';
			$result2 = ep_query($sql2);
			$row2 =  mysql_fetch_array($result2);

			$row['v_products_name_' . $lid] = $row2['products_name'];
			$row['v_products_description_' . $lid]  = $row2['products_description'];
			if ($ep_supported_mods['psd']) {
				$row['v_products_short_desc_' . $lid]   = $row2['products_short_desc'];
			}
			$row['v_products_url_' . $lid]    = $row2['products_url'];

			// froogle advanced format needs the quotes around the name and desc

			$row['v_froogle_products_name_' . $lid] = '"' . html_entity_decode(strip_tags(str_replace('"','""',$row2['products_name']))) . '"';
			$row['v_froogle_products_description_' . $lid] = '"' . html_entity_decode(strip_tags(str_replace('"','""',$row2['products_description']))) . '"';
			/*
			$row['v_froogle_products_name_' . $lid] = '"' . html_entity_decode(removeTags(str_replace('"','""',$row2['products_name']))) . '"';
			$row['v_froogle_products_description_' . $lid] = '"' . html_entity_decode(removeTags(str_replace('"','""',$row2['products_description']))) . '"';
			*/
		}

		// START specials
		if (isset($filelayout['v_specials_price'])) {
			$row['v_specials_price'] = '';
			$row['v_specials_date_avail'] = '';
			$row['v_specials_expires_date'] = '';
			$specials_query = ep_query("SELECT
						specials_new_products_price,
						specials_date_available,
						expires_date
				FROM ".TABLE_SPECIALS."
				WHERE products_id = " . $row['v_products_id']);

			if (mysql_num_rows($specials_query)) {
				$ep_specials = mysql_fetch_array($specials_query);
				$row['v_specials_price'] = $ep_specials['specials_new_products_price'];
				$row['v_specials_date_avail'] = $ep_specials['specials_date_available'];
				$row['v_specials_expires_date'] = $ep_specials['expires_date'];
			}
		}
		// END specials

		/**
		 * We need to keep looping until we find the root category
		 *
		 * Start with v_categories_id
		 * Get the category description
		 * Set the appropriate variable name
		 * If parent_id is not null, then follow it up.
		 * We'll populate an array first, then decide where it goes in the layout
		 */
		$thecategory_id = $row['v_categories_id'];
		$fullcategory = ''; // this will have the entire category stack for froogle
		for( $categorylevel=1; $categorylevel<$max_categories+1; $categorylevel++){
			if (!empty($thecategory_id)){
				$sql2 = "SELECT categories_name
					FROM ".TABLE_CATEGORIES_DESCRIPTION."
					WHERE
						categories_id = " . $thecategory_id . " AND
						language_id = " . $epdlanguage_id ;
				$result2 = ep_query($sql2);
				$row2 =  mysql_fetch_array($result2);
				// only set it if we found something
				$temprow['v_categories_name_' . $categorylevel] = $row2['categories_name'];
				// now get the parent ID if there was one
				$sql3 = "SELECT parent_id
					FROM ".TABLE_CATEGORIES."
					WHERE
						categories_id = " . $thecategory_id;
				$result3 = ep_query($sql3);
				$row3 =  mysql_fetch_array($result3);
				$theparent_id = $row3['parent_id'];
				if ($theparent_id != ''){
					// there was a parent ID, lets set thecategoryid to get the next level
					$thecategory_id = $theparent_id;
				} else {
					// we have found the top level category for this item,
					$thecategory_id = false;
				}
				$fullcategory = $row2['categories_name'] . " > " . $fullcategory;
			} else {
				$temprow['v_categories_name_' . $categorylevel] = '';
			}
		}
		// now trim off the last ">" from the category stack
		$row['v_category_fullpath'] = substr($fullcategory,0,strlen($fullcategory)-3);

		// temprow has the old style low to high level categories.
		$newlevel = 1;
		// let's turn them into high to low level categories
		for( $categorylevel=6; $categorylevel>0; $categorylevel--){
			if ($temprow['v_categories_name_' . $categorylevel] != ''){
				$row['v_categories_name_' . $newlevel++] = $temprow['v_categories_name_' . $categorylevel];
			}
		}

		if (isset($filelayout['v_manufacturers_name'])){
			$row['v_manufacturers_name'] = '';
			if (!empty($row['v_manufacturers_id'])) {
				$sql2 = "SELECT manufacturers_name
					FROM ".TABLE_MANUFACTURERS."
					WHERE
					manufacturers_id = " . $row['v_manufacturers_id'];
				$result2 = ep_query($sql2);
				$row2 =  mysql_fetch_array($result2);
				$row['v_manufacturers_name'] = $row2['manufacturers_name'];
			}
		}


		// If you have other modules that need to be available, put them here

		// VJ product attribs begin
		if (isset($filelayout['v_attribute_options_id_1'])){
			$languages = zen_get_languages();

			$attribute_options_count = 1;
			foreach ($attribute_options_array as $attribute_options) {
				$row['v_attribute_options_id_' . $attribute_options_count]  = $attribute_options['products_options_id'];

				for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
					$lid = $languages[$i]['id'];

					$attribute_options_languages_query = "select products_options_name from " . TABLE_PRODUCTS_OPTIONS . " where products_options_id = '" . (int)$attribute_options['products_options_id'] . "' and language_id = '" . (int)$lid . "'";
					$attribute_options_languages_values = ep_query($attribute_options_languages_query);

					$attribute_options_languages = mysql_fetch_array($attribute_options_languages_values);

					$row['v_attribute_options_name_' . $attribute_options_count . '_' . $lid] = $attribute_options_languages['products_options_name'];
				}

				$attribute_values_query = "select products_options_values_id from " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " where products_options_id = '" . (int)$attribute_options['products_options_id'] . "' order by products_options_values_id";
				$attribute_values_values = ep_query($attribute_values_query);

				$attribute_values_count = 1;
				while ($attribute_values = mysql_fetch_array($attribute_values_values)) {
					$row['v_attribute_values_id_' . $attribute_options_count . '_' . $attribute_values_count]   = $attribute_values['products_options_values_id'];

					$attribute_values_price_query = "select options_values_price, price_prefix from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int)$row['v_products_id'] . "' and options_id = '" . (int)$attribute_options['products_options_id'] . "' and options_values_id = '" . (int)$attribute_values['products_options_values_id'] . "'";
					$attribute_values_price_values = ep_query($attribute_values_price_query);

					$attribute_values_price = mysql_fetch_array($attribute_values_price_values);

					$row['v_attribute_values_price_' . $attribute_options_count . '_' . $attribute_values_count]  = $attribute_values_price['price_prefix'] . $attribute_values_price['options_values_price'];

					for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
						$lid = $languages[$i]['id'];

						$attribute_values_languages_query = "select products_options_values_name from " . TABLE_PRODUCTS_OPTIONS_VALUES . " where products_options_values_id = '" . (int)$attribute_values['products_options_values_id'] . "' and language_id = '" . (int)$lid . "'";
						$attribute_values_languages_values = ep_query($attribute_values_languages_query);

						$attribute_values_languages = mysql_fetch_array($attribute_values_languages_values);

						$row['v_attribute_values_name_' . $attribute_options_count . '_' . $attribute_values_count . '_' . $lid] = $attribute_values_languages['products_options_values_name'];
					}

					$attribute_values_count++;
				}

				$attribute_options_count++;
			}
		}
		// VJ product attribs end

		// this is for the separate price per customer module
		if (isset($filelayout['v_customer_price_1'])){
			$sql2 = "SELECT
					customers_group_price,
					customers_group_id
				FROM
					".TABLE_PRODUCTS_GROUPS."
				WHERE
				products_id = " . $row['v_products_id'] . "
				ORDER BY
				customers_group_id"
				;
			$result2 = ep_query($sql2);
			$ll = 1;
			$row2 =  mysql_fetch_array($result2);
			while( $row2 ){
				$row['v_customer_group_id_' . $ll]  = $row2['customers_group_id'];
				$row['v_customer_price_' . $ll]   = $row2['customers_group_price'];
				$row2 = mysql_fetch_array($result2);
				$ll++;
			}
		}
		if ($ep_dltype == 'froogle'){
			// For froogle, we check the specials prices for any applicable specials, and use that price
			// by grabbing the specials id descending, we always get the most recently added special price
			$sql2 = "SELECT
					specials_new_products_price
				FROM
					".TABLE_SPECIALS."
				WHERE
				products_id = " . $row['v_products_id'] . " and
				status = 1 and
				expires_date < CURRENT_TIMESTAMP
				ORDER BY
					specials_id DESC"
				;
			$result2 = ep_query($sql2);
			$ll = 1;
			$row2 =  mysql_fetch_array($result2);
			if (!empty($row2)){
				// reset the products price to our special price if there is one for this product
				$row['v_products_price']  = $row2['specials_new_products_price'];
			}
		}

		// Price/Qty/Discounts - chadd
		 $discount_index = 1;
		 while (isset($filelayout['v_discount_id_'.$discount_index])) {
			if ($row['v_products_discount_type'] != '0') { // if v_products_discount_type == 0 then there are no quantity breaks
				$sql2 = 'SELECT discount_id, discount_qty, discount_price FROM '.
					TABLE_PRODUCTS_DISCOUNT_QUANTITY.' WHERE products_id = '.
					$row['v_products_id'].' AND discount_id='.$discount_index;
				$result2 = ep_query($sql2);
				$row2    = mysql_fetch_array($result2);
				$row['v_discount_id_'.$discount_index]    = $row2['discount_id'];
				$row['v_discount_price_'.$discount_index] = $row2['discount_price'];
				$row['v_discount_qty_'.$discount_index]   = $row2['discount_qty'];
			}
			$discount_index++;
		 }

		//We check the value of tax class and title instead of the id
		//Then we add the tax to price if $price_with_tax is set to 1
		$row_tax_multiplier     = ep_get_tax_class_rate($row['v_tax_class_id']);
		$row['v_tax_class_title']   = zen_get_tax_class_title($row['v_tax_class_id']);
		$row['v_products_price']  = round($row['v_products_price'] + ($price_with_tax * $row['v_products_price'] * $row_tax_multiplier / 100),2);

		$tempcsvrow = array();
		foreach( $filelayout as $key => $value ){
			// only the specified keys are used
			$tempcsvrow[] = $row[$key];
		}
		$filestring[] = $tempcsvrow;

	}

	switch ($ep_dltype) {
		case 'froogle':
			$col_delimiter = "\t";
			$col_enclosure = ' ';
			$filestring = array_map("kill_breaks", $filestring);
		break;
	}
	$export_file = 'EP-' . $ep_dltype . strftime('%Y%b%d-%H%M%S');
	// now either stream it to them or put it in the temp directory
	if ($ep_dlmethod == 'stream') {
		//*******************************
		// STREAM FILE
		//*******************************
		header("Content-type: text/csv");
		//header("Content-type: application/vnd.ms-excel"); // @todo make this configurable
		header("Content-disposition: attachment; filename=$export_file" . (($col_delimiter == ",")?".csv":".txt"));
		// Changed if using SSL, helps prevent program delay/timeout (add to backup.php also)
		if ($request_type== 'NONSSL'){
			header("Pragma: no-cache");
		} else {
			header("Pragma: ");
		}
		header("Expires: 0");

		$fp = fopen("php://output", "w+");
		foreach ($filestring as $line) {
			fputcsv($fp, $line, $col_delimiter, $col_enclosure);
		}

		die();
	} else {
		//*******************************
		// PUT FILE IN TEMP DIR
		//*******************************
		$tmpfpath = $temp_path . $export_file . (($col_delimiter == ",")?".csv":".txt");
		$fp = fopen( $tmpfpath, "w+");
		foreach ($filestring as $line) {
			fputcsv($fp, $line, $col_delimiter, $col_enclosure);
		}
		fclose($fp);
		$messageStack->add(sprintf(EASYPOPULATE_MSGSTACK_FILE_EXPORT_SUCCESS, $export_file, $tempdir), 'success');
	}
}


//*******************************
// UPLOADING OF FILES STARTS HERE
//*******************************
if (isset($_POST['import'])) {

	$output['specials'] = array();
	$output['errors'] = array();
	$output['info'] = '';
	// BEGIN PROCESSING DATA
	// @todo more error checking here
	$uploaded_file = !empty($_POST['local_file']) ? $_POST['local_file'] : $_FILES['uploaded_file'];
	$file_location = ep_handle_uploaded_file($uploaded_file);

	$fileInfo = new SplFileInfo($file_location);

	$output['info'] = sprintf(EASYPOPULATE_DISPLAY_FILE_SPEC, $fileInfo->getFileName(), $fileInfo->getSize());
	// @todo add this to the gui with the default value selected
	if (empty($upload_file_format) || !isset($upload_file_format)) {
		$upload_file_format = 'Standard';
	}

	if ($enable_advanced_smart_tags) $smart_tags = array_merge($advanced_smart_tags,$smart_tags);

	$fileInfo->setFileClass(EPFileUploadFactory::get($upload_file_format));
	$file = $fileInfo->openFile('r');
	//$output['errors'][] = EASYPOPULATE_DISPLAY_FILE_NOT_EXIST;
	//$output['errors'][] = EASYPOPULATE_DISPLAY_FILE_OPEN_FAILED;

	// model name length error handling
	$model_varchar = zen_field_length(TABLE_PRODUCTS, 'products_model');
	if (!isset($model_varchar)) {
		$messageStack->add(EASYPOPULATE_MSGSTACK_MODELSIZE_DETECT_FAIL, 'warning');
		$modelsize = 32;
	} else {
		$modelsize = $model_varchar;
	}

	if ($filelayout = $file->getFileLayout()) {

	$file->onFileStart();

	foreach ($file as $items) {
		$items = $file->handleRow($items);

		if (!isset($items['products_model']) && !zen_not_null($items['products_model'])) {
			$output_class = 'fail nomodel';
			$output_message = EASYPOPULATE_DISPLAY_RESULT_NO_MODEL;
			continue;
		}

		if (strlen($items['products_model']) > $modelsize) {
			$output_class = 'fail';
			$output_status = EASYPOPULATE_DISPLAY_RESULT_SKIPPED;
			$output_message = EASYPOPULATE_DISPLAY_RESULT_MODEL_NAME_LONG;
			continue;
		}

		// @todo should we just SELECT * ?
		$sql = 'SELECT
			p.products_id,
			p.products_model,
			p.products_image,
			p.products_price,';

		if ($ep_supported_mods['uom'] == true) {
			$sql .=  'p.products_price_as,';
		}
		if ($ep_supported_mods['upc'] == true) {
			$sql .=  'p.products_upc,';
		}

		$sql .= 'p.products_weight,
			p.products_discount_type,
			p.products_discount_type_from,
			p.product_is_call,
			p.products_sort_order,
			p.products_quantity_order_min,
			p.products_quantity_order_units,
			p.products_date_added as date_added,
			p.products_date_available as date_avail,
			p.products_tax_class_id as tax_class_id,
			p.products_quantity,
			p.products_status,
			p.manufacturers_id,
			subc.categories_id as categories_id'.
			" FROM
			".TABLE_PRODUCTS." as p,
			".TABLE_CATEGORIES." as subc,
			".TABLE_PRODUCTS_TO_CATEGORIES." as ptoc
			WHERE
			p.products_id = ptoc.products_id AND
			p.products_model = '" . zen_db_input($items['products_model']) . "' AND
			ptoc.categories_id = subc.categories_id";

		$result = ep_query($sql);

		$product_is_new = true;
		while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$output_class = 'success';
			$output_message = '';
			$output_data = array();
			$product_is_new = false;
			/*
			* Get current products descriptions and categories for this model from database
			* $row at present consists of current product data for above fields only (in $sql)
			*/

			// let's check and delete it if requested
			if ($items['status'] == 9) {
				$output_status = EASYPOPULATE_DISPLAY_RESULT_DELETED;
				$output_class = 'success deleted';
				ep_remove_product($items['products_model']);
				continue 2;
			}

			$sql2 = 'SELECT * FROM '.TABLE_PRODUCTS_DESCRIPTION.' WHERE products_id = '.
				$row['products_id'] . ' ORDER BY language_id';
			$result2 = ep_query($sql2);
			while ($row2 = mysql_fetch_array($result2)) {
				$row['products_name_' . $row2['language_id']] = $row2['products_name'];
				$row['products_description_' . $row2['language_id']] = $row2['products_description'];
				$row['products_url_' . $row2['language_id']] = $row2['products_url'];
				if ($ep_supported_mods['psd']) {
					$row['products_short_desc_' . $row2['language_id']] = $row2['products_short_desc'];
				}
			}

			/**
			 * START Categories
			 * Start with v_categories_id
			 * Get the category description
			 * Set the appropriate variable name
			 * If parent_id is not null, then follow it up.
			 */
			$thecategory_id = $row['categories_id'];// master category id

			for($categorylevel=1; $categorylevel<$max_categories+1; $categorylevel++){
				if (!empty($thecategory_id)){
					$sql2 = "SELECT categories_name
						FROM ".TABLE_CATEGORIES_DESCRIPTION."
						WHERE
							categories_id = " . $thecategory_id . " AND
							language_id = " . $epdlanguage_id ;
					$result2 = ep_query($sql2);
					$row2 = mysql_fetch_array($result2);
					$temprow['categories_name_' . $categorylevel] = $row2['categories_name'];

					$sql3 = "SELECT parent_id
						FROM ".TABLE_CATEGORIES."
						WHERE categories_id = " . $thecategory_id;
					$result3 = ep_query($sql3);
					$row3 =  mysql_fetch_array($result3);
					$theparent_id = $row3['parent_id'];
					if ($theparent_id != ''){
						// there was a parent ID, lets set $thecategory_id to get the next level
						$thecategory_id = $theparent_id;
					} else {
						// we have found the top level category for this item,
						$thecategory_id = false;
					}
				} else {
						$temprow['categories_name_' . $categorylevel] = '';
				}
			}
			// temprow has the old style low to high level categories.
			$newlevel = 1;
			// let's turn them into high to low level categories
			for( $categorylevel=$max_categories+1; $categorylevel>0; $categorylevel--){
				if ($temprow['categories_name_' . $categorylevel] != ''){
					$row['categories_name_' . $newlevel++] = $temprow['categories_name_' . $categorylevel];
				}
			}

			/**
			* retrieve current manufacturer name from db for this product if exist
			*/
			$row['manufacturers_name'] = '';
			if (!empty($row['manufacturers_id'])) {
				$sql2 = "SELECT manufacturers_name
					FROM ".TABLE_MANUFACTURERS."
					WHERE
					manufacturers_id = " . $row['manufacturers_id'];
				$result2 = ep_query($sql2);
				$row2 =  mysql_fetch_array($result2);
				$row['manufacturers_name'] = $row2['manufacturers_name'];
			}

			/**
			 * Get tax info for this product
			 * We check the value of tax class and title instead of the id
			 *Then we add the tax to price if $price_with_tax is set to true
			 */
			$row_tax_multiplier = ep_get_tax_class_rate($row['tax_class_id']);
			$row['tax_class_title'] = zen_get_tax_class_title($row['tax_class_id']);
			if ($price_with_tax){
				$row['products_price'] = round($row['products_price'] + ($row['products_price'] * $row_tax_multiplier / 100),2);
			}

			/**
			 * The following defaults all of our current data from our db ($row array) to our update variables (called internal variables here)
			 * eg $products_price = $row['products_price'];
			 * @todo <langer> CHECKME perhaps we should build onto this array with each $row assignment routing above, so as to default all data to existing database
			 * @todo <johnny> we shouln't use extract, just keeping for compatibility with the current code
			 */
			extract($row);
		}
		/**
		* We have now set our PRODUCT_TABLE vars for existing products, and got our default descriptions & categories in $row still
		* new products start here!
		*/

		/**
		* Data error checking
		* inputs: $items; $filelayout; $product_is_new (no reliance on $row)
		*/
		if ($product_is_new == true) {
			if (!zen_not_null(trim($items['categories_name_1']))) {
				// let's skip this new product without a master category..
				$output_class = 'fail';
				$output_status = EASYPOPULATE_DISPLAY_RESULT_SKIPPED;
				$output_message = sprintf(EASYPOPULATE_DISPLAY_RESULT_CATEGORY_NOT_FOUND, ' new');
				continue;
			}
		} else { // not new product
			if (!zen_not_null(trim($items['categories_name_1'])) && isset($filelayout['categories_name_1'])) {
				// let's skip this existing product without a master category but has the column heading
				// or should we just update it to result of $row (it's current category..)??
				$output_class = 'fail';
				$output_status = EASYPOPULATE_DISPLAY_RESULT_SKIPPED;
				$output_message  = sprintf(EASYPOPULATE_DISPLAY_RESULT_CATEGORY_NOT_FOUND, '');
				continue;
			}
		}
		/*
		* End data checking
		**/


		/**
		* langer - assign to our vars any new data from $items (from our file)
		* output is: $products_model = "modelofthing", $products_description_1 = "descofthing", etc for each file heading
		* any existing (default) data assigned above is overwritten here with the new vals from file
		*/
		extract($items);

		// so how to handle these?  we shouldn't build the array unless it's been giving to us.
		// The assumption is that if you give us names and descriptions, then you give us name and description for all applicable languages
		foreach ($langcode as $lang){
			$l_id = $lang['id'];
			if (isset($filelayout['products_name_' . $l_id ])){ // do for each language in our upload file if exist
				// convert language names from _1, _2, etc; into arrays [1], [2], etc
				$products_name[$l_id] = smart_tags($items['products_name_' . $l_id],$smart_tags,false);
				$products_description[$l_id] = $items['products_description_' . $l_id ];
				// if short descriptions exist
				if ($ep_supported_mods['psd'] == true) {
					$products_short_desc[$l_id] = smart_tags($items['products_short_desc_' . $l_id ],$smart_tags,$strip_smart_tags);
				}
				$products_url[$l_id] = smart_tags($items['products_url_' . $l_id ],$smart_tags,false);
			}
		}
		//elari... we get the tax_clas_id from the tax_title - from zencart??
		//on screen will still be displayed the tax_class_title instead of the id....
		if (isset($tax_class_title)){
			$tax_class_id = ep_get_tax_title_class_id($tax_class_title);
		}
		//we check the tax rate of this tax_class_id
		$row_tax_multiplier = ep_get_tax_class_rate($tax_class_id);

		//And we recalculate price without the included tax...
		//Since it seems display is made before, the displayed price will still include tax
		//This is same problem for the tax_class_id that display tax_class_title
		if ($price_with_tax) {
			$products_price = round( $products_price / (1 + ( $row_tax_multiplier * $price_with_tax/100) ), 4);
		}

		// if they give us one category, they give us all 6 categories
		// @todo this does not appear to support more than 7 categories??
		unset ($categories_name); // default to not set.

		if (isset($filelayout['categories_name_1'])) { // does category 1 column exist in our file..

			$category_strlen_long = false;// checks cat length does not exceed db, else exclude product from upload
			$newlevel = 1;
			for($categorylevel=6; $categorylevel>0; $categorylevel--) {
				if ($items['categories_name_' . $categorylevel] != '') {
					if (strlen($items['categories_name_' . $categorylevel]) > $category_strlen_max) $category_strlen_long = TRUE;
					$categories_name[$newlevel++] = $items['categories_name_' . $categorylevel]; // adding the category name values to $categories_name array
				}// null categories are not assigned
			}
			while( $newlevel < $max_categories+1){
				$categories_name[$newlevel++] = ''; // default the remaining items to nothing
			}
			if ($category_strlen_long) {
				$output_class = 'fail';
				$output_status = EASYPOPULATE_DISPLAY_RESULT_SKIPPED;
				$output_message = sprintf(EASYPOPULATE_DISPLAY_RESULT_CATEGORY_NAME_LONG, $category_strlen_max);
				continue;
			}
		}

		// default the stock if they spec'd it or if it's blank
		// @todo <chadd> we should try something like this $db_status = $products_status;
		$db_status = '1'; // default to active
		if ($status == '0'){
			// they told us to deactivate this item
			$db_status = '0';
		}
		if ($status == '1') { // request activate this item
			$db_status = '1';
		}
		if ($deactivate_on_zero_qty && $products_quantity == 0) {
			$db_status = '0';
		}

		if ($manufacturer_id == '') {
			$manufacturer_id = "NULL";
		}

		// OK, we need to convert the manufacturer's name into id's for the database
		if ( isset($manufacturers_name) && $manufacturers_name != '' ){
			$sql = "SELECT man.manufacturers_id as manID
				FROM ".TABLE_MANUFACTURERS." as man
				WHERE
					man.manufacturers_name = '" . zen_db_input($manufacturers_name) . "' LIMIT 1";
			$result = ep_query($sql);
			if ( $row =  mysql_fetch_array($result) ){
				$manufacturers_id = $row['manID'];
			} else {
				$data = array();
				$data['manufacturers_name'] = $manufacturers_name;
				$data['date_added'] = 'NOW()';
				$data['last_modified'] = 'NOW()';
				$query = ep_db_modify(TABLE_MANUFACTURERS, $data, 'INSERT');
				$result = ep_query($query);
				$manufacturers_id = mysql_insert_id();
			}
		}
		// if the categories names are set then try to update them
		if (isset($categories_name_1)) {
			// start from the highest possible category and work our way down from the parent
			$categories_id = 0;
			$theparent_id = 0;
			for ( $categorylevel=$max_categories+1; $categorylevel>0; $categorylevel-- ){
				$thiscategoryname = $categories_name[$categorylevel];
				if ( $thiscategoryname != ''){
					// we found a category name in this field

					// now the subcategory
					$sql = "SELECT cat.categories_id AS catID FROM ".TABLE_CATEGORIES." AS cat, ".TABLE_CATEGORIES_DESCRIPTION." AS des WHERE
							cat.categories_id = des.categories_id AND
							des.language_id = $epdlanguage_id AND
							cat.parent_id = " . $theparent_id . " AND
							des.categories_name = '" . zen_db_input($thiscategoryname) . "' LIMIT 1";
					$result = ep_query($sql);
					if ( $row = mysql_fetch_array($result) ){ // langer - null result here where len of $categories_name[] exceeds maximum in database
						$thiscategoryid = $row['catID'];
					} else {
						$data = array();
						// to add, we need to put stuff in categories and categories_description
						$data['parent_id'] = $theparent_id;
						$data['sort_order'] = 0;
						$data['date_added'] = 'NOW()';
						$data['last_modified'] = 'NOW()';
						$query = ep_db_modify(TABLE_CATEGORIES, $data, 'INSERT');
						$result = ep_query($query);

						$thiscategoryid = mysql_insert_id();

						$data = array();
						$data['categories_id'] = $thiscategoryid;
						$data['language_id'] = $epdlanguage_id;
						$data['categories_name'] = $thiscategoryname;
						$query = ep_db_modify(TABLE_CATEGORIES_DESCRIPTION, $data, 'INSERT');
						$result = ep_query($query);
					}
					// the current catid is the next level's parent
					$theparent_id = $thiscategoryid;
					$categories_id = $thiscategoryid; // keep setting this, we need the lowest level category ID later
				}
			}
		}

		// insert new, or update existing, product
		// @todo harmonize the indentation
			// First we check to see if this is a product in the current db.
			$result = ep_query("SELECT `products_id` FROM ".TABLE_PRODUCTS." WHERE (products_model = '" . zen_db_input($products_model) . "') LIMIT 1 ");

			$date_avail = ($date_avail == true) ? date("Y-m-d H:i:s",strtotime($date_avail)) : '';
			// if date added is null, let's keep the existing date in db..
			if (!$date_added && $row['date_added']) { $date_added = $row['date_added']; }
			$date_added = ($date_added) ? date("Y-m-d H:i:s",strtotime($date_added)) : 'NOW()';

			$product = array();
			$product['products_model']	= $products_model;
			$product['products_date_available'] = $date_avail;
			$product['products_date_added'] = $date_added;
			$product['products_last_modified'] = 'NOW()';
			$product['products_price'] = $products_price;
			$product['products_image'] = $products_image;
			$product['products_weight'] = $products_weight;
			$product['products_tax_class_id'] = $tax_class_id;
			$product['products_discount_type'] = $products_discount_type;
			$product['products_discount_type_from'] = $products_discount_type_from;
			$product['product_is_call'] = $product_is_call;
			$product['products_sort_order'] = $products_sort_order;
			$product['products_quantity_order_min'] = $products_quantity_order_min;
			$product['products_quantity_order_units'] = $products_quantity_order_units;
			$product['products_quantity']	= $products_quantity;
			$product['master_categories_id'] = $categories_id;
			$product['manufacturers_id'] = $manufacturers_id;
			$product['products_status'] = $db_status;
			$product['metatags_title_status'] = $metatags_title_status;
			$product['metatags_products_name_status']	= $metatags_products_name_status;
			$product['metatags_model_status'] = $metatags_model_status;
			$product['metatags_price_status'] = $metatags_price_status;
			$product['metatags_title_tagline_status']	= $metatags_title_tagline_status;

			if ($ep_supported_mods['uom']) {
				$product['products_price_as'] = $products_price_as;
			}
			if ($ep_supported_mods['upc']) {
					$product['products_upc'] = $products_upc;
			}

			if ($row = mysql_fetch_array($result)) {
				//UPDATING PRODUCT
				$products_id = $row['products_id'];

				$query = ep_db_modify(TABLE_PRODUCTS, $product, 'UPDATE', "products_id = $products_id");

				if ( ep_query($query) ) {
					$output_class = 'updated success';
					$output_status = EASYPOPULATE_DISPLAY_RESULT_UPDATE_PRODUCT;
				} else {
					$output_class = 'updated fail';
					$output_status =  EASYPOPULATE_DISPLAY_RESULT_UPDATE_PRODUCT_FAIL;
					$output_message = EASYPOPULATE_DISPLAY_RESULT_SQL_ERROR;
				}
			} else {
				//NEW PRODUCT
				$query = ep_db_modify(TABLE_PRODUCTS, $product, 'INSERT');
				if ( ep_query($query) ) {
					$products_id = mysql_insert_id();
					$output_class = 'new success';
					$output_status = EASYPOPULATE_DISPLAY_RESULT_NEW_PRODUCT;
				} else {
					$output_class = 'new fail';
					$output_status = EASYPOPULATE_DISPLAY_RESULT_NEW_PRODUCT_FAIL;
					$output_message = EASYPOPULATE_DISPLAY_RESULT_SQL_ERROR;
					continue; // langer - any new categories however have been created by now..Adding into product table needs to be 1st action?
				}
			}


			//*************************
			// Product Meta Start
			//*************************
			foreach ($metatags as $key => $metaData) {
				$data = array();
				$data['products_id'] = $products_id;
				$data['language_id'] = $key;
				$data['metatags_title']	= $metaData['title'];
				$data['metatags_keywords'] = $metaData['keywords'];
				$data['metatags_description']	= $metaData['description'];
				$query = "SELECT products_id FROM ".TABLE_META_TAGS_PRODUCTS_DESCRIPTION.
				" WHERE products_id = $products_id AND language_id = $key";
				$result = ep_query($query);
				if ($row = mysql_fetch_array($result)) {
					$where = "products_id = $products_id AND language_id = $key";
					$query = ep_db_modify(TABLE_META_TAGS_PRODUCTS_DESCRIPTION, $data, 'UPDATE', $where);
				} else {
					$query = ep_db_modify(TABLE_META_TAGS_PRODUCTS_DESCRIPTION, $data, 'INSERT');
				}
				$result = ep_query($query);
			}

			/**
			 * Update quantity price breaks
			 * if products_discount_type == 0 then there are no quantity breaks
			 */
			if (isset($items['products_discount_type']) && !empty($items['products_discount_type'])) {
				$sql = "SELECT `products_id` FROM ".TABLE_PRODUCTS_DISCOUNT_QUANTITY." WHERE (`products_id` = '$products_id') LIMIT 1 ";
				$result = ep_query($sql);
				if ($row = mysql_fetch_array($result)) {
					$sql = "DELETE FROM ".TABLE_PRODUCTS_DISCOUNT_QUANTITY." WHERE (`products_id` = '$products_id') ";
					$result = ep_query($sql);
				}
				for ($discount = 1; ; $discount++) {
					if (!isset($items['discount_qty_' .$discount])) break;
					if (!isset($items['discount_price_' .$discount])) break;
					if (empty($items['discount_qty_' .$discount])) continue;
					if (empty($items['discount_price_' .$discount])) continue;
					// Easier to start over than try to update individual discounts
					$data = array();
					$data['discount_id'] = $discount;
					$data['products_id'] = $products_id;
					$data['discount_qty'] = $items['discount_qty_' .$discount];
					$data['discount_price'] = $items['discount_price_' .$discount];
					$sql = ep_db_modify(TABLE_PRODUCTS_DISCOUNT_QUANTITY, $data, 'INSERT');
					$result = ep_query($sql);
				}
			}

			//*************************
			// Products Descriptions Start
			//*************************
			if (isset($products_name)){
			foreach( $products_name as $key => $name){
			if ($name != ''){

					$sql = "SELECT * FROM ".TABLE_PRODUCTS_DESCRIPTION." WHERE products_id = $products_id AND	language_id = " . $key . " LIMIT 1 ";
					$result = ep_query($sql);
					$data = array();
					$data['products_id']	= $products_id;
					$data['language_id']	= $key;
					$data['products_name'] = $name;
					$data['products_description']	= $products_description[$key];
					$data['products_url'] = $products_url[$key];

					if ($ep_supported_mods['psd']) {
						$data['products_short_desc'] = $products_short_desc[$key];
					}
					if (mysql_num_rows($result) == 0) {
						$query = ep_db_modify(TABLE_PRODUCTS_DESCRIPTION, $data, 'INSERT');
						$result = ep_query($query);
					} else {
						$where = "products_id = $products_id AND language_id = $key";
						$query = ep_db_modify(TABLE_PRODUCTS_DESCRIPTION, $data, 'UPDATE', $where);
						$result = ep_query($query);
					}
			}
			}
			}
			//*************************
			// Products Descriptions End
			//*************************

			/**
			 * Assign product to category if linked
			 * @todo <chadd> FIXME: this is buggy as instances occur when the master category id is INCORRECT!
			 */
			if (isset($categories_id)) { // find out if this product is listed in the category given
				$result_incategory = ep_query('SELECT
							'.TABLE_PRODUCTS_TO_CATEGORIES.'.products_id,
							'.TABLE_PRODUCTS_TO_CATEGORIES.'.categories_id
							FROM '.TABLE_PRODUCTS_TO_CATEGORIES.'
							WHERE
							'.TABLE_PRODUCTS_TO_CATEGORIES.'.products_id='.$products_id.' AND
							'.TABLE_PRODUCTS_TO_CATEGORIES.'.categories_id='.$categories_id);

				if (mysql_num_rows($result_incategory) == 0) {
					$data = array();
					$data['products_id'] = $products_id;
					$data['categories_id'] = $categories_id;
					$query = ep_db_modify(TABLE_PRODUCTS_TO_CATEGORIES, $data, 'INSERT');
					$res1 = ep_query($query);
				}
			}

			// START ATTRIBUTES
			if (isset($attributes) && !empty($attributes)) {
				$has_attributes = true;
				$attribute_rows = 1; // master row count
				$languages = zen_get_languages();

				// remove product attribute options linked to this product before proceeding further
				$attributes_clean_query = 'DELETE FROM ' . TABLE_PRODUCTS_ATTRIBUTES . ' WHERE products_id = ' . $products_id;
				ep_query($attributes_clean_query);

				foreach ($attributes as $attribute) {
					$option_id = $attribute['id'];
					$attribute_options_query = 'SELECT products_options_name FROM ' . TABLE_PRODUCTS_OPTIONS . '
						where products_options_id = ' . $option_id;
					$attribute_options_values = ep_query($attribute_options_query);
					// option table update begin
					// langer - does once initially for each model, for all options and languages in upload file
					if ($attribute_rows == 1) {
						// insert into options table if no option exists
						if (!mysql_num_rows($attribute_options_values)) {
							foreach($attribute['names'] as $lid => $name) {
								$data = array();
								$data['products_options_id'] = $option_id;
								$data['language_id'] = $lid;
								$data['products_options_name'] = $name;
								$query = ep_db_modify(TABLE_PRODUCTS_OPTIONS, $data, 'INSERT');
								$attribute_options_insert = ep_query($query);

							}
						} else { // update options table, if options already exists
							foreach($attribute['names'] as $lid => $name) {
								$attribute_options_update_lang_query = "select products_options_name from " . TABLE_PRODUCTS_OPTIONS . " where products_options_id = '" . (int)$option_id . "' and language_id ='" . (int)$lid . "'";
								$attribute_options_update_lang_values = ep_query($attribute_options_update_lang_query);

								$data = array();
								$data['products_options_id'] = $option_id;
								$data['language_id'] = $lid;
								$data['products_options_name'] = $name;
								// if option name doesn't exist for particular language, insert value
								if (!mysql_num_rows($attribute_options_update_lang_values)) {
									$query = ep_db_modify(TABLE_PRODUCTS_OPTIONS, $data, 'INSERT');
									$attribute_options_lang_insert = ep_query($query);
								} else { // if option name exists for particular language, update table
									$where = 'products_options_id =' . $option_id . ' AND language_id = ' . $lid;
									$query = ep_db_modify(TABLE_PRODUCTS_OPTIONS, $data, 'UPDATE', $where);
								}
								ep_query($query);
							}
						}
					}
					// option table update end

					foreach ($attribute['values'] as $values) {
						$attribute_values_query = "SELECT products_options_values_name FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . ' WHERE products_options_values_id = ' . (int)$values['id'];
						$attribute_values_values = ep_query($attribute_values_query);

						// options_values table update begin
						// langer - does once initially for each model, for all attributes and languages in upload file
						if ($attribute_rows == 1) {
							if (!mysql_num_rows($attribute_values_values)) {
								foreach($values['names'] as $lid => $name) {
									$data = array();
									$data['products_options_values_id'] = $values['id'];
									$data['language_id'] = $lid;
									$data['products_options_values_name'] = $name;
									$query = ep_db_modify(TABLE_PRODUCTS_OPTIONS_VALUES, $data, 'INSERT');
									$attribute_values_insert = ep_query($query);
								}

								// insert values to pov2po table
								$data = array();
								$data['products_options_id'] = $option_id;
								$data['products_options_values_id'] = $values['id'];
								$query = ep_db_modify(TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS, $data, 'INSERT');
								$attribute_values_pov2po = ep_query($query);
							} else { // update options table, if options already exists
								foreach($values['names'] as $lid => $name) {
									$attribute_values_update_lang_query = 'SELECT products_options_values_name FROM ' . TABLE_PRODUCTS_OPTIONS_VALUES . ' WHERE products_options_values_id = ' . (int)$values['id'] . ' and language_id =' . (int)$lid;
									$attribute_values_update_lang_values = ep_query($attribute_values_update_lang_query);
									$data = array();
									$data['products_options_values_id'] = $values['id'];
									$data['language_id'] = $lid;
									$data['products_options_values_name'] = $name;
									if (!mysql_num_rows($attribute_values_update_lang_values)) {
										$query = ep_db_modify(TABLE_PRODUCTS_OPTIONS_VALUES, $data, 'INSERT');
									} else {
										$where = 'products_options_values_id =' . $values['id'] . ' AND language_id = ' . $lid;
										$query = ep_db_modify(TABLE_PRODUCTS_OPTIONS_VALUES, $data, 'UPDATE' , $where);
									}
									$attribute_values_update = ep_query($query);
								}
							}
						}
						// options_values table update end

						// options_values price update begin
						if (isset($values['price']) && is_numeric($values['price'])) {
							$attribute_prices_query = 'SELECT options_values_price, price_prefix FROM ' . TABLE_PRODUCTS_ATTRIBUTES . ' WHERE products_id = ' . (int)$products_id . ' AND options_id =' . (int)$option_id . ' AND options_values_id = ' . (int)$values['id'];
							$attribute_prices_values = ep_query($attribute_prices_query);

							$attribute_values_price_prefix = ($values['price'] < 0) ? '-' : '+';

							// options_values_prices table update begin
							if (!mysql_num_rows($attribute_prices_values)) {
								$data = array();
								$data['products_id'] = $products_id;
								$data['options_id'] = $option_id;
								$data['options_values_id'] = $values['id'];
								$data['options_values_price'] = (float)$values['price'];
								$data['price_prefix'] = $attribute_values_price_prefix;
								$query = ep_db_modify(TABLE_PRODUCTS_ATTRIBUTES, $data, 'INSERT');
							} else {
								$where = 'products_id = ' . $products_id . '
											AND options_id = ' . $option_id . '
											AND options_values_id =' . $values['id'];
								$query = ep_db_modify(TABLE_PRODUCTS_ATTRIBUTES, $data, 'UPDATE', $where);
							}
							$attribute_prices_update = ep_query($query);
						}
						// options_values price update end
					}
				}
				$attribute_rows++;
			}
			unset($attributes);
			// END ATTRIBUTES

			/**
			* Specials
			* if a null value in specials price, do not add or update. If price = 0, let's delete it
			*/
			if (isset($specials_price) && zen_not_null($specials_price)) {
				$specials_message = '';
				$specials_status = '';
				if ($specials_price >= $products_price) {
					$specials_class = 'fail';
					$specials_status = EASYPOPULATE_DISPLAY_RESULT_SKIPPED;
					$specials_message = EASYPOPULATE_SPECIALS_PRICE_FAIL;
					//available function: zen_set_specials_status($specials_id, $status)
					// could alternatively make status inactive, and still upload..
					continue;
				}

				// if null (set further above), set forever, else get raw date
				$specials_date_avail = ($specials_date_avail == true) ? date("Y-m-d H:i:s",strtotime($specials_date_avail)) : "0001-01-01";
				$specials_expires_date = ($specials_expires_date == true) ? date("Y-m-d H:i:s",strtotime($specials_expires_date)) : "0001-01-01";

				$special = ep_query("SELECT products_id
											FROM " . TABLE_SPECIALS . "
											WHERE products_id = ". $products_id);
				$data = array();
				$data['products_id'] = $products_id;
				$data['specials_new_products_price'] = $specials_price;
				$data['specials_date_available'] = $specials_date_avail;
				$data['specials_last_modified'] = 'NOW()';
				$data['expires_date'] = $specials_expires_date;
				$data['status'] = 1;

				if (mysql_num_rows($special) == 0) {
					if ($specials_price == '0') {
						$specials_class = 'fail notfound';
						$specials_status = EASYPOPULATE_DISPLAY_RESULT_DELETE_NOT_FOUND;
						$specials_message = EASYPOPULATE_SPECIALS_DELETE_FAIL;
						continue;
					}
					$data['specials_date_added'] = 'NOW()';
					$query = ep_db_modify(TABLE_SPECIALS, $data, 'INSERT');

					$result = ep_query($query);
					$specials_class = 'new success';
					$specials_status = EASYPOPULATE_DISPLAY_RESULT_NEW_PRODUCT;
				} else {
					// existing product
					if ($specials_price == '0') {
						$db->Execute("delete from " . TABLE_SPECIALS . "
									 where products_id = '" . (int)$products_id . "'");
						$specials_class = 'delete success';
						$specials_status = EASYPOPULATE_DISPLAY_RESULT_DELETED;
						continue;
					}
					$query = ep_db_modify(TABLE_SPECIALS, $data, 'UPDATE', "products_id = $products_id");
					$result = ep_query($query);
					$specials_class = 'updated success';
					$specials_status = EASYPOPULATE_DISPLAY_RESULT_UPDATE_PRODUCT;
				}

				$specials_data = array($products_model, $products_name[$epdlanguage_id], $products_price , $specials_price);
				$output['specials'][] = array('status' => $specials_status, 'class' => $specials_class, 'message' => $specials_message, 'data' => $specials_data);
			}
			// end specials for this product
		$output_data = array_values($items);
		$output['items'][] = array('status' => $output_status, 'class' => $output_class, 'message' => $output_message, 'data' => $output_data);
		// end of row insertion code


		$file->onItemFinish($products_id, $products_model);
	}
}
	/**
	* Post-upload tasks start
	*/
	$file->onFileFinish();

	ep_update_prices();

	if (!empty($output['specials'])) {
		zen_expire_specials();
	}

	if ($has_attributes) {
		ep_update_attributes_sort_order();
	}

	/**
	* Post-upload tasks end
	*/
}

// END FILE UPLOADS

if ($ep_stack_sql_error == true) $messageStack->add(EASYPOPULATE_MSGSTACK_ERROR_SQL, 'caution');

/**
* this is a rudimentary date integrity check for references to any non-existant product_id entries
* this check ought to be last, so it checks the tasks just performed as a quality check of EP...
* @todo langer  data present in table products, but not in descriptions.. user will need product info, and decide to add description, or delete product
*/
if ($_GET['dross'] == 'delete') {
	ep_purge_dross();
	// now check it is really gone...
	$dross = ep_get_dross();
	if (zen_not_null($dross)) {
		$string = "Product debris corresponding to the following product_id(s) cannot be deleted by EasyPopulate:\n";
		foreach ($dross as $products_id => $langer) {
			$string .= $products_id . "\n";
		}
		$string .= "It is recommended that you delete this corrupted data using phpMyAdmin.\n\n";
		write_debug_log($string);
		$messageStack->add(EASYPOPULATE_MSGSTACK_DROSS_DELETE_FAIL, 'caution');
	} else {
		$messageStack->add(EASYPOPULATE_MSGSTACK_DROSS_DELETE_SUCCESS, 'success');
	}
} else { // elseif ($_GET['dross'] == 'check')
	// we can choose a config option: check always, or only on clicking a button
	// default action when not deleting existing debris is to check for it and alert when discovered..
	$dross = ep_get_dross();
	if (zen_not_null($dross)) {
		$messageStack->add(sprintf(EASYPOPULATE_MSGSTACK_DROSS_DETECTED, count($dross), zen_href_link(FILENAME_EASYPOPULATE, 'dross=delete')), 'caution');
	}
}

/**
 * Changes planned for GUI
 * @todo <johnny> process data via xhr method
 * @todo <johnny> show results via xhr method
 * @todo <langer> 1 input field for local and server updating
 * @todo <langer> default to update directly from HDD, with option to upload to temp, or update from temp
 * @todo <langer> List temp files with delete, etc options
 * @todo <langer> Auto detecting of mods - display list of (only) installed mods, with check-box to include in download
 * @todo <langer> may consider an auto-splitting feature if it can be done.
 *     Will detect speed of server, safe_mode etc and determine what splitting level is required (can be over-ridden of course)
 */
?>
<!DOCTYPE html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
	<title><?php echo TITLE; ?> - Easy Populate</title>
	<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
	<link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
	<script language="javascript" type="text/javascript" src="includes/menu.js"></script>
	<script language="javascript" type="text/javascript" src="includes/general.js"></script>
	<script language="javascript" type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.1/jquery.min.js"></script>

	<script type="text/javascript">
	$(document).ready(function() {
		cssjsmenu('navbar');
		$('#hoverJS').attr('disabled', 'disabled');
	});
	</script>
	<!--@todo: move this css to some other file -->
	<style type="text/css">
	label {
		font-weight: bold;
	}
	.results_table {
		border-collapse: collapse;
		border:1px solid #000;
	}
	.results_table .fail {
		background-color: #E68080;
	}
	.results_table .success {
		background-color: #85C285;
	}
	td.status {
		font-weight: bold;
	}
	</style>
</head>
<body>
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<div id="ep_header">
	<h1>Easy Populate <?php echo EASYPOPULATE_VERSION ?></h1>
</div>
<div>
	<form enctype="multipart/form-data" action="easypopulate.php" method="POST">
		<input type="hidden" name="MAX_FILE_SIZE" value="100000000">
		<input type="hidden" name="import" value="1">
		<fieldset>
			<legend>Load comma or tab delimited files</legend>
			<div>
			<label for="uploaded_file">Upload EP File</label>
			<input id="uploaded_file" name="uploaded_file" type="file" size="50">
			</div>
			<div>
			<label for="local_file">Import from Temp Dir (<?php echo $tempdir; ?>)</label>
			<input type="text" id="local_file" name="local_file" size="50">
			</div>
			<input type="submit" name="buttoninsert" value="Insert into db">
		</fieldset>
	</form>
		  <?php echo zen_draw_form('custom', 'easypopulate.php', 'id="custom"', 'get'); ?>
          <!--  <form ENCTYPE="multipart/form-data" ACTION="easypopulate.php?download=stream&dltype=full" METHOD="POST"> -->
                <div align = "left">
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

					echo zen_draw_input_field('dltype', 'full', ' style="padding: 0px"', false, 'submit');
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
					<td><a href="easypopulate.php?download=stream&amp;dltype=<?php echo $key ?>"><?php echo $value ?></a></td>
					<td><a href="easypopulate.php?download=tempfile&amp;dltype=<?php echo $key ?>"><?php echo $value ?></a></td>
				</tr>
			<?php } ?>
			</tbody>
			</table>
			<?php if ($products_with_attributes) { ?>
					<span class="fieldRequired"> * Attributes Included in Complete</span>
			<?php } else { ?>
					<span class="fieldRequired"> * Attributes Not Included in Complete</span>
			<?php } ?>
			<br />
			<?php if (isset($output['info'])) echo '<p>' . $output['info'] . '</p>'; ?>
			<?php if (!empty($output['errors'])) { ?>
				<p>Errors:</p>
				<?php foreach ($output['errors'] as $error) { ?>
					<p class="fail"><?php echo $error; ?></p>
				<?php } ?>
			<?php } ?>
			<?php if (!empty($output['items'])) { ?>
			<div><h2><?php echo EASYPOPULATE_DISPLAY_HEADING; ?></h2> Items Uploaded(<?php echo $file->itemCount;?>)</div>
			<table id="uploaded_products" class="results_table">
				<thead>
				<tr>
					<th><?php echo EASYPOPULATE_DISPLAY_STATUS; ?></th>
					<th><?php echo EASYPOPULATE_DISPLAY_MESSAGE; ?></th>
					<!-- @todo make sure the headers line up with the text in all cases -->
					<?php foreach (array_keys($filelayout) as $header) { ?>
						<th><?php echo $header ?></th>
					<?php } ?>
				</tr>
				</thead>
				<?php foreach ($output['items'] as $item) { ?>
					<tr class="<?php echo $item['class'] ?>">
						<td class="status"><?php echo $item['status'] ?></td>
						<td class="message"><?php echo $item['message'] ?></td>
						<?php foreach ($item['data'] as $data) { ?>
							<?php if (!is_array($data)) { ?>
								<td><?php echo print_el($data); ?></td>
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
							<td><?php echo print_el($data); ?></td>
						<?php } ?>
					</tr>
				<?php } ?>
			</table>
			<?php } ?>
			<?php if (is_dir($temp_path)) { ?>
			<div><h2>Uploaded Files</h2></div>
			<form id="uploaded_files" enctype="multipart/form-data" action="easypopulate.php" method="POST">
			<input type="hidden" name="import" value="1">
			<input type="hidden" name="local_file">
			<table>
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
						<td><input type="submit" onclick="this.form.local_file.value='<?php echo $tempFile->getFileName() ?>';" value="Import" /></td>
						<td><a href="<?php echo $linkBase . $tempFile->getFileName(); ?>"><?php echo $tempFile->getFileName(); ?></a></td>
						<td><?php echo round(($tempFile->getSize() / 1024)); ?> KB</td>
						<td><?php echo strftime(DATE_FORMAT_LONG, $tempFile->getMTime()); ?></td>
					</tr>
				<?php } ?>
				<?php } ?>
			</table>
			</form>
			<?php } ?>
</div>
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>