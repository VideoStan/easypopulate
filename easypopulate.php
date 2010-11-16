<?php
/**
 * EasyPopulate Admin Controller
 *
 * @package easypopulate
 * @copyright 2009-2010
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License 2+
 * @todo separate import/export?
 * @todo validate all parameters
 * @todo show sql errors
 */
if (false) {

/**
 * EasyPopulate Admin Controller
 */
class ZMEasyPopulateController extends ZMController
{
	protected $isXhr = false;
	protected $config;

	public function __construct()
	{
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
			$this->isXhr = true;
			$options['layout'] = null;
		}
		parent::__construct();
		$this->plugin_ = ZMPlugins::instance()->getPluginForId('easyPopulate');
		include_once $this->plugin_->getPluginDirectory() . '/lang/english/easypopulate.php'; // @todo remove me once these constants are no longer used
		$configObject = new EasyPopulateConfig();
		$configObject->refreshConfig();

		$this->config = $configObject;
		$this->request = ZMRequest::instance();

		// @todo probably shouldn't be in the constructor
		$tempPath = $this->plugin_->get('temp_path') ;
		$writable = is_dir($tempPath) && is_writable($tempPath);
		if (!$writable) {
			$message = <<<STRING
<p><strong>Import folder not found!</strong></p>
<br />
Your configuration indicates that your import directory is: <strong>%s</strong>
<br>
Please make sure this directory exists and is writeable.
STRING;
		    ZMMessages::instance()->error(sprintf($message, $tempPath));
		}
	}

	// @todo ZM_MIGRATE temporary router until zenmagick is a bit more flexible
	public function route()
	{
		$request = $this->request;

		switch($request->getRequestId()) {
			case 'import':
				switch ($request->getMethod()) {
					case 'GET': return $this->get_import();
					case 'POST': return $this->post_import();
				}
				break;
			case 'preset':
				switch($request->getMethod()) {
					case 'GET': return $this->get_preset();
					case 'POST': return $this->post_preset();
				}
				break;
			case 'export':
				$testParam = $request->getParameter('format');
				if (empty($testParam)) {
					return $this->findView('export', array('temp_dir' => $this->plugin_->get('temp_dir')));
				} else {
					return $this->get_export();
				}
				break;
		}
		ZMMessages::instance()->error(_zm('Invalid request method'));
		return $this->findView('error');
	}

	// @todo ZM_MIGRATE
	public function setResponseCode($code)
	{
		header('placeholder', true, $code);
	}

	// @todo ZM_MIGRATE
	public function error($text, $statusCode = 500)
	{
		$this->setResponseCode($statusCode);
		echo $text;
		exit(1);
	}

	// @todo ZM_MIGRATE
	public function sendFile($file, $contentType, $fileName = null)
	{
		$this->setContentType($contentType);
		if (is_null($fileName)) {
			$fileName = basename($file);
		}
		header("Content-Disposition: attachment; filename=" . $fileName);
		print readfile($file);
	}

	// @todo ZM_MIGRATE
	public function isXhr()
	{
		$headers = ZMNetUtils::getAllHeaders(); // @todo ZM MIGRATE shouldn't have to set this here, it should be in the available in the controller
		return (array_key_exists('X-Requested-With', $headers) && 'XMLHttpRequest' == $headers['X-Requested-With']);
	}

	/**
	 * Get a single provider preset
	 *
	 * @param string $name
	 * @return string template output
	 * @todo use jsonrpc
	 */
	public function get_preset()
	{
		$this->setContentType('application/json');
		$configs = $this->config->getValues($this->request->getParameter('name'));
		echo json_encode($configs);
		exit();
	}

	public function post_preset()
	{
		$name = $this->request->getParameter('name');
		$config = $this->request->getParameter('config');
		if (!empty($name) && !empty($config)) {
			$postedConfig = $config;
			$dbConfig = $this->config->getValues($name);

			foreach ($dbConfig as $key => $value) {
				if (is_bool($value)) { // checkboxes
					if (!isset($postedConfig[$key])) $postedConfig[$key] = false;
					if ($postedConfig[$key] == 'on') $postedConfig[$key] = true;
				}
			}

			$this->config->setConfig($name, $postedConfig);
		}
		exit();
	}

	public function get_export_page()
	{
		return $this->render('export', ep_get_config());
	}

	public function get_export($format = 'full', $download = 'stream')
	{
		global $messageStack;
		$this->config->importOrExport = 'export';
		$config = $this->config->getValues('Standard'); // @todo dont' hardcode this
		$export = new EasyPopulateExport($config);
		
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

	public function get_import($handler = null)
	{
		$import_handler = $handler;
		$tpl = array();

		$config = ep_get_config();
		$tpl = array_merge($tpl, $config);

		if (empty($import_handler)) $import_handler = $config['import_handler']; 
		$handler_config = $this->config->getConfig($import_handler);
		$tpl = array_merge($tpl,$handler_config['import']);
		$tpl['item_type'] = $handler_config['item_type'];
		$tpl['handler'] = $handler_config['import'];

		if ($this->isXhr) return $this->render('import-fields', $tpl);

		$tpl['item_types'] = $this->config->getItemTypes();
		$tpl['handlers'] = $this->config->getHandlers($tpl['item_type']);
		$tpl['handlers_all'] = $this->config->getHandlers(null, true);
		$tpl['max_file_size'] = min(ep_get_bytes(ini_get('upload_max_filesize')), ep_get_bytes(ini_get('post_max_size')));
		return $this->render('import', $tpl);
	}

	public function post_import()
	{
		$import_handler = $this->request->import_handler;
		if (is_null($import_handler) || !is_string($import_handler)) {
			$this->error('Please select an import handler');
		}

		// @todo put config entries in $this->request->config again?
		foreach ($this->request as $k => $v) {
			if (is_null($v)) continue;
			$config[$k] = $v;
		}

		$handlerConfig = $this->config->getConfig($config['import_handler']);
		$this->config->setValues($config['import_handler'],$config);
		$config = $this->config->getValues($config['import_handler']);

		$config['local_file'] = ep_get_config('temp_path') . $config['local_file'];
		if (!is_null($this->request->feed_fetch) && !empty($config['local_file']) && isset($config['feed_url'])) {
			if(!@copy($config['feed_url'], $config['local_file'])) {
				$error = error_get_last();
				$this->error(sprintf('Unable to save %s to %s because: %s', $config['feed_url'], $config['local_file'], $error['message']));
			}
		}

		$fileInfo = new SplFileInfo($config['local_file']);

		if (!$fileInfo->isFile()) {
			$this->error(sprintf(EASYPOPULATE_DISPLAY_FILE_NOT_EXIST, $fileInfo->getFileName()));
		}

		if (!$fileInfo->isReadable()) {
			$this->error(sprintf(EASYPOPULATE_DISPLAY_FILE_OPEN_FAILED, $fileInfo->getFileName()));
		}

		// @todo sanitize and autoload me
		$import = EPFileUploadFactory::getProcessFile($import_handler, $this->config, $handlerConfig['item_type']);

		if (!$import->setImportHandler($import_handler)) {
			$message = "Could not use Import Handler '" . $import_handler . "' because: " . $import->error;
			$this->error($message);
		}

		$import->openTempFile();
		$result = $import->run($fileInfo);

		$resultFileName = $import->tempFile->getBasename();
		if ((bool)$this->request->feed_send_email) {
			$message = "Feed $import_handler has been updated. Please see 
			" . HTTP_CATALOG_SERVER . '/' . ep_get_config('tempdir') . $resultFileName . " for details";
			$original_error_level = error_reporting();
			error_reporting(0);
			zen_mail(EMAIL_FROM, STORE_OWNER_EMAIL_ADDRESS, 'EasyPopulate Update', $message, EMAIL_FROM, STORE_OWNER_EMAIL_ADDRESS, $message);
			error_reporting($original_error_level);

		}

		print $import->tempFile->getFileName();
		exit();
	}

	public function upload($request)
	{
		if (!isset($_FILES['uploaded_file']) || empty($_FILES['uploaded_file']['type'])) {
			$this->error(_zm('Failed to read uploaded file'));
		}
		$file = $_FILES['uploaded_file'];
		$error = constant('EASYPOPULATE_UPLOAD_ERROR_CODE_' . $file['error']);
		if ($file['error'] != UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
			$this->error(_zm($error));
		} else {
			$fileName = ep_get_config('temp_path') . $file['name'];
			move_uploaded_file($file['tmp_name'], $fileName);
			$fileInfo = new SplFileInfo($fileName);
			$size = round(($fileInfo->getSize() / 1024)) . ' KB';
			$out = sprintf(EASYPOPULATE_DISPLAY_FILE_SPEC, $error, $fileInfo->getFileName(), $size);
			print $out; 
			exit(0);
		}
	}
}
?>
