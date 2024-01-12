<?php

/**
 * ILS aware delegator factory
 *
 * Copyright (C) Villanova University 2018.
 *
 * PHP version 8
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */

namespace VuFind\RecordDriver;

use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use Psr\Container\ContainerInterface;

use function call_user_func;
use function is_array;

/**
 * ILS aware delegator factory
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */
class IlsAwareDelegatorFactory implements DelegatorFactoryInterface
{
    /**
     * Invokes this factory.
     *
     * @param ContainerInterface $container Service container
     * @param string             $name      Service name
     * @param callable           $callback  Service callback
     * @param array|null         $options   Service options
     *
     * @return AbstractBase
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(
        ContainerInterface $container,
        $name,
        callable $callback,
        array $options = null
    ) {
        $driver = call_user_func($callback);

        // Attach the ILS if at least one backend supports it:
        $ilsBackends = $this->getIlsBackends($container);
        if (!empty($ilsBackends) && $container->has(\VuFind\ILS\Connection::class)) {
            $driver->attachILS(
                $container->get(\VuFind\ILS\Connection::class),
                $container->get(\VuFind\ILS\Logic\Holds::class),
                $container->get(\VuFind\ILS\Logic\TitleHolds::class)
            );
            $driver->setIlsBackends($ilsBackends);
        }

        return $driver;
    }

    /**
     * Get the ILS backend configuration.
     *
     * @param ContainerInterface $container Service container
     *
     * @return string[]
     */
    protected function getIlsBackends(ContainerInterface $container)
    {
        // Get a list of ILS-compatible backends.
        static $ilsBackends = null;
        if (!is_array($ilsBackends)) {
            $config = $container->get(\VuFind\Config\PluginManager::class)
                ->get('config');
            $settings = isset($config->Catalog) ? $config->Catalog->toArray() : [];

            // If the setting is missing, default to the default backend; if it
            // is present but empty, don't put an empty string in the final array!
            $rawSetting = $settings['ilsBackends'] ?? [DEFAULT_SEARCH_BACKEND];
            $ilsBackends = empty($rawSetting) ? [] : (array)$rawSetting;
        }
        return $ilsBackends;
    }
}
