<?php
/**
 * EasyPopulate Plugin
 *
 * @package easypopulate
 * @author John William Robeson, Jr <johnny@localmomentum.net>
 * @copyright 2010
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 */

define('TABLE_EASYPOPULATE_FEEDS', ZM_DB_PREFIX . 'easypopulate_feeds');

/**
 * Import/Export delimited files
 *
 * @package org.zenmagick.plugins.easypopulate
 */
class ZMEasyPopulatePlugin extends Plugin {
    function __construct() {
        parent::__construct('EasyPopulate', 'Import/Export delimited files', '3.9.5');
        $this->setContext(Plugin::CONTEXT_ADMIN);
        ZMLoader::instance()->addPath(ZMFileUtils::mkPath(array($this->getPluginDirectory(), 'Handlers')));
    }

    public function set($name, $value) {
        parent::set($name, $value);
        ZMObject::__set($name, $value);
    }

    public function install() {
        parent::install();
        ZMDbUtils::executePatch(file(ZMDbUtils::resolveSQLFilename($this->getPluginDirectory()."sql/install.sql")), $this->messages_);

        $this->addConfigValue('Log Errors', 'debug_logging', 'false', 'Log Errors',
            'widget@BooleanFormWidget#name=debug_logging&default=false&label=Log Errors&style=checkbox');
        $this->addConfigValue('Log All Queries', 'log_queries', 'false', 'Log all SQL queries - useful for debugging',
            'widget@BooleanFormWidget#name=log_queries&default=false&label=Log Queries&style=checkbox');
        $this->addConfigValue('Uploads Directory', 'temp_dir', 'tempEP/', 'Name of directory for your uploads',
            'widget@TextFormWidget#name=temp_dir&default=tempEP&size=50&maxlength=255');
        $this->addConfigValue('File Processing Time Limit', 'time_limit', '1200', '(In Seconds) You can change this if your script is taking too long to process. This functionality may be not always be enabled by your server administrator',
            'widget@TextFormWidget#name=time_limit&default=1200&size=6&maxlength=6');
    }

    /**
     * {@inheritDoc}
     */
    public function remove($keepSettings=false) {
        parent::remove($keepSettings);
        ZMDbUtils::executePatch(file(ZMDbUtils::resolveSQLFilename($this->getPluginDirectory()."sql/uninstall.sql")), $this->messages_);
    }

    /**
     * {@inheritDoc}
     */
    public function init() {
        parent::init();
        foreach ($this->getConfigValues() as $entry) {
            $this->set(strtolower($entry->getName()), $entry->getValue());
        }

        $this->set('temp_path', DIR_FS_CATALOG . $this->get('temp_dir'));

        $GLOBALS['epConfig'] = $this->getProperties(); // @todo ZM MIGRATE, drop this line when ep_get_config() is gone

        ZMUrlManager::instance()->setMappings(array(
            'import' => array('controller' => 'EasyPopulateController#method=route'), 
            'export' => array('controller' => 'EasyPopulateController#method=route'),
            'preset' => array('controller' => 'EasyPopulateController#method=route'), // @todo change the name of this
            // @todo delegate uploads to zenmagick
            'temp_upload' => array('controller' => 'EasyPopulateController#method=upload')
        ));
        // add admin pages
        $menuKey = $this->addMenuGroup(_zm('EasyPopulate'));
        $this->addMenuItem2(_zm('Import'), 'import', $menuKey);
        $this->addMenuItem2(_zm('Export'), 'export', $menuKey);
    }
}
