<?php

/**
 * Trait for using autowiring to build objects.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\ServiceManager\Factory\AutowiringFactory;
use VuFindTest\Container\MockContainer;

use function is_array;
use function is_callable;

/**
 * Trait for using autowiring to build objects.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait AutowireTrait
{
    /**
     * Check whether the AutowiringFactory will need special containers, and create any that are missing.
     *
     * @param class-string<T>    $class     Class to build
     * @param ContainerInterface $container Container to populate
     *
     * @return void
     */
    protected function addMissingContainersToContainer(string $class, ContainerInterface $container): void
    {
        $reflectionClass = new ReflectionClass($class);

        // Nothing to do if there is no constructor:
        if (null === ($constructor = $reflectionClass->getConstructor())) {
            return;
        }

        // Nothing to do if there are no constructor parameters:
        $reflectionParameters = $constructor->getParameters();
        if (empty($reflectionParameters)) {
            return;
        }

        // Check for containers in constructor parameter attributes:
        foreach ($reflectionParameters as $reflectionParameter) {
            $attributes = $reflectionParameter->getAttributes(Autowire::class);
            $autowireArgs = ($attributes[0] ?? null)?->getArguments();
            if ($containerName = $autowireArgs['container'] ?? null) {
                // If the container lacks the required container service, or if it's a MockContainer that hasn't been
                // explicity populated with a custom mock yet, make sure we have a mock container in place.
                if (
                    !$container->has($containerName)
                    || ($container instanceof MockContainer && $container->willAutoMockService($containerName))
                ) {
                    if (is_callable([$container, 'set'])) {
                        // If $container is a MockContainer, we'll use it as the plugin manager. This makes it easier
                        // to override plugins when using the trait, since everything can be defined in a single
                        // global space. However, if $container is some other kind of object, we should create a new
                        // MockContainer for more predictable behavior.
                        $container->set(
                            $containerName,
                            $container instanceof MockContainer ? $container : new MockContainer($this)
                        );
                    } else {
                        throw new \Exception('Cannot automatically add missing dependency: ' . $containerName);
                    }
                }
            }
        }
    }

    /**
     * Get an autowired object.
     *
     * @param class-string<T>               $class               Class to build
     * @param ContainerInterface|array|null $containerOrServices Pre-populated container, or array of services
     *
     * @template T
     *
     * @return T
     */
    protected function getAutowiredObject(
        string $class,
        ContainerInterface|array|null $containerOrServices = null
    ): object {
        $container = $containerOrServices instanceof ContainerInterface
            ? $containerOrServices : new MockContainer($this);
        foreach (is_array($containerOrServices) ? $containerOrServices : [] as $serviceName => $service) {
            $container->set($serviceName, $service);
        }
        $this->addMissingContainersToContainer($class, $container);
        $factory = new AutowiringFactory();
        return $factory($container, $class);
    }
}
