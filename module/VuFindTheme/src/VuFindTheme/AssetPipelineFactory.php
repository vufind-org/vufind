<?php

/**
 * Factory for AssetPipeline class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTheme;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

use function count;

/**
 * Factory for AssetPipeline class.
 *
 * @category VuFind
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AssetPipelineFactory implements FactoryInterface
{
    /**
     * Split config and return prefixed setting with current environment.
     *
     * @param array $config Configuration settings
     *
     * @return string|bool
     */
    protected function getPipelineConfig(array $config)
    {
        $default = false;
        if (isset($config['Site']['asset_pipeline'])) {
            $settings = array_map(
                'trim',
                explode(';', $config['Site']['asset_pipeline'])
            );
            foreach ($settings as $setting) {
                $parts = array_map('trim', explode(':', $setting));
                if (APPLICATION_ENV === $parts[0]) {
                    return $parts[1];
                } elseif (count($parts) == 1) {
                    $default = $parts[0];
                } elseif ($parts[0] === '*') {
                    $default = $parts[1];
                }
            }
        }
        return $default;
    }

    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException&\Throwable if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $configManager = $container->get(\VuFind\Config\PluginManager::class);
        $config = $configManager->get('config')?->toArray() ?? [];
        return new $requestedName(
            $container->get(\VuFindTheme\ThemeInfo::class),
            $container->get('ViewHelperManager')->get('url'),
            $this->getPipelineConfig($config),
            $config['Site']['asset_pipeline_max_css_import_size'] ?? null
        );
    }
}
