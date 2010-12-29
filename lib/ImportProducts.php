<?php
/**
 * EasyPopulate products import
 *
 * @package easypopulate
 * @author langer
 * @author johnny <johnny@localmomentum.net>
 * @author too many to list, see history.txt
 * @copyright 200?-2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 * @todo <johnny> make it a better class
 * @todo <chadd> change v_products_price_as to v_products_price_uom
 * @todo <johnny> let ZM handle field errors
 */


class EasyPopulateImportProducts extends EasyPopulateProcess
{
	public $itemCount = 0;

	protected $taxClassIds = array();
	protected $productIds = array();

	public function dependenciesMet()
	{
		return true;
	}

	public function run(SplFileInfo $fileInfo)
	{
		$transforms = array();
		$output['specials'] = array();
		$output['errors'] = array();
		$output['info'] = '';
		$ep_supported_mods = array();
		$ep_supported_mods['psd'] = false; //ep_field_name_exists(TABLE_PRODUCTS_DESCRIPTION,'products_short_desc');
		$ep_supported_mods['uom'] = false; //ep_field_name_exists(TABLE_PRODUCTS_DESCRIPTION,'products_price_as'); // uom = unit of measure
		$ep_supported_mods['upc'] = false; //ep_field_name_exists(TABLE_PRODUCTS_DESCRIPTION,'products_upc'); // upc = UPC Code
		extract($this->config->getValues($this->importHandler), EXTR_OVERWRITE);

		$file = $this->openFile($fileInfo);
		if ($file === false) return false;

		$filelayout = $file->getFileLayout();

		//$this->onFileStart();

		foreach ($file as $items) {
			$output_message = '';
			$categories_name = array();
			$itemBefore = $items;
			$items['description'] = array();
			$items['metatags'] = array();
			$items['attributes'] = array();
			$items = $file->handleRow($items);

			if (!isset($items['products_model']) && !zen_not_null($items['products_model'])) {
				$output_message = EASYPOPULATE_DISPLAY_RESULT_NO_MODEL;
				continue;
			}

			$sql = 'SELECT	p.*, subc.categories_id'.
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
				$output_data = array();
				$product_is_new = false;
				/*
				* Get current products descriptions and categories for this model from database
				* $row at present consists of current product data for above fields only (in $sql)
				*/

				// let's check and delete it if requested
				if (isset($items['products_status']) && $items['products_status'] == 9) {
					$output_status = EASYPOPULATE_DISPLAY_RESULT_DELETED;
					zen_remove_product($items['products_id']);
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

				$categories = $this->getCategories($row['categories_id']);
				foreach ($categories as $k => $v) {
					$row['categories_name_' . ($k + 1)] = $categories[$k];
				}

				$row['manufacturers_name'] = $this->getManufacturerName($row['manufacturers_id']);

				/**
				 * Get tax info for this product
				 */
				$row_tax_multiplier = $this->getTaxClassRate($row['products_tax_class_id']);
				$row['tax_class_title'] = zen_get_tax_class_title($row['products_tax_class_id']);
				if ($prices_include_tax){
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
			 *
			 * New products start here!
			 */

			/**
			 * Data error checking
			 * inputs: $items; $filelayout; $product_is_new (no reliance on $row)
			 */
			if ($product_is_new == true) {
				if (!zen_not_null(trim($items['categories_name_1']))) {
					// let's skip this new product without a master category..
					$output_status = EASYPOPULATE_DISPLAY_RESULT_SKIPPED;
					$output_message = sprintf(EASYPOPULATE_DISPLAY_RESULT_CATEGORY_NOT_FOUND, ' new');
					continue;
				}
			} else { // not new product
				if (!zen_not_null(trim($items['categories_name_1'])) && isset($filelayout['categories_name_1'])) {
					// let's skip this existing product without a master category but has the column heading
					// or should we just update it to result of $row (it's current category..)??
					// @todo <johnny> If product exists and doesn't have a master category, use the current one
					$output_status = EASYPOPULATE_DISPLAY_RESULT_SKIPPED;
					$output_message  = sprintf(EASYPOPULATE_DISPLAY_RESULT_CATEGORY_NOT_FOUND, '');
					continue;
				}
			}
			/**
			 * End data checking
			 */


			/**
			 * langer - assign to our vars any new data from $items (from our file)
			 * output is: $products_model = "modelofthing", $products_description_1 = "descofthing", etc for each file heading
			 * any existing (default) data assigned above is overwritten here with the new vals from file
			 */
			extract($items);

			// Modify a price based on the submitted price modifier
			$products_price = $this->modifyPrice($products_price, $price_modifier);

			//elari... we get the tax_class_id from the tax_title - from zencart??
			//on screen will still be displayed the tax_class_title instead of the id....
			if (isset($tax_class_title)){
				$products_tax_class_id = $this->getTaxTitleClassId($tax_class_title);
			}

			$row_tax_multiplier = $this->getTaxClassRate($products_tax_class_id);

			// Recalculate price without the included tax
			if ($prices_include_tax) {
				$products_price = round( $products_price / (1 + ( $row_tax_multiplier * $prices_include_tax/100) ), 4);
			}

			$newlevel = 1;
			// @todo decouple import from max_categories altogether
			for ($categorylevel = 10; $categorylevel>0; $categorylevel--) {
				if (isset($items['categories_name_' . $categorylevel])) {
					if (!empty($items['categories_name_' . $categorylevel])) {
						$categories_name[$newlevel++] = $items['categories_name_' . $categorylevel];
					}
				}
			}

			if (!isset($manufacturers_name)) $manufacturers_name = NULL; 
			$manufacturers_id = $this->getManufacturerIdByName($manufacturers_name);
			if (empty($manufacturers_id)) {
				$data = array();
				$data['manufacturers_name'] = $manufacturers_name;
				$data['date_added'] = 'NOW()';
				$data['last_modified'] = 'NOW()';
				$query = ep_db_modify(TABLE_MANUFACTURERS, $data, 'INSERT');
				$result = ep_query($query);
				$manufacturers_id = mysql_insert_id();
			}

			$des_extra = '';
			if (!empty($site) && function_exists('get_sites')) {
				$des_extra = ', des.sites';
			}

			// if the categories names are set then try to update them
			if (isset($categories_name_1)) {
				// start from the highest possible category and work our way down from the parent
				$categories_id = 0;
				$theparent_id = 0;

				// @todo decouple import from max categories altogether
				for ($categorylevel = 10; $categorylevel>0; $categorylevel--) {
					if (isset($categories_name[$categorylevel])){
						$thiscategoryname = $categories_name[$categorylevel];
						// we found a category name in this field

						// now the subcategory
						$sql = "SELECT cat.categories_id AS catID $des_extra
								FROM ".TABLE_CATEGORIES." AS cat, ".TABLE_CATEGORIES_DESCRIPTION." AS des WHERE
								cat.categories_id = des.categories_id AND
								des.language_id = $epdlanguage_id AND
								cat.parent_id = " . $theparent_id . " AND
								des.categories_name = '" . zen_db_input($thiscategoryname) . "' LIMIT 1";
						$result = ep_query($sql);

						if ($row = mysql_fetch_array($result)) { 
							$thiscategoryid = $row['catID'];

							if (isset($site) && !empty($site)) {
								$sites = explode(',', $row['sites']);
								if (!in_array($site, $sites)) {
									$cddata['sites'] = $row['sites'] . ',' . $site;
									$where = "categories_id = $thiscategoryid";
									$query = ep_db_modify(TABLE_CATEGORIES_DESCRIPTION, $cddata, 'UPDATE', $where);
									$sresult = ep_query($query);	
								}
							}
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

							if (isset($site) && !empty($site)) {
								$data['sites'] = $site;
							}

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
			// First we check to see if this is a product in the current db.
			$result = ep_query("SELECT `products_id` FROM ".TABLE_PRODUCTS." WHERE (products_model = '" . zen_db_input($products_model) . "') LIMIT 1 ");

			$product = array();

			$product['products_date_available'] = 'NOW()';
			if (isset($products_date_available) && !empty($products_date_available)) {
				$product['products_date_available'] = date('Y-m-d H:i:s', strtotime($products_date_available));
			}

			if (isset($row['products_date_added'])) {
				$product['products_date_added'] = $row['products_date_added'];
			} else {
				$product['products_date_added'] = isset($products_date_added) && !empty($products_date_added) ? date("Y-m-d H:i:s",strtotime($products_date_added)) : 'NOW()';
			}

			if (!isset($products_quantity) || empty($products_quantity)) {
				$products_quantity = 0;
			}

			if (!isset($products_status) || $products_status == '') {
				$products_status = 1;
			}
			if ($deactivate_on_zero_qty && $products_quantity == 0) {
				$products_status = 0;
			}

			$product['products_model']	= $products_model;
			$product['products_last_modified'] = 'NOW()';
			$product['products_price'] = $products_price;

			if ($image_check_exists) {
				if (!file_exists(DIR_FS_CATALOG . 'images/' . $products_image)) {
					$item['products_image'] = PRODUCTS_IMAGE_NO_IMAGE;
				}
			}

			if (!empty($products_image) && ($products_image != PRODUCTS_IMAGE_NO_IMAGE)) {
				$products_image = $image_path_prefix . $products_image;
			}
			$product['products_image'] = $products_image;

			$product['products_weight'] = $products_weight;
			$product['products_tax_class_id'] = $products_tax_class_id;
			if (isset($products_discount_type)) {
				$product['products_discount_type'] = $products_discount_type;
			}
			if (isset($products_discount_type_from)) {
				$product['products_discount_type_from'] = $products_discount_type_from;
			}
			if (isset($product_is_call)) {
				$product['product_is_call'] = $product_is_call;
			}
			if (isset($products_sort_order)) {
				$product['products_sort_order'] = $products_sort_order;
			}
			if (isset($products_qty_box_status)) {
				$product['products_qty_box_status'] = $products_qty_box_status;
			}
			$product['products_quantity_order_min'] = 1;
			if (isset($products_quantity_order_min)) {
				$product['products_quantity_order_min'] = $products_quantity_order_min;
			}
			$item['products_quantity_order_units'] = 1;
			if (isset($products_quantity_order_units)) {
				$product['products_quantity_order_units'] = $products_quantity_order_units;
			}

			$product['products_quantity']	= $products_quantity;
			$product['master_categories_id'] = $categories_id;
			$product['manufacturers_id'] = $manufacturers_id;
			$product['products_status'] = $products_status;
			if (isset($metatags_title_status)) {
				$product['metatags_title_status'] = $metatags_title_status;
			}
			if (isset($metatags_products_name_status)) {
				$product['metatags_products_name_status']	= $metatags_products_name_status;
			}
			if (isset($metatags_model_status)) {
				$product['metatags_model_status'] = $metatags_model_status;
			}
			if (isset($metatags_price_status)) {
				$product['metatags_price_status'] = $metatags_price_status;
			}
			if (isset($metatags_title_tagline_status)) {
				$product['metatags_title_tagline_status']	= $metatags_title_tagline_status;
			}
			if ($ep_supported_mods['uom']) {
				$product['products_price_as'] = $products_price_as;
			}
			if ($ep_supported_mods['upc']) {
				$product['products_upc'] = $products_upc;
			}

			if ($row = mysql_fetch_array($result)) { //UPDATING PRODUCT
				$products_id = $row['products_id'];

				$query = ep_db_modify(TABLE_PRODUCTS, $product, 'UPDATE', "products_id = $products_id");

				if ( ep_query($query) ) {
					$output_status = EASYPOPULATE_DISPLAY_RESULT_UPDATE_PRODUCT;
				} else {
					$output_status =  EASYPOPULATE_DISPLAY_RESULT_UPDATE_PRODUCT_FAIL;
					$output_message = EASYPOPULATE_DISPLAY_RESULT_SQL_ERROR;
				}
			} else { //NEW PRODUCT
				$query = ep_db_modify(TABLE_PRODUCTS, $product, 'INSERT');
				if ( ep_query($query) ) {
					$products_id = mysql_insert_id();
					$output_status = EASYPOPULATE_DISPLAY_RESULT_NEW_PRODUCT;
				} else {
					$output_status = EASYPOPULATE_DISPLAY_RESULT_NEW_PRODUCT_FAIL;
					$output_message = EASYPOPULATE_DISPLAY_RESULT_SQL_ERROR;
					continue; // @todo CHECKME langer - any new categories however have been created by now..Adding into product table needs to be 1st action?
				}
			}


			/**
			 * Product Meta Start
			 */
			foreach ($metatags as $key => $metaData) {
				$data = array();
				$data['products_id'] = $products_id;
				$data['language_id'] = $key;

				$data['metatags_title'] = isset($metaData['title']) ? $metaData['title'] : '';
				$data['metatags_keywords'] = isset($metaData['keywords']) ? $metaData['keywords'] : '';
				$data['metatags_description'] = isset($metaData['description']) ? $metaData['description'] : '';

				if (isset($metatags_title) && !empty($metatags_title) && empty($data['metatags_title'])) {
					$data['metatags_title'] = $this->transformPlaceHolders($itemBefore, $metatags_title);
				}

				if (isset($metatags_keywords) && !empty($metatags_keywords) && empty($data['metatags_keywords'])) {
					$data['metatags_keywords'] = $this->transformPlaceHolders($itemBefore, $metatags_keywords);
				}
				if (isset($metatags_description) && !empty($metatags_description) && empty($data['metatags_description'])) {
					$data['metatags_description'] = $this->transformPlaceHolders($itemBefore, $metatags_description);
				}

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
				// Easier to start over than try to update individual discounts
				if ($row = mysql_fetch_array($result)) {
					$sql = "DELETE FROM ".TABLE_PRODUCTS_DISCOUNT_QUANTITY." WHERE (`products_id` = '$products_id') ";
					$result = ep_query($sql);
				}
				for ($discount = 1; ; $discount++) {
					if (!isset($items['discount_qty_' .$discount])) break;
					if (!isset($items['discount_price_' .$discount])) break;
					if (empty($items['discount_qty_' .$discount])) continue;
					if (empty($items['discount_price_' .$discount])) continue;

					$data = array();
					$data['discount_id'] = $discount;
					$data['products_id'] = $products_id;
					$data['discount_qty'] = $items['discount_qty_' .$discount];
					$data['discount_price'] = $this->modifyPrice($items['discount_price_' . $discount], $price_modifier);
					$sql = ep_db_modify(TABLE_PRODUCTS_DISCOUNT_QUANTITY, $data, 'INSERT');
					$result = ep_query($sql);
				}
			}

			//*************************
			// Products Descriptions Start
			//*************************
			foreach ($descriptions as $key => $value) {
					$sql = "SELECT * FROM ".TABLE_PRODUCTS_DESCRIPTION." WHERE products_id = $products_id AND	language_id = " . $key . " LIMIT 1 ";
					$result = ep_query($sql);
					$data = array();
					$data['products_id']	= $products_id;
					$data['language_id']	= $key;
					if (isset($value['name'])) $data['products_name'] = $this->smartTags($value['name'], false);
					if (isset($value['description'])) $data['products_description']	= $value['description'];
					if (isset($value['url'])) $data['products_url'] = $this->smartTags($value['url'], false);

					if ($ep_supported_mods['psd']) {
						$data['products_short_desc'] = $this->smartTags($value['short_desc'], $replace_newlines);
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
			/**
			 * Products Descriptions End
			 */

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
			if (!empty($attributes)) {
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
								if (isset($attribute['type'])) {
									$data['products_options_type'] = $attribute['type'];
								}
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
								if (isset($values['display_only'])) {
									$data['attributes_display_only'] = $values['display_only'];
								}
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
						$specials_status = EASYPOPULATE_DISPLAY_RESULT_DELETE_NOT_FOUND;
						$specials_message = EASYPOPULATE_SPECIALS_DELETE_FAIL;
						continue;
					}
					$data['specials_date_added'] = 'NOW()';
					$query = ep_db_modify(TABLE_SPECIALS, $data, 'INSERT');

					$result = ep_query($query);
					$specials_status = EASYPOPULATE_DISPLAY_RESULT_NEW_PRODUCT;
				} else {
					// existing product
					if ($specials_price == '0') {
						$db->Execute("delete from " . TABLE_SPECIALS . "
									 where products_id = '" . (int)$products_id . "'");
						$specials_status = EASYPOPULATE_DISPLAY_RESULT_DELETED;
						continue;
					}
					$query = ep_db_modify(TABLE_SPECIALS, $data, 'UPDATE', "products_id = $products_id");
					$result = ep_query($query);
					$specials_status = EASYPOPULATE_DISPLAY_RESULT_UPDATE_PRODUCT;
				}

				$specials_data = array($products_model, $descriptions[$epdlanguage_id]['name'], $products_price , $specials_price);
				$output['specials'][] = array('status' => $specials_status, 'message' => $specials_message, 'data' => $specials_data);
			}
			// end specials for this product
			$output_data = array_values($items);
			// @todo write  status message and status to tempFile 
			//$output['items'][] = array('status' => $output_status, 'message' => $output_message, 'data' => $output_data);

			$this->productIds[] = $products_id;
			$this->itemCount++;
			$output_data = $this->flattenArray($items);
			if (empty($this->tempFile->filelayout)) {
				$this->tempFile->setFileLayout(array_keys($output_data), true);
			}

			$this->tempFile->write($output_data);

			//$this->onItemFinish($products_id, $products_model);
		}
		/**
		* Post-upload tasks start
		*/
		//$this->onFileFinish();

		$this->updatePriceSortOrder();

		if (!empty($output['specials'])) {
			zen_expire_specials();
		}

		if (isset($has_attributes)) {
			$this->updateAttributesSortOrder();
		}

		// @todo move this into onFinish
		if ($feed_fetch) {
			$this->removeMissingProducts();
		}

		return true;
	}

	/**
	 * Modifiy a price (pre-tax) by a flat price or a percentage
	 *
	 * @param int $price a price
	 * @param mixed $modifier positive or negative value
	 *
	 * @return int
	 */
	private function modifyPrice($price , $modifier = 0)
	{
		if (strpos($modifier, '%') !== false) {
			$modifier = str_replace('%', '', $modifier);
			$modifier = $price * ((int)$modifier / 100);
		}
		return $price += $modifier;
	}

	/**
	 * Get table field defaults
	 *
	 * @param string $table
	 * @return array array containing default values for each field in the table (field => value)
	 */
	protected function getTableDefaults($table)
	{
		if (isset($this->tableDefaults[$table])) {
			return $this->taxClassIds[$table];
		}
		$query = 'SHOW COLUMNS FROM ' . $table;
		$result = mysql_query($query);
		if (!mysql_num_rows($result)) return array();
		$defaults = array();
		while ($row = mysql_fetch_assoc($result)) {
			$defaults[$row['Field']] = $row['Default'];
		}
		$this->tableDefaults[$table] = $defaults;
		return $defaults;
	}

	/**
	 * Get manufacturer id by name
	 *
	 * @param name
	 * @return int
	 * @todo cache it?
	 */
	private function getManufacturerIdByName($name = '')
	{
		if (empty($name)) return NULL;
		$id = NULL;
		$query = "SELECT manufacturers_id FROM ".TABLE_MANUFACTURERS."
		WHERE manufacturers_name = '" . zen_db_input($name) . "' LIMIT 1";
		$result = ep_query($query);
		if ($row =  mysql_fetch_array($result)) {
			$id = $row['manufacturers_id'];
		}
		return $id;
	}

	/**
	 * Get tax class id by tax class title
	 *
	 * @param string $taxClassTitle
	 * @return int tax class id
	 *
	 * @todo should we error out if the tax class doesn't exist? or continue to fail silently?
	 */
	private function getTaxTitleClassId($taxClassTitle)
	{
		if (isset($this->taxClassIds[$taxClassTitle])) {
			return $this->taxClassIds[$taxClassTitle];
		}
		$query = "SELECT tax_class_id FROM " . TABLE_TAX_CLASS . "
		WHERE tax_class_title = '" . zen_db_input($taxClassTitle) . "'" ;
		$row = mysql_fetch_array(mysql_query($query));
		if (!is_array($row) || empty($row)) {
			$row = array('tax_class_id' => 0);
		}
		$this->taxClassIds[$taxClassTitle] = $row['tax_class_id'];
		return $row['tax_class_id'];
	}

	/**
	 * Reset products price sorting
	 */
	private function updatePriceSortOrder()
	{
		global $db;
		$products = $db->Execute("SELECT products_id FROM " . TABLE_PRODUCTS);

		while (!$products->EOF) {
			zen_update_products_price_sorter($products->fields['products_id']);
			$products->MoveNext();
		}
	}

	private function updateAttributesSortOrder()
	{
		global $db;
		$query = "SELECT p.products_id, pa.products_attributes_id
		FROM " . TABLE_PRODUCTS . " p, " .
		TABLE_PRODUCTS_ATTRIBUTES . " pa " . "
		WHERE p.products_id= pa.products_id";
		$attributes = $db->Execute($query);
		while (!$attributes->EOF) {
			zen_update_attributes_products_option_values_sort_order($attributes->fields['products_id']);
			$attributes->MoveNext();
		}
	}

	/**
	 * Reset products master categories ID
	 *
	 * @todo use it or remove it
	 */
	private function updateCategoryIds()
	{
		global $db;
		$products = $db->Execute("SELECT products_id FROM " . TABLE_PRODUCTS);
		while (!$products->EOF) {
			$sql = "SELECT products_id, categories_id
			FROM " . TABLE_PRODUCTS_TO_CATEGORIES . "
			WHERE products_id = '" . $products->fields['products_id'] . "'";
			$category = $db->Execute($sql);
			$sql = "UPDATE " . TABLE_PRODUCTS . " SET
			master_categories_id = '" . $category->fields['categories_id'] . "'
			WHERE products_id = '" . $products->fields['products_id'] . "'";
			$db->Execute($sql);

			$products->MoveNext();
		}
	}

	/**
	 * Remove products that are no longer in the input file
	 * @todo ZM_MIGRATE replace  zen_remove_product with a ZM native method, one that should tell us if it failed.
	 */
	protected function removeMissingProducts()
	{
		$missingProducts = $this->config->getMissingItems($this->importHandler);
		foreach ($missingProducts as $productId) {
				zen_remove_product($productId);
		}
		$this->config->updateMissingItems($this->importHandler, array());
	}

	/**
	 * Transform {} placeholders to the field value
	 *
	 * If v_products_name_1 is foo, then it will transform {products_name_1} to foo
	 *
	 * @param mixed array of values to search
	 * @param string string in which to replace search values
	 * @return string
	 */
	protected function transformPlaceHolders(array $search, $replace)
	{
		return preg_replace("/\{([^\{]{1,100}?)\}/e", '$search[\'$1\']', $replace);
	}
}
?>
