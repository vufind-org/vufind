<?php

/**
 * PSR-11 container that exposes only an explicit allowlist of services from a wrapped container.
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

use Mcp\Exception\ServiceNotFoundException;
use Psr\Container\ContainerInterface;

use function in_array;

/**
 * PSR-11 container that exposes only an explicit allowlist of services from a wrapped container.
 *
 * Used to firewall MCP capability code away from the rest of the application: capability classes
 * only ever see this container, so they can only ever obtain services named on the allowlist, no
 * matter what the wrapped container itself can provide.
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
     * Constructor.
     *
     * @param ContainerInterface $container       Container to delegate allowed lookups to
     * @param string[]           $allowedServices FQCNs of services callers may retrieve
     */
    public function __construct(
        protected ContainerInterface $container,
        protected array $allowedServices
    ) {
    }

    /**
     * Get a service, if it is on the allowlist.
     *
     * @param string $id Service identifier (a FQCN)
     *
     * @return mixed
     *
     * @throws ServiceNotFoundException Service is not on the allowlist (or not in the wrapped
     * container)
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new ServiceNotFoundException("Service \"$id\" is not available to this container.");
        }
        return $this->container->get($id);
    }

    /**
     * Is a service on the allowlist (and available from the wrapped container)?
     *
     * @param string $id Service identifier (a FQCN)
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return in_array($id, $this->allowedServices, true) && $this->container->has($id);
    }
}
