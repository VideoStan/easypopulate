<?php
/**
 * EasyPopulate testimonials import
 *
 * @package easypopulate
 * @author johnny <johnny@localmomentum.net>
 * @copyright 200?-2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 * @todo <johnny> make it a better class
 */


class EasyPopulateImportTestimonials extends EasyPopulateProcess
{
	public function dependenciesMet()
	{
		if (!file_exists(DIR_FS_ADMIN . 'testimonials_manager.php')) {
			$this->error = 'Please Install Testimonials Manager';
			return false;
		}
		return true;
	}

	public function run(SplFileInfo $fileInfo)
	{
		$config = $this->config->getValues($this->importHandler);

		$file = $this->openFile($fileInfo);

		if ($file === false) return false;
		// @todo put this somewhere else
		$file->imagePathPrefix = $config['image_path_prefix'];


		$file->onFileStart();

		foreach ($file as $items) {
			$output_message = '';
			$items = $file->handleRow($items);

			if ($items['status'] == '') $items['status'] = 0;
			if ($items['testimonials_show_email'] == '') {
				$items['testimonials_show_email'] = 0;
			}

			if (isset($items['date_added']) && !empty($items['date_added'])) {
				$items['date_added'] = date('Y-m-d H:i:s', strtotime($items['date_added']));
			} else {
				$items['date_added'] = 'NOW()';
			}

			$query = "SELECT * FROM " . TABLE_TESTIMONIALS_MANAGER . "
			WHERE testimonials_mail = '" . zen_db_input($items['testimonials_mail']) . "'";

			if (isset($items['site']) && empty($items['site'])) {
				if (isset($config['site'])) {
					$items['site'] = $config['site'];
				} else {
					$items['site'] = $_SERVER['HTTP_HOST'];
				}
			}
			if (isset($items['site']) && !empty($items['site'])) {
				$items['site'] = trim($items['site']);
				$query .= " AND site = '" . $items['site'] . "'"; 
			}

			$result = ep_query($query);

			if ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
				$items['last_update'] = 'NOW()';
				$where = 'testimonials_id = ' . $row['testimonials_id'];
				$query = ep_db_modify(TABLE_TESTIMONIALS_MANAGER, $items, 'UPDATE', $where);
				if ( ep_query($query) ) {
					$output_status = EASYPOPULATE_DISPLAY_RESULT_UPDATE_PRODUCT;
				} else {
					$output_status =  EASYPOPULATE_DISPLAY_RESULT_UPDATE_PRODUCT_FAIL;
					$output_message = EASYPOPULATE_DISPLAY_RESULT_SQL_ERROR;
					continue;
				}
			} else {
				$query = ep_db_modify(TABLE_TESTIMONIALS_MANAGER, $items, 'INSERT');
				if (ep_query($query)) {
					$output_status = EASYPOPULATE_DISPLAY_RESULT_NEW_PRODUCT;
				} else {
					$output_status = EASYPOPULATE_DISPLAY_RESULT_NEW_PRODUCT_FAIL;
					$output_message = EASYPOPULATE_DISPLAY_RESULT_SQL_ERROR;
					continue; 
				}
			}

			$output_data = array_values($items);
			// @todo write  status message and status to tempFile 

			$output_data = $this->flattenArray($items);
			if (empty($this->tempFile->filelayout)) {
				$this->tempFile->setFileLayout(array_keys($output_data), true);
			}

			$this->tempFile->write($output_data);

			//$file->onItemFinish($products_id, $products_model);
		}

		/**
		* Post-upload tasks start
		*/
		$file->onFileFinish();

		$this->itemCount = $file->itemCount;

		return true;
	}
}
?>
