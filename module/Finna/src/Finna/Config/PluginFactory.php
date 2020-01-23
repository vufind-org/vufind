<?php
/**
 * VuFind Config Plugin Factory
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\Config;

use Interop\Container\ContainerInterface;
use VuFind\Config\Locator;

/**
 * VuFind Config Plugin Factory
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
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

    /**
     * Create a service for the specified name.
     *
     * @param ContainerInterface $container     Service container
     * @param string             $requestedName Name of service
     * @param array              $options       Options
     *
     * @return object
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options['configPath'])) {
            return $this
                ->loadConfigFile($requestedName, $options['configPath']);
        }
        return parent::__invoke($container, $requestedName, $options);
    }
}
