<?php
/**
 * VuFind Config Plugin Factory
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Config;

use VuFind\Config\Locator;

/**
 * VuFind Config Plugin Factory
 *
 * @category VuFind2
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class PluginFactory extends \VuFind\Config\PluginFactory
{
    /**
     * Load the specified configuration file.
     *
     * @param string $filename config file name
     * @param string $path     path relative to VuFind base (optional; defaults
     * to config/vufind
     *
     * @return Config
     */
    protected function loadConfigFile($filename, $path = 'config/vufind')
    {
        // If we don't have a local config file in config/vufind, check for it in
        // config/finna and load from there if found.
        $localConfig = Locator::getLocalConfigPath($filename, $path);
        if ($localConfig === null) {
            $localConfig = Locator::getLocalConfigPath($filename, 'config/finna');
            if ($localConfig !== null) {
                return parent::loadConfigFile($filename, 'config/finna');
            }
        }

        return parent::loadConfigFile($filename, $path);
    }
}
