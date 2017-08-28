<?php
/**
 * VuFind Config Manager
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Config;
use Zend\ServiceManager\AbstractPluginManager as Base;

/**
 * VuFind Config Manager
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PluginManager extends Base
{
    /**
     * Validate the plugin
     *
     * Checks that the filter loaded is either a valid callback or an instance
     * of FilterInterface.
     *
     * @param mixed $plugin Plugin to validate
     *
     * @throws ServiceManagerRuntimeException if invalid
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validatePlugin($plugin)
    {
        // Assume everything is okay.
    }

    /**
     * Reload a configuration and return the new version
     *
     * @param string $id Service identifier
     *
     * @return \Zend\Config\Config
     */
    public function reload($id)
    {
        $oldOverrideSetting = $this->getAllowOverride();
        $this->setAllowOverride(true);
        $this->setService($id, $this->create($id));
        $this->setAllowOverride($oldOverrideSetting);
        return $this->get($id);
    }
}
