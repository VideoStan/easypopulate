<?php
/**
 * EasyPopulate testimonials import
 *
 * @package easypopulate
 * @author John William Robeson, Jr <johnny>
 * @copyright 2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 */

/**
 * Easy Populate record format
 *
 * All headings in $filelayout['columnheading'] = columnnumber
 * All values are in $items[$filelayout] = 'value'
 *
  Available Fields:
  title
  url
  name
  image
  text
  email
  company
  city
  country
  show_email
  status
  date_added
  site
 */
class EPUploadTestimonials extends EasyPopulateCsvFileObject
{
	public $name = 'Testimonials';
	public $itemCount = 0;
	public $imagePathPrefix = '';

	function __construct($file)
	{
		$this->transforms = array();
		$this->autoDetectLineEndings(ep_get_config('detect_line_endings'));
		parent::__construct($file);
	}

	public static function defaultConfig()
	{
		$config = array();
		$config['column_delimiter'] = ',';
		$config['column_enclosure'] = '"';
		$config['price_modifier'] = 0;
		$config['image_path_prefix'] = '';
		$config['tax_class_title'] = '';
		$config['metatags_keywords'] = '';
		$config['metatags_description'] = '';
		$config['metatags_title'] = '';
		$config['feed_fetch'] = false;
		$config['images_fetch'] = false;
		$config['feed_send_email'] = false;
		if (function_exists('get_sites')) {
			$config['site'] = '';
		}
		return $config;
	}

	/**
	 * Map csv column header names to column names
	 *
	 * @param array
	 * @return array
	 */
	public function mapFileLayout($filelayout)
	{
		$filelayout[0] = 'testimonials_title';
      $filelayout[1] = 'testimonials_url';
      $filelayout[2] = 'testimonials_name';
      $filelayout[3] = 'testimonials_image';
      $filelayout[4] = 'testimonials_html_text';
      $filelayout[5] = 'testimonials_mail';
      $filelayout[6] = 'testimonials_company';
      $filelayout[7] = 'testimonials_city';
      $filelayout[8] = 'testimonials_country';
      $filelayout[9] = 'testimonials_show_email';
      $filelayout[10] = 'status';
      $filelayout[11] = 'date_added';
      if (function_exists('get_sites')) {
      	$filelayout[12] = 'site';
      }
		return array_flip($filelayout);
	}


	/**
	 * Map row values to columns
	 *
	 */
	public function handleRow(array $item)
	{

		return $item;
	}

	public function onFileStart()
	{
	}

	/**
	 * Do something when the item is finished
	 *
	 * @todo think about this function signature
	 */
	public function onItemFinish($itemId)
	{
		$this->itemCount++;
	}

	/**
	 * do something when the file is finished processing
	 */
	public function onFileFinish()
	{
	}

}
?>