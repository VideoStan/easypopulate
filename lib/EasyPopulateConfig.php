<?php
/**
 * EasyPopulate handler configuration options class
 *
 * @package easypopulate
 * @author John William Robeson, Jr, <johnny@localmomentum.net>
 * @copyright 2010
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2 (or any later version)
 */

/**
 * Handler config options class
 * 
 * @todo document the yaml config format
 */
class EasyPopulateConfig
{
	public $importOrExport = 'import';

	private $fileConfig = array();
	private $configs;

	/**
	 * Constructor
	 *
	 * @param string $importOrExport is this for an import configuration section or an export one
	 */
	function __construct($importOrExport = 'import')
	{
	    $this->importOrExport = $importOrExport;
		$yaml = file_get_contents(DIR_FS_ADMIN . DIR_WS_CLASSES . 'EasyPopulate/config/config.yml');
		$this->fileConfig = ZMRuntime::yamlload2($yaml);
	}

	public function getItemTypes()
	{
		$item_types = array();
		foreach ($this->configs as $handler) {
			$item_types[$handler['item_type']] = $handler['item_type'];
		}
		return $item_types;
	}

	public function getValues($name)
	{
		$values = array();
		foreach($this->configs[$name][$this->importOrExport] as $key => $value) {
			if (!isset($value['value'])) $value['value'] = null;
			$values[$key] = $value['value'];
		}
		return $values;
	}

	public function setValues($name, array $values = array())
	{
		foreach ($values as $key => $value) {
			$this->configs[$name][$this->importOrExport][$key]['value'] = $value;
		}
	}

	public function getHandlers($itemType = null, $itemTypeIndexed = false)
	{
		$handlers = array();
		foreach ($this->configs as $handler => $config) {
			// @todo 'other' isn't right
			if (!isset($config['item_type'])) $config['item_type'] = 'other';
			if (is_null($itemType) || ($config['item_type'] == $itemType)) {
				if ($itemTypeIndexed) {
					$handlers[$config['item_type']][] = $handler;
				} else {
					$handlers[] = $handler;
				}
			}
		}
		return $handlers;
	}

	/**
	 * Get handler config
	 *
	 * @param string $name
	 * @return array array of config values
	 */

	public function getConfig($name = NULL)
	{
		if (!isset($this->configs[$name])) return false;
		if (!empty($name)) return $this->configs[$name];
		return $this->configs;
	}

	public function getConfigs()
	{
		$query = "SELECT name, handler, config FROM  " . TABLE_EASYPOPULATE_FEEDS;

		if (!empty($name)) $query .= ' WHERE name = :name';
		$result = ZMRuntime::getDatabase()->query($query, array('name' => $name), TABLE_EASYPOPULATE_FEEDS );
		$configs = array();
		foreach ($result as $fields) {
		//while (!$result->EOF) {
			$name = $fields['name'];
			$defaultConfig = $this->fileConfig['handlers'][$name];
			$defaultConfig['handler'] = $fields['handler'];
			//$defaltConfig['item_type'] = $result->fields['item_type'];
			$config = json_decode($fields['config'], true);
			$configs[$name] = array_merge($defaultConfig, (array)$config);
			//$result->MoveNext();
		}
		$this->configs = $configs;
		return $configs;
	}

	public function refreshConfig()
	{
		$fileConfig = $this->fileConfig;
		$configNames = array_keys($this->getConfigs());

		foreach ($fileConfig['handlers'] as $handler => $handlerConfig) {
			if (in_array($handler, $configNames)) continue;
			$this->setConfig($handler, $handlerConfig);
		}

		return true;
	}

	/**
	 * Set handler config
	 *
	 * @param string $name
	 * @param array $config array of config entries
	 * @return bool
	 */
	public function setConfig($name, array $config = array())
	{
		$configs = $this->fileConfig;
		$defaultConfig = array();
		if (isset($configs['handlers'][$name])) {
			$defaultConfig = $configs['handlers'][$name];
		}

		if (!isset($defaultConfig['item_type'])) $defaultConfig['item_type'] = 'misc';

		$data = array();
		$exists = (bool)$this->getConfig($name);
		if (empty($exists)) {
			$modify = 'INSERT';
			$data['created'] = date('Y-m-d H:i:s');
			$data['last_run_data'] = json_encode(array());
			$data['handler'] = $name;
			$data['name'] = $name;

			$where = '';
			$this->configs[$name] = $defaultConfig;
		} else {
			$modify = 'UPDATE';
			$data['name'] = $name;
			$where = 'name = :name';
			if (!isset($config['item_type'])) { // just values
				$this->setValues($name, $config);
			}
		}
		$data['modified'] = date('Y-m-d H:i:s');
		$data['config'] = json_encode($this->getConfig($name));
		$query = ep_db_modify(TABLE_EASYPOPULATE_FEEDS, $data, $modify, $where);
		$result = ZMRuntime::getDatabase()->update($query, $data, TABLE_EASYPOPULATE_FEEDS);
		return true;
	}
}
?>
