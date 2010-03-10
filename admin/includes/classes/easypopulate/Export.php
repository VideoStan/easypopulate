<?php
/**
 * EasyPopulate exports
 *
 * @package easypopulate
 * @author langer
 * @author johnny <johnny@localmomentum.net>
 * @author too many to list, see history.txt
 * @copyright 200?-2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 * @todo <johnny> actually make it a class 
 */
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
    if (isset($_GET['ep_category_filter']) && !empty($_GET['ep_category_filter'])) {
      $sub_categories = array();
      $categories_query_addition = 'ptoc.categories_id = ' . (int)$_GET['ep_category_filter'] . '';
      zen_get_sub_categories($sub_categories, $_GET['ep_category_filter']);
      foreach ($sub_categories AS $key => $category ) {
        $categories_query_addition .= ' OR ptoc.categories_id = ' . (int)$category . '';
      }
      $sql_filter .= ' AND (' . $categories_query_addition . ')';
    }
    if (isset($_GET['ep_manufacturer_filter']) && !empty($_GET['ep_manufacturer_filter'])) {
      $sql_filter .= ' and p.manufacturers_id = ' . (int)$_GET['ep_manufacturer_filter'];
    }
    if (isset($_GET['ep_status_filter']) && !empty($_GET['ep_status_filter'])) {
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
		$custom_filelayout_sql = ' ';
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
		for ($categorylevel= $max_categories; $categorylevel>0; $categorylevel--) {
			if ($temprow['v_categories_name_' . $categorylevel] != ''){
				$row['v_categories_name_' . $newlevel++] = $temprow['v_categories_name_' . $categorylevel];
			} else {
				$row['v_categories_name_' . $newlevel++] = '';
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
}
?>