<?php

/**
 * PSR-11 container that firewalls MCP capability code away from the rest of the application.
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
 * @package  Mcp
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindApi\Mcp;

use Mcp\Exception\ContainerException;
use Mcp\Exception\ServiceNotFoundException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;

use function class_exists;
use function in_array;
use function str_starts_with;

/**
 * PSR-11 container that firewalls MCP capability code away from the rest of the application.
 *
 * Only two things are ever buildable: a service on an explicit allowlist (fetched directly from
 * the wrapped container), or a capability class under one of an explicit set of namespaces (built
 * by reflecting on its constructor). A capability class's constructor parameters must themselves
 * each be an allowed service or have a default value -- resolution never falls back to
 * auto-wiring some other arbitrary class to satisfy a parameter, and no class outside the
 * capability namespaces is ever instantiated at all, no matter how trivially reflectable it might
 * be.
 *
 * @category VuFind
 * @package  Mcp
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FirewallContainer implements ContainerInterface
{
    /**
     * Instances already resolved, whether fetched from the wrapped container or auto-wired.
     *
     * @var array<string, object>
     */
    protected array $instances = [];

    /**
     * Constructor.
     *
     * @param ContainerInterface $container            Container to delegate allowed lookups to
     * @param string[]           $allowedServices      FQCNs of services callers may retrieve
     *                                                 directly from the wrapped container
     * @param string[]           $capabilityNamespaces Namespace prefixes (each including a
     * trailing backslash) that a class may be under to be built by reflection, e.g.
     * ['VuFindApi\Mcp\Capabilities\']
     */
    public function __construct(
        protected ContainerInterface $container,
        protected array $allowedServices,
        protected array $capabilityNamespaces
    ) {
    }

    /**
     * Get a service: an allowed service comes from the wrapped container, a capability class is
     * built by reflection (see autowire()), and nothing else is available at all.
     *
     * @param string $id Service identifier (a FQCN)
     *
     * @return mixed
     *
     * @throws ServiceNotFoundException Neither an allowed service nor a capability class
     * @throws ContainerException       A capability class was found but could not be auto-wired
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if ($this->isAllowedService($id)) {
            return $this->instances[$id] = $this->container->get($id);
        }
        if ($this->isCapabilityClass($id)) {
            return $this->instances[$id] = $this->autowire($id);
        }
        throw new ServiceNotFoundException("Service \"$id\" is not available to this container.");
    }

    /**
     * Is a service allowed from the wrapped container, or a capability class that can be
     * auto-wired?
     *
     * @param string $id Service identifier (a FQCN)
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || $this->isAllowedService($id) || $this->isCapabilityClass($id);
    }

    /**
     * Is $id one of the services this container may fetch directly from the wrapped container?
     *
     * @param string $id Service identifier (a FQCN)
     *
     * @return bool
     */
    protected function isAllowedService(string $id): bool
    {
        return in_array($id, $this->allowedServices, true) && $this->container->has($id);
    }

    /**
     * Is $id a real class under one of the capability namespaces this container may build by
     * reflection?
     *
     * @param string $id Service identifier (a FQCN)
     *
     * @return bool
     */
    protected function isCapabilityClass(string $id): bool
    {
        if (!class_exists($id)) {
            return false;
        }
        foreach ($this->capabilityNamespaces as $namespace) {
            if (str_starts_with($id, $namespace)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Instantiate a capability class by reflecting on its constructor. Each parameter must itself
     * be an allowed service (fetched from the wrapped container) or have a default value -- never
     * another arbitrary class built by a further round of auto-wiring, which would let a
     * capability class reach services beyond the allowlist simply by depending on an intermediate
     * class that depends on them.
     *
     * @param string $className Capability class to instantiate
     *
     * @return object
     *
     * @throws ContainerException Constructor has a parameter that is not an allowed service and
     * has no default value
     */
    protected function autowire(string $className): object
    {
        $reflectionClass = new ReflectionClass($className);
        $constructor = $reflectionClass->getConstructor();
        if (null === $constructor || 0 === $constructor->getNumberOfParameters()) {
            return new $className();
        }

        $args = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;
            if (null !== $typeName && $this->isAllowedService($typeName)) {
                $args[] = $this->get($typeName);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
            } else {
                throw new ContainerException(
                    "Cannot resolve parameter \${$parameter->getName()} of \"$className\": not an allowed "
                    . 'service, and has no default value.'
                );
            }
        }
        return $reflectionClass->newInstanceArgs($args);
    }
}
