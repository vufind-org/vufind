<?php

/**
 * Abstract factory for building AbstractContent tabs.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\RecordTab;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Config\PluginManager as ConfigManager;
use VuFind\Content\PluginManager as ContentManager;

use function in_array;

/**
 * Abstract factory for building AbstractContent tabs.
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractContentFactory implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    /**
     * The name of the tab being constructed.
     *
     * @var string
     */
    protected $tabName;

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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        $config = $container->get(ConfigManager::class)->get('config');
        // Only instantiate the loader if the feature is enabled:
        $loader = isset($config->Content->{$this->tabName})
            ? $container->get(ContentManager::class)->get($this->tabName)
            : null;
        return new $requestedName($loader, $this->getHideSetting($config));
    }

    /**
     * Support method for construction of AbstractContent objects -- should we
     * hide this tab if it is empty?
     *
     * @param \Laminas\Config\Config $config VuFind configuration
     *
     * @return bool
     */
    protected function getHideSetting(\Laminas\Config\Config $config)
    {
        $setting = $config->Content->hide_if_empty ?? false;
        if (
            $setting === true || $setting === false
            || $setting === 1 || $setting === 0
        ) {
            return (bool)$setting;
        }
        if ($setting === 'true' || $setting === '1') {
            return true;
        }
        $hide = array_map('trim', array_map('strtolower', explode(',', $setting)));
        return in_array(strtolower($this->tabName), $hide);
    }
}
