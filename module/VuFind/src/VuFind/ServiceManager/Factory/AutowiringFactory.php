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
use LogicException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\Feature\ExplodeSettingTrait;
use VuFind\Config\YamlReader;

use function is_array;

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
    use ExplodeSettingTrait;

    /**
     * Configuration manager
     *
     * @var ?ConfigManagerInterface
     */
    protected ?ConfigManagerInterface $configManager = null;

    /**
     * YAML reader
     *
     * @var ?YamlReader
     */
    protected ?YamlReader $yamlReader = null;

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
            try {
                $attributes = $reflectionParameter->getAttributes(Autowire::class);
                $autowireArgs = ($attributes[0] ?? null)?->getArguments();
                $params[] = $this->resolveParameter($container, $reflectionParameter, $autowireArgs);
            } catch (\Exception $e) {
                $paramName = $reflectionParameter->getName();
                throw new \Exception(
                    "Problem resolving parameter $paramName when building $requestedName: " . $e->getMessage(),
                    previous: $e
                );
            }
        }
        return new $requestedName(...$params);
    }

    /**
     * Resolve and get parameter.
     *
     * @param ContainerInterface  $container           Service container
     * @param ReflectionParameter $reflectionParameter Parameter
     * @param ?array              $autowireArgs        Autowire attribute arguments
     *
     * @return mixed
     */
    protected function resolveParameter(
        ContainerInterface $container,
        ReflectionParameter $reflectionParameter,
        ?array $autowireArgs
    ) {
        if ($config = $autowireArgs['config'] ?? null) {
            return $this->getConfig($container, $config, $reflectionParameter, $autowireArgs);
        }
        return $this->resolveService($container, $reflectionParameter, $autowireArgs);
    }

    /**
     * Get a configuration as an array.
     *
     * @param ContainerInterface  $container           Service container
     * @param string              $config              Configuration name
     * @param ReflectionParameter $reflectionParameter Parameter
     * @param ?array              $autowireArgs        Autowire attribute arguments
     *
     * @return mixed
     */
    protected function getConfig(
        ContainerInterface $container,
        string $config,
        ReflectionParameter $reflectionParameter,
        ?array $autowireArgs
    ) {
        $result = null;
        $type = $autowireArgs['configType'] ?? 'array';
        switch ($type) {
            case 'array':
            case 'object':
                $this->configManager ??= $container->get(ConfigManagerInterface::class);
                $result = 'object' === $type
                    ? $this->configManager->getConfigObject($config)
                    : $this->configManager->getConfigArray($config);
                break;
            case 'yaml':
                $this->yamlReader ??= $container->get(YamlReader::class);
                $result = $this->yamlReader->get("$config.yaml");
                break;
            default:
                throw new LogicException("Invalid configType $type");
        }

        if (null !== $result && $path = $autowireArgs['path'] ?? null) {
            if (!is_array($result)) {
                throw new LogicException('Autowiring path can only be used with an array type configuration');
            }
            foreach (explode('/', $path) as $part) {
                if (null === ($result = $result[$part] ?? null)) {
                    break;
                }
            }
            if (null !== $result && null !== ($explode = $autowireArgs['explode'] ?? null)) {
                $result = $this->explodeSetting((string)$result, true, $explode);
            } else {
                $result ??= $autowireArgs['default'] ?? null;
                if (null !== $result) {
                    // Cast to proper type:
                    $type = $reflectionParameter->getType();
                    if ($type instanceof ReflectionNamedType) {
                        $result = match ($type->getName()) {
                            'array' => (array)$result,
                            'bool' => (bool)$result,
                            'float' => (float)$result,
                            'int' => (int)$result,
                            'string' => (string)$result,
                            default => $result,
                        };
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Resolve service for a constructor parameter.
     *
     * @param ContainerInterface  $container           Service container
     * @param ReflectionParameter $reflectionParameter Parameter
     * @param ?array              $autowireArgs        Autowire attribute arguments
     *
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function resolveService(
        ContainerInterface $container,
        ReflectionParameter $reflectionParameter,
        ?array $autowireArgs
    ) {
        $name = $autowireArgs['service'] ?? null;
        if (null === $name) {
            $type = $reflectionParameter->getType();
            $name = $type?->getName();
            if (null === $name || !($type instanceof ReflectionNamedType)) {
                throw new LogicException('Unable to resolve type of parameter ' . $reflectionParameter->getName());
            }
            if ($type->isBuiltIn()) {
                throw new LogicException(
                    'Unable to autowire parameter ' . $reflectionParameter->getName() . ' of type ' . $type->getName()
                );
            }
        }

        $containerToUse = ($containerName = $autowireArgs['container'] ?? null)
            ? $container->get($containerName)
            : $container;
        return $containerToUse->get((string)$name);
    }
}
