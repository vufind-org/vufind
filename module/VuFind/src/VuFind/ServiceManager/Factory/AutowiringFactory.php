<?php

/**
 * VuFind Autowiring Factory
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
 * @package  ServiceManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ServiceManager\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Renderer\PhpRenderer;
use LogicException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use VuFind\Config\ConfigManager;

/**
 * VuFind Autowiring Factory
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AutowiringFactory implements FactoryInterface
{
    /**
     * Configuration manager
     *
     * @var ?ConfigManager
     */
    protected ?ConfigManager $configManager = null;

    /**
     * Create a service for the specified name.
     *
     * @param ContainerInterface $container     Service container
     * @param string             $requestedName Name of service
     * @param ?array             $options       Options (unused)
     *
     * @return object
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        $reflectionClass = new ReflectionClass($requestedName);

        // Just create the object if there is no constructor:
        if (null === ($constructor = $reflectionClass->getConstructor())) {
            return new $requestedName();
        }

        // Just create the object if there are no constructor parameters:
        $reflectionParameters = $constructor->getParameters();
        if (empty($reflectionParameters)) {
            return new $requestedName();
        }

        // Map constructor parameters:
        $params = [];
        foreach ($reflectionParameters as $reflectionParameter) {
            $attributes = $reflectionParameter->getAttributes(Autowire::class);
            $autowireArgs = ($attributes[0] ?? null)?->getArguments();
            if ($config = $autowireArgs['config'] ?? null) {
                $params[] = $this->getConfigArray($container, $config);
            } else {
                $params[] = $this->resolveService($container, $autowireArgs, $reflectionParameter);
            }
        }
        return new $requestedName(...$params);
    }

    /**
     * Get a configuration as an array.
     *
     * @param ContainerInterface $container Service container
     * @param string             $config    Configuration name
     *
     * @return array
     */
    protected function getConfigArray(ContainerInterface $container, string $config): array
    {
        $this->configManager ??= $container->get(ConfigManager::class);
        return $this->configManager->getConfigArray($config);
    }

    /**
     * Resolve service for a constructor parameter.
     *
     * @param ContainerInterface  $container           Service container
     * @param ?array              $autowireArgs        Autowire attribute arguments
     * @param ReflectionParameter $reflectionParameter Parameter
     *
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function resolveService(
        ContainerInterface $container,
        ?array $autowireArgs,
        ReflectionParameter $reflectionParameter
    ) {
        $name = $autowireArgs['service'] ?? null;
        if (null === $name) {
            $type = $reflectionParameter->getType();
            $name = $type->getName();
            if (null === $name || !($type instanceof ReflectionNamedType)) {
                throw new LogicException('Unable to resolve type of parameter ' . $reflectionParameter->getName());
            }
        }
        $containerToUse = ($containerName = $autowireArgs['container'] ?? null)
            ? $container->get($containerName)
            : $container;
        return ($containerToUse instanceof PhpRenderer)
            ? $containerToUse->plugin((string)$name)
            : $containerToUse->get((string)$name);
    }
}
