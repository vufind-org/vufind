<?php

/**
 * Entity manager factory.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Db
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db;

use Doctrine\ORM\EntityManager;
use DoctrineModule\Service\AbstractFactory;
use DoctrineORMModule\Options\EntityManager as DoctrineORMModuleEntityManager;
use Psr\Container\ContainerInterface;

use function assert;

/**
 * Entity manager factory.
 *
 * Sets up the entity manager as described in the Doctrine ORM documentation
 * so that targetEntity resolution will occur reliably.
 *
 * @category VuFind
 * @package  Db
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EntityManagerFactory extends AbstractFactory
{
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
    public function __invoke(ContainerInterface $container, $requestedName, array|null $options = null)
    {
        $options = $this->getOptions($container, 'entitymanager');
        assert($options instanceof DoctrineORMModuleEntityManager);
        $connection = $container->get($options->getConnection());
        $config = $container->get($options->getConfiguration());

        // Do not use the standard entity resolver and configuration since
        // the mappings already exist in the plugin manager.
        $pm = $container->get(\VuFind\Db\Entity\PluginManager::class);

        $evm  = $connection->getEventManager();
        $rtel = new \Doctrine\ORM\Tools\ResolveTargetEntityListener();

        foreach ($pm->getAliases() as $interface => $class) {
            // Adds a target-entity class
            $rtel->addResolveTargetEntity($interface, $class, []);
        }

        // Add the ResolveTargetEntityListener
        $evm->addEventSubscriber($rtel);

        return new EntityManager($connection, $config, $evm);
    }

    /**
     * Get the class name of the options associated with this factory.
     *
     * @return string
     */
    public function getOptionsClass(): string
    {
        return DoctrineORMModuleEntityManager::class;
    }
}
