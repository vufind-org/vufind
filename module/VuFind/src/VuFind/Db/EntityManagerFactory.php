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
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Proxy\ProxyFactory;
use Laminas\Cache\Psr\CacheItemPool\CacheItemPoolDecorator;
use Laminas\Cache\Storage\Adapter\BlackHole;
use Laminas\Cache\Storage\Capabilities;
use Laminas\Cache\Storage\PostEvent;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use stdClass;
use VuFind\Db\Mapping\ClassMetadataFactory;
use VuFind\Db\Mapping\ClassMetadataMappingsInterface;

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
class EntityManagerFactory implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    /**
     * Cache storage.
     *
     * @var ?StorageInterface
     */
    protected ?StorageInterface $cacheStorage = null;

    /**
     * Create an object.
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
        $connection = $container->get(Connection::class);
        $paths = [
            __DIR__ . '/Entity',
        ];
        $isDevMode = APPLICATION_ENV == 'development';
        if ('cli' === PHP_SAPI) {
            $cache = new CacheItemPoolDecorator(new BlackHole());
        } else {
            $this->cacheStorage = $container->get(\VuFind\Cache\Manager::class)->getCache('object');

            // Cache item pool decorator requires static TTL support that file system cache does not provide. Doctrine
            // doesn't need it, so fake the support:
            $eventManager = $this->cacheStorage->getEventManager();
            $eventManager->attach('getCapabilities.post', [$this, 'cacheCapabilitiesHandler']);
            $cache = new CacheItemPoolDecorator($this->cacheStorage);
            $eventManager->detach([$this, 'cacheCapabilitiesHandler']);
        }

        $config = ORMSetup::createAttributeMetadataConfig($paths, $isDevMode, cache: $cache);
        $config->setClassMetadataFactoryName(ClassMetadataFactory::class);
        $config->setProxyDir(LOCAL_CACHE_DIR . (PHP_SAPI == 'cli' ? '/cli' : '') . '/doctrine-proxies');
        $config->setProxyNamespace('DoctrineProxies');
        $config->setAutoGenerateProxyClasses(
            $isDevMode ? ProxyFactory::AUTOGENERATE_ALWAYS : ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED
        );
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
        $entityPluginManager = $container->get(\VuFind\Db\Entity\PluginManager::class);

        $entityManager = new EntityManager($connection, $config);

        // Add entity mappings to class metadata factory:
        $metadataFactory = $entityManager->getMetadataFactory();
        if ($metadataFactory instanceof ClassMetadataMappingsInterface) {
            $metadataFactory->setAliases($entityPluginManager->getAliases());
        }

        // Add LoadClassMetadataListener:
        $entityManager->getEventManager()->addEventListener(
            Events::loadClassMetadata,
            new LoadClassMetadataListener($entityManager, $entityPluginManager->getAliases())
        );

        return $entityManager;
    }

    /**
     * Cache adapter post capabilities check handler that adds fake TTL support.
     *
     * @param PostEvent $event Event
     *
     * @return void
     */
    public function cacheCapabilitiesHandler(PostEvent $event): void
    {
        if ($event->getStorage() !== $this->cacheStorage) {
            return;
        }
        $oldCapacities = $event->getResult();
        $newCapacities = new Capabilities(
            $event->getStorage(),
            new stdClass(),
            ['staticTtl' => true],
            $oldCapacities
        );
        $event->setResult($newCapacities);
    }
}
