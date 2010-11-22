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
 * @todo instantiate based on name/handler
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
		$pluginDir = ZMPlugins::instance()->getPluginForId('easyPopulate')->getPluginDirectory();
		$yaml = file_get_contents($pluginDir . 'config/config.yml');
		$this->fileConfig = ZMRuntime::yamlload2($yaml); // sfYaml
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
		$query = "SELECT id, name, handler, config FROM  " . TABLE_EASYPOPULATE_FEEDS;

		if (!empty($name)) $query .= ' WHERE name = :name';
		$result = ZMRuntime::getDatabase()->query($query, array('name' => $name), TABLE_EASYPOPULATE_FEEDS);
		$configs = array();
		foreach ($result as $fields) {
			$name = $fields['name'];
			$defaultConfig = $this->fileConfig['handlers'][$name];
			$defaultConfig['handler'] = $fields['handler'];
			$defaultConfig['id'] = $fields['id'];
			//$defaltConfig['item_type'] = $result->fields['item_type'];
			$config = json_decode($fields['config'], true);
			$configs[$name] = array_merge($defaultConfig, (array)$config);
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
		$data['modified'] = date('Y-m-d H:i:s');

		$exists = $this->getConfig($name);
		if (empty($exists)) { // INSERT
			$data['created'] = date('Y-m-d H:i:s');
			$data['last_run_data'] = json_encode(array());
			$data['handler'] = $name;
			$data['name'] = $name;
			$this->configs[$name] = $defaultConfig;
			$data['config'] = json_encode($defaultConfig);
			$result = ZMRuntime::getDatabase()->createModel(TABLE_EASYPOPULATE_FEEDS, $data);
		} else { // UPDATE
			$data['name'] = $name;
			$data['id'] = $exists['id'];
			if (!isset($config['item_type'])) { // just values
				$this->setValues($name, $config);
			}
			$data['config'] = json_encode($this->getConfig($name));
			$result = ZMRuntime::getDatabase()->updateModel(TABLE_EASYPOPULATE_FEEDS, $data);
		}
		return true;
	}

	/**
	 * Get items that are no longer in the input file
	 */
	public function getMissingItems($name, array $productIds)
	{
		$query = "SELECT * FROM " . TABLE_EASYPOPULATE_FEEDS . " WHERE name = :name";

		$result = ZMRuntime::getDatabase()->querySingle($query, array('name' => $name), TABLE_EASYPOPULATE_FEEDS);
		$lastProductIds = json_decode($result['last_run_data'], true);
		$diff = array();
		if (!empty($lastProductIds)) {
			$diff = array_diff($lastProductIds, $productIds);
		}
		return $diff;
	}

	/**
	 * Update missing items
	 */
	public function updateMissingItems($name, $productIds = array())
	{
		$data = array();
		$data['last_run_data'] = json_encode(array_unique($productIds));
		$data['modified'] = date('Y-m-d H:i:s');
		$date['name'] = $name;
		$result = ZMRuntime::getDatabase()->updateModel(TABLE_EASYPOPULATE_FEEDS, $data);
	}
}
?>
