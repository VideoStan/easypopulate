<?php
/**
 * EasyPopulate main administrative interface
 *
 * @package easypopulate
 * @author langer
 * @author too many to list, see history.txt
 * @copyright 200?-2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License (v2 only)
 */
//require_once 'includes/application_top.php';
/**
 * Capture header and footer since they must be included in the global
 * scope so all zencart variables are available to them
 *
 * Rewrite the header/footer urls so they point to the right place
 */
$replace = array();
$replace['/admin/easypopulate.php/'] = '';
$replace['="images/'] = '="../images/';
$replace['="includes/languages'] = '="../includes/languages';
ob_start();
require DIR_WS_INCLUDES . 'header.php';
$header = ob_get_clean();
ob_start();
require DIR_WS_INCLUDES . 'footer.php';
$footer = ob_get_clean();
$header = str_replace(array_keys($replace), array_values($replace), $header);
$footer = str_replace(array_keys($replace), array_values($replace), $footer);

$original_error_level = error_reporting();
error_reporting(E_ALL ^ E_DEPRECATED); // zencart uses functions deprecated in php 5.3

require DIR_WS_CLASSES . 'EasyPopulate/lib/EasyPopulate.php';
include DIR_WS_CLASSES . 'EasyPopulate/lib/easypopulate_functions.php';
require DIR_WS_CLASSES . 'EasyPopulate/lib/fitzgerald/lib/fitzgerald.php';

if (!isset($_SESSION['easypopulate'])) {
	$_SESSION['easypopulate'] = array();
}
if (!isset($_SESSION['easypopulate']['errors'])) {
	$_SESSION['easypopulate']['errors'] = array();
}

class EasyPopulate extends Fitzgerald
{
	public $originalErrorLevel;

	public function __construct($options = array())
	{
		parent::__construct($options);
		$language = 'english';
		if (!is_null($this->session->language)) {
			$language = $this->session->language;
		}
		$langDir = dirname( __FILE__) . '/lang/' . $language . '/';
		foreach (glob($langDir . '*php') as $langFile) {
			if (is_readable($langFile)) include $langFile;
		}
	}

	protected function views()
	{
		$views = parent::views();
		array_unshift($views, dirname(__FILE__) . '/');
		return $views;
	}

	public function get_index()
	{
		$tpl = array();

		if (defined('EASYPOPULATE_CONFIG_VERSION')) {
			$config = ep_get_config();
			$chmod_check = is_dir($config['temp_path']) && is_writable($config['temp_path']);
			if (!$chmod_check) {
				ep_set_error('local_file', sprintf(EASYPOPULATE_MSGSTACK_TEMP_FOLDER_MISSING, $config['temp_path'], DIR_FS_CATALOG));
			}
			ep_update_handlers();
			$config = ep_get_config();

		}
		return $this->render('main');
	}

	public function post_installer()
	{
		if (!is_null($this->request->action)) {
			$f = $this->request->action . '_easypopulate';
			$f();
			// @todo return true or false from the installer so we can print an error message
			//$this->session->installSuccess = EASYPOPULATE_MSGSTACK_INSTALL_SUCCESS;
		}
		$this->redirect('/');
	}

	public function get_preset($config)
	{
		echo json_encode(EPFileUploadFactory::getConfig($config));
		error_reporting($this->originalErrorLevel);
		exit();
	}

	public function post_preset()
	{
		if (!is_null($this->request->preset) && !is_null($this->request->config)) {
			EPFileUploadFactory::setConfig($this->request->preset, $this->request->config);
		}
		if (isset($ep_stack_sql_error) &&  $ep_stack_sql_error) $messageStack->add(EASYPOPULATE_MSGSTACK_ERROR_SQL, 'caution');
		error_reporting($this->originalErrorLevel);
		exit();
	}

	public function get_export_page()
	{
		return $this->render('export', ep_get_config());
	}

	public function get_export($format = 'full', $download = 'stream')
	{
		$export = new EasyPopulateExport();
		$export->setFormat($format);
		$export->run();

		if ($download == 'stream') {
			//if (isset($ep_stack_sql_error) &&  $ep_stack_sql_error) $messageStack->add(EASYPOPULATE_MSGSTACK_ERROR_SQL, 'caution');
			return $this->sendFile($export->fileName, 'text/csv', $export->tempFName);
			exit();
		} else {
			if (!rename($export->tempFName, ep_get_config('temp_path') . $export->fileName)) {
				// @todo error
			}
			$messageStack->add(sprintf(EASYPOPULATE_MSGSTACK_FILE_EXPORT_SUCCESS, $export->fileName, ep_get_config('temp_path')), 'success');
			if (isset($ep_stack_sql_error) &&  $ep_stack_sql_error) $messageStack->add(EASYPOPULATE_MSGSTACK_ERROR_SQL, 'caution');
			$this->redirect('/export');
		}
	}

	/**
    * This is a rudimentary date integrity check for references to any non-existant product_id entries
	 * this check ought to be last, so it checks the tasks just performed as a quality check of EP...
	 * @todo langer  data present in table products, but not in descriptions.. user will need product info, and decide to add description, or delete product
	 */
	public function get_dross()
	{
		$dross = EasyPopulateImport::getDross();
		if (!empty($dross)) {
			//$messageStack->add(sprintf(EASYPOPULATE_MSGSTACK_DROSS_DETECTED, count($dross), zen_href_link('easypopulate.php', 'dross=delete')), 'caution');
		}
	}

	public function post_dross()
	{
		EasyPopulateImport::purgeDross($dross);
		// now check it is really gone...
		$dross = EasyPopulateImport::getDross();
		if (!empty($dross)) {
			$string = "Product debris corresponding to the following product_id(s) cannot be deleted by EasyPopulate:\n";
			foreach ($dross as $products_id) {
				$string .= $products_id . "\n";
			}
			$string .= "It is recommended that you delete this corrupted data using phpMyAdmin.\n\n";
			write_debug_log($string, 'dross');
			//$messageStack->add(EASYPOPULATE_MSGSTACK_DROSS_DELETE_FAIL, 'caution');
		} else {
			//$messageStack->add(EASYPOPULATE_MSGSTACK_DROSS_DELETE_SUCCESS, 'success');
		}
		if (isset($ep_stack_sql_error) &&  $ep_stack_sql_error) $messageStack->add(EASYPOPULATE_MSGSTACK_ERROR_SQL, 'caution');
	}

	private function getImportTplVars()
	{
		$tpl = array();
		$tpl['max_file_size'] = min(ep_get_bytes(ini_get('upload_max_filesize')), ep_get_bytes(ini_get('post_max_size')));
		$tpl['price_modifier'] = 0;
		$tpl['image_path_prefix'] = '';
		$tpl['column_delimiter'] = ',';
		$tpl['column_enclosure'] = '"';
		$tpl['local_file'] = '';
		$tpl['tax_class_title'] = '';
		$tpl['feed_url'] = '';
		$tpl['metatags_keywords'] = '';
		$tpl['metatags_description'] = '';
		$tpl['metatags_title'] = '';
		$tpl['site'] = '';

		$config = ep_get_config();
		$tpl = array_merge($tpl, $config);
		$tpl = array_merge($tpl, EPFileUploadFactory::getConfig($tpl['import_handler']));
		return $tpl;
	}

	public function get_import()
	{
		$tpl = $this->getImportTplVars();
		return $this->render('import', $tpl);
	}

	public function post_import()
	{
		$config = array();
		$config['import_handler'] = ep_get_config('import_handler');
		if (!is_null($this->request->import_handler)) {
			$config['import_handler'] = $this->request->import_handler;
		}

		$saved_config = EPFileUploadFactory::getConfig($config['import_handler']);
		$config = array_merge($saved_config, $config);

		if (!is_null($this->request->local_file)) {
			$config['local_file'] = $this->request->local_file;
		}

		$config['local_file'] = ep_get_config('temp_path') . $config['local_file'];
		if (!is_null($this->request->remote_file) && !empty($config['local_file']) && isset($config['feed_url'])) {
			if(!@copy($config['feed_url'], $config['local_file'])) {
				$error = error_get_last();
				ep_set_error('local_file', sprintf('Unable to save %s to %s because: %s', $config['feed_url'], $config['local_file'], $error['message']));
				$this->redirect('/import');
			}
		}

		if (!is_null($this->request->site)) {
			$config['site'] = $this->request->site;
		}

		if (!is_null($this->request->column_delimiter)) {
			$config['column_delimiter'] = $this->request->column_delimiter;
		}

		if (!is_null($this->request->column_enclosure)) {
			$config['column_enclosure'] = $this->request->column_enclosure;
		}

		if (!is_null($this->request->price_modifier)) {
			$config['price_modifier'] = $this->request->price_modifier;
		}

		if (!is_null($this->request->image_path_prefix)) {
			$config['image_path_prefix'] = $this->request->image_path_prefix;
		}

		if (!is_null($this->request->tax_class_title)) {
			$config['tax_class_title'] = $this->request->tax_class_title;
		}

		if (!is_null($this->request->metatags_keywords)) {
			$config['metatags_keywords'] = $this->request->metatags_keywords;
		}

		if (!is_null($this->request->metatags_description)) {
			$config['metatags_description'] = $this->request->metatags_description;
		}

		if (!is_null($this->request->metatags_title)) {
			$config['metatags_title'] = $this->request->metatags_title;
		}

		$fileInfo = new SplFileInfo($config['local_file']);

		if (!$fileInfo->isFile()) {
			ep_set_error('local_file', sprintf(EASYPOPULATE_DISPLAY_FILE_NOT_EXIST, $fileInfo->getFileName()));
			$this->redirect('/import');
		}

		if (!$fileInfo->isReadable()) {
			ep_set_error('local_file', sprintf(EASYPOPULATE_DISPLAY_FILE_OPEN_FAILED, $fileInfo->getFileName()));
			$this->redirect('/import');
		}

		$import = new EasyPopulateImport($config);
		$tpl = $this->getImportTplVars();
		$tpl['local_file'] = $this->request->local_file;
		$tpl['output'] = $import->run($fileInfo);
		$tpl['import'] = $import;

		if (isset($ep_stack_sql_error) &&  $ep_stack_sql_error) $this->log(EASYPOPULATE_MSGSTACK_ERROR_SQL);
		$tpl = array_merge($tpl, $config);
		return $this->render('import', $tpl);
	}

	public function post_upload()
	{
		if (!isset($_FILES['uploaded_file']) || empty($_FILES['uploaded_file']['type'])) {
			throw new Exception('Failed to read uploaded file');
		}
		$file = $_FILES['uploaded_file'];
		$error = constant('EASYPOPULATE_UPLOAD_ERROR_CODE_' . $file['error']);
		if ($file['error'] != UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
			ep_set_error('uploaded_file', $error);
			 $out = $error;
		} else {
			$fileName = ep_get_config('temp_path') . $file['name'];
			move_uploaded_file($file['tmp_name'], $fileName);
			$fileInfo = new SplFileInfo($fileName);
			$size = round(($fileInfo->getSize() / 1024)) . ' KB';
			$out = sprintf(EASYPOPULATE_DISPLAY_FILE_SPEC, $error, $fileInfo->getFileName(), $size);
			ep_set_error('uploaded_file', $error); // @todo not an error at all
		}
		if (is_null($this->request->ajax)) $this->redirect('/import');
		return $out;
	}

	public function handleError($number, $message, $file = '', $line = 0)
	{
		// a proper resource isn't always available to RecordCount() in queryFactoryResult
		// @todo find a way just ignore that one
		return parent::handleError($number, $message, $file, $line);
	}
}

	$app = new EasyPopulate(array(
	'errorLevel' => error_reporting(),
	'layout' => 'layout',
	'header' =>  $header,
	'footer' => $footer,
	'sessions' => false, // We use zencart's sessions
	'mountPoint' => '/admin/easypopulate.php'));

	$app->originalErrorLevel = $original_error_level;

	$app->get('/', 'get_index');
	$app->post('/installer', 'post_installer');
	$app->get('/preset/:config', 'get_preset');
	$app->post('/preset', 'post_preset');
	$app->get('/export', 'get_export_page');
	$app->get('/export/:format/:download', 'get_export');

	$app->get('/dross', 'get_dross');
	$app->post('/dross', 'post_dross');
	$app->get('/import', 'get_import');
	$app->post('/import', 'post_import');
	$app->post('/upload', 'post_upload');

	$app->run();
?>