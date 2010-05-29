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
 * @todo <johnny> use better encapsulation techniques
 */
class EasyPopulateExport extends EasyPopulateProcess
{
	public $fileName = '';
	public $tempFName = '';
	private $type = 'full';
	private $columnDelimiter = ',';
	private $columnEnclosure = '"';

	public function setFormat($type = 'full')
	{
		if ($type == 'froogle') {
			$this->columnDelimiter = "\t";
			$this->columnEnclosure = ' ';
		}
		$this->type = $type;
		$this->fileName = 'EP-' . $type . strftime('%Y%b%d-%H%M%S') . '.' . (($this->columnDelimiter == ',') ? 'csv' : 'txt');
	}

	public function run()
	{
		$ep_dltype = $this->type;
		/**
		 * START check for existence of various mods
		 */
		// all mods go in this array as 'name' => 'true' if exist. eg $ep_supported_mods['psd'] = true; means it exists.
		// @todo scan array in future to reveal if any mods for inclusion in downloads
		$ep_supported_mods = array();
		$ep_supported_mods['psd'] = false; //ep_field_name_exists(TABLE_PRODUCTS_DESCRIPTION,'products_short_desc');
		$ep_supported_mods['uom'] = false; //ep_field_name_exists(TABLE_PRODUCTS_DESCRIPTION,'products_price_as'); // uom = unit of measure
		$ep_supported_mods['upc'] = false; //ep_field_name_exists(TABLE_PRODUCTS_DESCRIPTION,'products_upc'); // upc = UPC Code
		/**
		 * END check for existance of various mods
		 */
		$products_with_attributes = false;
		extract(ep_get_config());
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

		//	START custom fields
		$custom_filelayout_sql = ' ';
		if(count($custom_fields) > 0) {
			foreach($custom_fields as $f) {
				if (empty($f)) continue;
				$filelayout[] = $f;
				$custom_filelayout_sql .= ', p.' . $f .' ';
			}
		}
		// END custom fields

		switch($ep_dltype){
		case 'full': // FULL products download
			// The file layout is dynamically made depending on the number of languages
			$fileMeta = array();

			$filelayout[] = 'products_model';
			$filelayout[] = 'products_image';

		 	$fileMeta[] = 'metatags_products_name_status';
			$fileMeta[] = 'metatags_title_status';
			$fileMeta[] = 'metatags_model_status';
			$fileMeta[] = 'metatags_price_status';
			$fileMeta[] = 'metatags_title_tagline_status';

			foreach ($langcode as $key => $lang){
				$l_id = $lang['id'];

				$filelayout[] = 'products_name_' . $l_id;
				$filelayout[] = 'products_description_' . $l_id;

				if ($ep_supported_mods['psd']) {
					$filelayout[] = 'products_short_desc_' . $l_id;
				}

				$filelayout[] = 'products_url_' . $l_id;

				$fileMeta[] = 'metatags_title_' . $l_id;
				$fileMeta[] = 'metatags_keywords_' . $l_id;
				$fileMeta[] = 'metatags_description_' . $l_id;
			}

			$filelayout[] = 'specials_price';
			$filelayout[] = 'specials_date_avail';
			$filelayout[] = 'specials_expires_date';
			$filelayout[] = 'products_price';

			if ($ep_supported_mods['uom']) {
				$filelayout[] = 'products_price_as';
			}

			if ($ep_supported_mods['upc']) {
				$filelayout[] = 'products_upc';
			}

			$filelayout[] = 'products_weight';
			$filelayout[] = 'product_is_call';
			$filelayout[] = 'products_sort_order';
			$filelayout[] = 'products_quantity_order_min';
			$filelayout[] = 'products_quantity_order_units';
			$filelayout[] = 'date_avail';
			$filelayout[] = 'date_added';
			$filelayout[] = 'products_quantity';

			if ($products_with_attributes) {
				$attributes_layout = $this->getAttributesFileLayout();
				$filelayout = array_merge($filelayout, $attributes_layout);
			}

			$filelayout[] = 'manufacturers_name';

			// build the categories name options based on the max categories configuration setting
			for($i=1;$i<$max_categories+1;$i++){
				$filelayout[] = 'categories_name_' . $i;
			}

			$filelayout[] = 'tax_class_title';
			$filelayout[] = 'status';

			$filelayout = array_merge($filelayout, $fileMeta);

			$filelayout_sql = 'SELECT
				p.products_id,
				p.products_model,
				p.products_image,
				p.products_price,';

			if ($ep_supported_mods['uom'] == true) {
				$filelayout_sql .=  'p.products_price_as,';
			}
			if ($ep_supported_mods['upc']) {
				$filelayout_sql .=  'p.products_upc,';
			}

				$filelayout_sql .= 'p.products_weight,
				p.products_last_modified,
				p.product_is_call,
				p.products_sort_order,
				p.products_quantity_order_min,
				p.products_quantity_order_units,
				p.products_date_available AS date_avail,
				p.products_date_added AS date_added,
				p.products_tax_class_id AS tax_class_id,
				p.products_quantity,
				p.manufacturers_id,
				subc.categories_id,
				p.products_status as status,
				p.metatags_title_status,
				p.metatags_products_name_status,
				p.metatags_model_status,
				p.metatags_price_status,
				p.metatags_title_tagline_status'.
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

			$filelayout[] = 'products_model';
			$filelayout[] = 'specials_price';
			$filelayout[] = 'specials_date_avail';
			$filelayout[] = 'specials_expires_date';
			$filelayout[] = 'products_price';
			if ($ep_supported_mods['uom']) {
				$filelayout[] = 'products_price_as';
			}
			$filelayout[] = 'products_quantity';

			/*
			$filelayout[] = 'customer_price_1';
			$filelayout[] = 'customer_group_id_1';
			$filelayout[] = 'customer_price_2';
			$filelayout[] = 'customer_group_id_2';
			$filelayout[] = 'customer_price_3';
			$filelayout[] = 'customer_group_id_3';
			$filelayout[] = 'customer_price_4';
			$filelayout[] = 'customer_group_id_4';
			$filelayout[] = 'last_modified';
			$filelayout[] = 'status';
			//*/

			$filelayout_sql = 'SELECT
				p.products_id,
				p.products_model,
				p.products_price,';

			if ($ep_supported_mods['uom']) {
				$filelayout_sql .=  'p.products_price_as,';
			}

			$filelayout_sql .= 'p.products_tax_class_id as tax_class_id,
				p.products_quantity
				FROM ' . TABLE_PRODUCTS . ' as p';
			break;

		case 'modqty':
			$filelayout[] = 'products_model';
			$filelayout[] = 'products_price';
			$filelayout[] = 'products_quantity';
			$filelayout[] = 'last_modified';
			$filelayout[] = 'status';

			/**
			 * uncomment the customer_price and customer_group to support multi-price per product contrib
			 * @todo modularize this
			$filelayout[] = 'customer_price_1';
			$filelayout[] = 'customer_group_id_1';
			$filelayout[] = 'customer_price_2';
			$filelayout[] = 'customer_group_id_2';
			$filelayout[] = 'customer_price_3';
			$filelayout[] = 'customer_group_id_3';
			$filelayout[] = 'customer_price_4';
			$filelayout[] = 'customer_group_id_4';
			$filelayout[] = 'last_modified';
			$filelayout[] = 'status';
			*/

			$filelayout_sql = 'SELECT
				p.products_id,
				p.products_model,
				p.products_price,
				p.products_quantity,
				p.products_last_modified as last_modified,
				p.products_status as status,
				p.products_tax_class_id  as tax_class_id
				FROM '
				.TABLE_PRODUCTS.' as p';

			break;

		// @todo <chadd> quantity price breaks file layout
		// 09-30-09 Need a configuration variable to set the MAX discounts level
		//          then I will be able to generate $filelayout() dynamically
		case 'pricebreaks':
			$filelayout[] =	'products_model';
			$filelayout[] =	'products_price';

			if ($ep_supported_mods['uom']) {
				$filelayout[] = 'products_price_as';
			}

			$filelayout[] =	'products_discount_type';
			$filelayout[] =	'products_discount_type_from';

			for ($i=1;$i<$max_qty_discounts+1;$i++) {
				$filelayout[] = 'discount_id_' . $i;
				$filelayout[] = 'discount_qty_' . $i;
				$filelayout[] = 'discount_price_' . $i;
			}

			$filelayout_sql = 'SELECT
				p.products_id,
				p.products_model,
				p.products_price,
				p.products_tax_class_id  as tax_class_id';

			if ($ep_supported_mods['uom']) {
				$filelayout_sql .=  'p.products_price_as,';
			}

			$filelayout_sql .= 'p.products_discount_type,
				p.products_discount_type_from
				FROM '
				.TABLE_PRODUCTS.' as p';
		break;

		case 'category':
			// The file layout is dynamically made depending on the number of languages
			$filelayout[] = 'products_model';

			// build the categories name section of the array based on the number of categories the user wants to have
			for($i=1;$i<$max_categories+1;$i++){
				$filelayout[] = 'categories_name_' . $i;
			}


			$filelayout_sql = 'SELECT
				p.products_id,
				p.products_model,
				subc.categories_id
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

			$filetemp['product_url'] = 'froogle_products_url_1';
			$filetemp['name'] = 'froogle_products_name_1';
			$filetemp['description'] = 'froogle_products_description_1';
			$filetemp['price'] = 'products_price';
			$filetemp['image_url'] = 'products_fullpath_image';
			$filetemp['category'] = 'category_fullpath';
			$filetemp['offer_id'] = 'froogle_offer_id';
			$filetemp['instock'] = 'froogle_instock';
			$filetemp['shipping'] = 'froogle_shipping';
			$filetemp['brand'] = 'manufacturers_name';
			$filetemp['upc'] = 'froogle_upc';
			//$filetemp['color'] = 'froogle_color';
			//$filetemp['size'] = 'froogle_size';
			//$filetemp['quantity'] = 'froogle_quantitylevel';
			//$filetemp['product_id'] = 'froogle_product_id';
			$filetemp['manufacturer_id'] = 'froogle_manufacturer_id';
			//$filetemp['exp_date'] = 'froogle_exp_date';
			$filetemp['product_type'] = 'froogle_product_type';
			//$filetemp['delete'] = 'froogle_delete';
			$filetemp['currency'] = 'froogle_currency';


			$fileheaders = array_keys($filetemp);
			$filelayout = array_values($filetemp);

			$filelayout_sql = "SELECT
				p.products_id,
				p.products_model,
				p.products_image,
				p.products_price,
				p.products_weight,
				p.products_date_added AS date_added,
				p.products_last_modified AS last_modified,
				p.products_tax_class_id AS tax_class_id,
				p.products_quantity,
				p.manufacturers_id,
				subc.categories_id".
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

			$filelayout[] = 'products_model';
			$attribute_layout = $this->getAttributesFileLayout();
			$filelayout = array_merge($filelayout, $attributes_layout);

			$filelayout_sql = "SELECT
				p.products_id,
				p.products_model
				FROM
				".TABLE_PRODUCTS." as p
				";

			break;

		case 'attrib_basic':
			$filelayout[] =	'products_attributes_id';
			$filelayout[] =	'products_id';
			$filelayout[] =	'products_model'; // product model from table PRODUCTS
			$filelayout[] =	'options_id';
			$filelayout[] =	'products_options_name'; // options name from table PRODUCTS_OPTIONS
			$filelayout[] =	'products_options_type'; // 0-drop down, 1=text , 2=radio , 3=checkbox, 4=file, 5=read only
			$filelayout[] =	'options_values_id';
			$filelayout[] =	'products_options_values_name'; // options values name from table PRODUCTS_OPTIONS_VALUES

			$filelayout_sql = 'SELECT
				a.products_attributes_id,
				a.products_id,
				p.products_model,
				a.options_id,
				o.products_options_id,
				o.products_options_name,
				o.products_options_type,
				a.options_values_id,
				v.products_options_values_id,
				v.products_options_values_name
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
			$filelayout[] =	'products_options_id';
			$filelayout[] =	'language_id';
			$filelayout[] =	'products_options_name';
			$filelayout[] =	'products_options_sort_order';
			$filelayout[] =	'products_options_type';
			$filelayout[] =	'products_options_length';
			$filelayout[] =	'products_options_comment';
			$filelayout[] =	'products_options_size';
			$filelayout[] =	'products_options_images_per_row';
			$filelayout[] =	'products_options_images_style';
			$filelayout[] =	'products_options_rows';

			$filelayout_sql = 'SELECT
				o.products_options_id,
				o.language_id,
				o.products_options_name,
				o.products_options_sort_order,
				o.products_options_type,
				o.products_options_length,
				o.products_options_comment,
				o.products_options_size,
				o.products_options_images_per_row,
				o.products_options_images_style,
				o.products_options_rows'
				.' FROM '
				.TABLE_PRODUCTS_OPTIONS. ' AS o';
			break;

		case 'values':
			$filelayout[] =	'products_options_values_id';
			$filelayout[] =	'language_id';
			$filelayout[] =	'products_options_values_name';
			$filelayout[] =	'products_options_values_sort_order';

			$filelayout_sql = 'SELECT
				v.products_options_values_id,
				v.language_id,
				v.products_options_values_name,
				v.products_options_values_sort_order'
				.' FROM '
				.TABLE_PRODUCTS_OPTIONS_VALUES. ' AS v';
			break;

		case 'optionvalues':
			$filelayout[] =	'products_options_values_to_products_options_id';
			$filelayout[] =	'products_options_id';
			$filelayout[] =	'products_options_name';
			$filelayout[] =	'products_options_values_id';
			$filelayout[] =	'products_options_values_name';

			$filelayout_sql = 'SELECT
				otv.products_options_values_to_products_options_id,
				otv.products_options_id,
				o.products_options_name,
				otv.products_options_values_id,
				v.products_options_values_name'
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

		//@todo ? $tempFName = tempnam(ep_get_config('temp_path'), 'eptemp-');
		$tempFName = tempnam('/tmp', '');
		$tempFile = new EasyPopulateCsvFileObject($tempFName , 'w+');
		$tempFile->setCsvControl($this->columnDelimiter, stripslashes($this->columnEnclosure));

		$header = array();
		foreach ($filelayout_header as $key => $value) {
			$header[] = 'v_' . $key; // @todo maybe set the key prefix in EasyPopulateCsvFileObject ?
		}
		$tempFile->setFileLayout($header, true);
		$num_of_langs = count($langcode);

		while ($row = mysql_fetch_array($result, MYSQL_ASSOC)){

			// build the long full froogle image path
			// check for a large image else use medium else use small else no link
			// thanks to Tim Kroeger - www.breakmyzencart.com
			if (isset($row['products_image'])) {
			$products_image = (($row['products_image'] == PRODUCTS_IMAGE_NO_IMAGE) ? '' : $row['products_image']);
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

			$row['products_fullpath_image'] = $image_url;
			}
			// Other froogle defaults go here for now
			$row['froogle_instock']     = 'Y';
			$row['froogle_shipping']    = '';
			$row['froogle_upc']       = '';
	//		$row['froogle_color']     = '';
	//		$row['froogle_size']      = '';
	//		$row['froogle_quantitylevel']   = '';
			$row['froogle_manufacturer_id'] = '';
	//		$row['froogle_exp_date']    = '';
			$row['froogle_product_type']    = 'OTHER';
	//		$row['froogle_delete']    = '';
			$row['froogle_currency']    = 'usd';
			$row['froogle_offer_id']    = $row['products_model'];
	//		$row['froogle_product_id']    = $row['products_model'];

			foreach ($langcode as $key => $lang) {
				$lid = $lang['id'];

				// START product meta tags
				$sqlMeta = 'SELECT * FROM '.TABLE_META_TAGS_PRODUCTS_DESCRIPTION.'
								WHERE products_id = '.$row['products_id'].
								' AND language_id = '.$lid.' LIMIT 1 ';
				$resultMeta = ep_query($sqlMeta);
				$rowMeta = mysql_fetch_array($resultMeta);
				$row['metatags_title_' . $lid] = $rowMeta['metatags_title'];
				$row['metatags_keywords_' . $lid] = $rowMeta['metatags_keywords'];
				$row['metatags_description_' . $lid] = $rowMeta['metatags_description'];
				//END product meta tags

				// for each language, get the description and set the vals
				$sql2 = 'SELECT * FROM ' . TABLE_PRODUCTS_DESCRIPTION . ' WHERE
						products_id = ' . $row['products_id'] . ' AND
						language_id = ' . $lid . ' LIMIT 1';
				$result2 = ep_query($sql2);
				$row2 =  mysql_fetch_array($result2);

				$row['products_name_' . $lid] = $row2['products_name'];
				$row['products_description_' . $lid]  = $row2['products_description'];
				if ($ep_supported_mods['psd']) {
					$row['products_short_desc_' . $lid]   = $row2['products_short_desc'];
				}
				$row['products_url_' . $lid]    = $row2['products_url'];

				// froogle advanced format needs the quotes around the name and desc
				$row['froogle_products_name_' . $lid] = '"' . html_entity_decode(strip_tags(str_replace('"','""',$row2['products_name']))) . '"';
				$row['froogle_products_description_' . $lid] = '"' . html_entity_decode(strip_tags(str_replace('"','""',$row2['products_description']))) . '"';
				$row['froogle_products_url_' . $lid] = $row['products_url_' . $lid];
			}

			// START specials
			if (isset($filelayout['specials_price'])) {
				$row['specials_price'] = '';
				$row['specials_date_avail'] = '';
				$row['specials_expires_date'] = '';
				$specials_query = ep_query("SELECT
							specials_new_products_price,
							specials_date_available,
							expires_date
					FROM ".TABLE_SPECIALS."
					WHERE products_id = " . $row['products_id']);

				if (mysql_num_rows($specials_query)) {
					$ep_specials = mysql_fetch_array($specials_query);
					$row['specials_price'] = $ep_specials['specials_new_products_price'];
					$row['specials_date_avail'] = $ep_specials['specials_date_available'];
					$row['specials_expires_date'] = $ep_specials['expires_date'];
				}
			}
			// END specials

			if (isset($row['categories_id'])) {
				$categories = $this->getCategories($row['categories_id']);
				$categories = array_slice($categories, 0, $max_categories, true);
				if ($ep_dltype == 'froogle') {
					$fullcategory = ''; // @todo move to froogle output
					foreach ($categories as $k => $v) {
						$fullcategory .= $v . ' > ';
					}
					// now trim off the last ">" from the category stack
					$row['category_fullpath'] = substr($fullcategory,0,strlen($fullcategory)-3);
				}
				$categories = array_pad($categories, $max_categories, '');
				foreach ($categories as $k => $v) {
					$row['categories_name_' . ($k + 1)] = $categories[$k];
				}
			}

			$row['manufacturers_name'] = '';
			if (isset($filelayout['manufacturers_name'])){
				$row['manufacturers_name'] = $this->getManufacturerName($row['manufacturers_id']);
			}


			// If you have other modules that need to be available, put them here

			// VJ product attribs begin
			if (isset($filelayout['attribute_options_id_1'])){
				$languages = zen_get_languages();

				$attribute_options_count = 1;
				foreach ($attribute_options_array as $attribute_options) {
					$row['attribute_options_id_' . $attribute_options_count]  = $attribute_options['products_options_id'];

					for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
						$lid = $languages[$i]['id'];

						$attribute_options_languages_query = "select products_options_name from " . TABLE_PRODUCTS_OPTIONS . " where products_options_id = '" . (int)$attribute_options['products_options_id'] . "' and language_id = '" . (int)$lid . "'";
						$attribute_options_languages_values = ep_query($attribute_options_languages_query);

						$attribute_options_languages = mysql_fetch_array($attribute_options_languages_values);

						$row['attribute_options_name_' . $attribute_options_count . '_' . $lid] = $attribute_options_languages['products_options_name'];
					}

					$attribute_values_query = "select products_options_values_id from " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " where products_options_id = '" . (int)$attribute_options['products_options_id'] . "' order by products_options_values_id";
					$attribute_values_values = ep_query($attribute_values_query);

					$attribute_values_count = 1;
					while ($attribute_values = mysql_fetch_array($attribute_values_values)) {
						$row['attribute_values_id_' . $attribute_options_count . '_' . $attribute_values_count]   = $attribute_values['products_options_values_id'];

						$attribute_values_price_query = "select options_values_price, price_prefix from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int)$row['products_id'] . "' and options_id = '" . (int)$attribute_options['products_options_id'] . "' and options_values_id = '" . (int)$attribute_values['products_options_values_id'] . "'";
						$attribute_values_price_values = ep_query($attribute_values_price_query);

						$attribute_values_price = mysql_fetch_array($attribute_values_price_values);

						$row['attribute_values_price_' . $attribute_options_count . '_' . $attribute_values_count]  = $attribute_values_price['price_prefix'] . $attribute_values_price['options_values_price'];

						for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
							$lid = $languages[$i]['id'];

							$attribute_values_languages_query = "select products_options_values_name from " . TABLE_PRODUCTS_OPTIONS_VALUES . " where products_options_values_id = '" . (int)$attribute_values['products_options_values_id'] . "' and language_id = '" . (int)$lid . "'";
							$attribute_values_languages_values = ep_query($attribute_values_languages_query);

							$attribute_values_languages = mysql_fetch_array($attribute_values_languages_values);

							$row['attribute_values_name_' . $attribute_options_count . '_' . $attribute_values_count . '_' . $lid] = $attribute_values_languages['products_options_values_name'];
						}

						$attribute_values_count++;
					}

					$attribute_options_count++;
				}
			}
			// VJ product attribs end

			// this is for the separate price per customer module
			if (isset($filelayout['customer_price_1'])){
				$sql2 = "SELECT
						customers_group_price,
						customers_group_id
					FROM
						".TABLE_PRODUCTS_GROUPS."
					WHERE
					products_id = " . $row['products_id'] . "
					ORDER BY
					customers_group_id"
					;
				$result2 = ep_query($sql2);
				$ll = 1;
				$row2 =  mysql_fetch_array($result2);
				while( $row2 ){
					$row['customer_group_id_' . $ll]  = $row2['customers_group_id'];
					$row['customer_price_' . $ll]   = $row2['customers_group_price'];
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
					products_id = " . $row['products_id'] . " and
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
					$row['products_price']  = $row2['specials_new_products_price'];
				}
			}

			// Price/Qty/Discounts - chadd
			 $discount_index = 1;
			 while (isset($filelayout['discount_id_'.$discount_index])) {
				if ($row['products_discount_type'] != '0') { // if products_discount_type == 0 then there are no quantity breaks
					$sql2 = 'SELECT discount_id, discount_qty, discount_price FROM '.
						TABLE_PRODUCTS_DISCOUNT_QUANTITY.' WHERE products_id = '.
						$row['products_id'].' AND discount_id='.$discount_index;
					$result2 = ep_query($sql2);
					$row2    = mysql_fetch_array($result2);
					$row['discount_id_'.$discount_index]    = $row2['discount_id'];
					$row['discount_price_'.$discount_index] = $row2['discount_price'];
					$row['discount_qty_'.$discount_index]   = $row2['discount_qty'];
				}
				$discount_index++;
			 }

			//We check the value of tax class and title instead of the id
			//Then we add the tax to price if $price_with_tax is set to 1
			if (isset($filelayout['products_price'])) {
				$row_tax_multiplier     = $this->getTaxClassRate($row['tax_class_id']);
				$row['tax_class_title']   = zen_get_tax_class_title($row['tax_class_id']);
				$row['products_price']  = round($row['products_price'] + ($price_with_tax * $row['products_price'] * $row_tax_multiplier / 100),2);
			}

			$tempcsvrow = array();
			foreach ($filelayout as $key => $value) {
				// only the specified keys are used
				$tempcsvrow[] = $row[$key];
			}
			switch ($ep_dltype) {
				case 'froogle':
					$tempcsvrow = array_map(array($this, 'killBreaks'), $tempcsvrow);
					break;
				}
			$tempFile->write($tempcsvrow);
		}

		$this->tempFName = $tempFName;
		return true;
	}

	/**
	 * Return the filelayout for attributes
	 *
	 * @return array
	 */
	function getAttributesFileLayout()
	{
		$filelayout = array();
		$languages = zen_get_languages();

		$attribute_options_count = 1;
		foreach ($attribute_options_array as $attribute_options_values) {
			$key1 = 'attribute_options_id_' . $attribute_options_count;
			$filelayout[] = $key1;

			for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
				$l_id = $languages[$i]['id'];
				$key2 = 'attribute_options_name_' . $attribute_options_count . '_' . $l_id;
				$filelayout[] = $key2;
			}

			$attribute_values_query = "SELECT products_options_values_id
			FROM " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . "
			WHERE products_options_id = '" . (int)$attribute_options_values['products_options_id'] . "'
			ORDER BY products_options_values_id";
			$attribute_values_values = ep_query($attribute_values_query);

			$attribute_values_count = 1;
			while ($attribute_values = mysql_fetch_array($attribute_values_values)) {
				$key3 = 'attribute_values_id_' . $attribute_options_count . '_' . $attribute_values_count;
				$filelayout[] = $key3;

				$key4 = 'attribute_values_price_' . $attribute_options_count . '_' . $attribute_values_count;
				$filelayout[] = $key4;

				for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
					$l_id = $languages[$i]['id'];

					$key5 = 'attribute_values_name_' . $attribute_options_count . '_' . $attribute_values_count . '_' . $l_id;
					$filelayout[] = $key5;
				}

				$attribute_values_count++;
			}
			$attribute_options_count++;
		}
		return $filelayout;
	}

	/**
	 * Kills all line breaks and tabs
	 *
	 * Used for Froogle (Google Products)
	 *
	 * @param string $line line to kill breaks on
	 * @return mixed
	 */
	private function killBreaks($line) {
		if (is_array($line)) return array_map('kill_breaks', $line);
		return str_replace(array("\r","\n","\t")," ",$line);
	}
}
?>
