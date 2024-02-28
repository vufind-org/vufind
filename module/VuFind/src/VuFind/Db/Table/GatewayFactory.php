<?php

/**
 * Generic table gateway factory.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Db\Table;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * Generic table gateway factory.
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GatewayFactory implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    /**
     * Return row prototype object (null if unavailable)
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     *
     * @return object
     */
    protected function getRowPrototype(ContainerInterface $container, $requestedName)
    {
        $rowManager = $container->get(\VuFind\Db\Row\PluginManager::class);
        // Map Table class to matching Row class.
        $name = str_replace('\\Table\\', '\\Row\\', $requestedName);
        return $rowManager->has($name) ? $rowManager->get($name) : null;
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
        array $options = null
    ) {
        $adapter = $container->get(\Laminas\Db\Adapter\Adapter::class);
        $tm = $container->get(\VuFind\Db\Table\PluginManager::class);
        $config = $container->get('config');
        $rowPrototype = $this->getRowPrototype($container, $requestedName);
        $args = $options ? $options : [];
        return new $requestedName(
            $adapter,
            $tm,
            $config,
            $rowPrototype,
            ...$args
        );
    }
}
