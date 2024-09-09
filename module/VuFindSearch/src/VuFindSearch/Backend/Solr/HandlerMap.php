<?php

/**
 * SOLR backend handler map.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Solr;

use InvalidArgumentException;
use RuntimeException;
use VuFindSearch\Backend\AbstractHandlerMap;
use VuFindSearch\ParamBag;

use function sprintf;

/**
 * SOLR backend handler map.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class HandlerMap extends AbstractHandlerMap
{
    /**
     * Search handlers, indexed by function.
     *
     * @var array
     */
    protected $handlers;

    /**
     * Query defaults/appends/invariants, indexed by handler.
     *
     * @var array
     */
    protected $parameters;

    /**
     * Fallback handler, if any.
     *
     * @var string
     */
    protected $fallback;

    /**
     * Constructor.
     *
     * @param array $map Handler map
     *
     * @return void
     */
    public function __construct(array $map = [])
    {
        $this->handlers = [];
        $this->parameters = [];
        $this->setHandlerMap($map);
    }

    /**
     * Set the handler map.
     *
     * @param array $map Handler map
     *
     * @return void
     *
     * @throws InvalidArgumentException Duplicate fallback handler
     * @throws InvalidArgumentException Duplicate function handler definition
     */
    public function setHandlerMap(array $map)
    {
        $fallback = null;
        foreach ($map as $handler => $definition) {
            if (isset($definition['fallback']) && $definition['fallback']) {
                if ($fallback) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Duplicate fallback handler definition: %s, %s',
                            $handler,
                            $fallback
                        )
                    );
                }
                $fallback = $handler;
            }
            if (isset($definition['functions'])) {
                foreach ((array)$definition['functions'] as $function) {
                    if (isset($this->handlers[$function])) {
                        throw new InvalidArgumentException(
                            sprintf(
                                'Handler for function already defined: %s, %s',
                                $function,
                                $handler
                            )
                        );
                    }
                    $this->handlers[$function] = $handler;
                }
            }
            if (isset($definition['invariants'])) {
                $this->setParameters(
                    $handler,
                    'invariants',
                    (array)$definition['invariants']
                );
            }
            if (isset($definition['defaults'])) {
                $this->setParameters(
                    $handler,
                    'defaults',
                    (array)$definition['defaults']
                );
            }
            if (isset($definition['appends'])) {
                $this->setParameters(
                    $handler,
                    'appends',
                    (array)$definition['appends']
                );
            }
        }
        $this->fallback = $fallback;
    }

    /**
     * Return function handler.
     *
     * @param string $function Name of search function
     *
     * @return string Handler name
     *
     * @throws RuntimeException Undefined function handler
     */
    public function getHandler($function)
    {
        if (!isset($this->handlers[$function])) {
            if (!$this->fallback) {
                throw new RuntimeException(
                    sprintf('Undefined function handler: %s', $function)
                );
            }
            return $this->fallback;
        }
        return $this->handlers[$function];
    }

    /**
     * Return query invariants for search function.
     *
     * @param string $function Name of search function
     *
     * @return array Query invariants
     */
    public function getInvariants($function)
    {
        $handler = $this->getHandler($function);
        return $this->getParameters($handler, 'invariants');
    }

    /**
     * Return query defaults for search function.
     *
     * @param string $function Name of search function
     *
     * @return array Query defaults
     */
    public function getDefaults($function)
    {
        $handler = $this->getHandler($function);
        return $this->getParameters($handler, 'defaults');
    }

    /**
     * Return query appends for search function.
     *
     * @param string $function Name of search function
     *
     * @return array Query appends
     */
    public function getAppends($function)
    {
        $handler = $this->getHandler($function);
        return $this->getParameters($handler, 'appends');
    }

    /**
     * Add handler default, append, or invariant.
     *
     * @param string $handler Request handler
     * @param string $type    Parameter type, one of 'defaults', 'appends',
     *                        or 'invariants'
     * @param string $name    Parameter name
     * @param string $value   Parameter value
     *
     * @return void
     */
    public function addParameter($handler, $type, $name, $value)
    {
        $this->getParameters($handler, $type)->add($name, $value);
    }

    /**
     * Set handler defaults, appends, or invariants.
     *
     * @param string $handler    Request handler
     * @param string $type       Parameter type, one of 'defaults', 'appends',
     *                           or 'invariants'
     * @param array  $parameters Parameters
     *
     * @return void
     */
    public function setParameters($handler, $type, array $parameters)
    {
        if ($type != 'invariants' && $type != 'appends' && $type != 'defaults') {
            throw new InvalidArgumentException(
                sprintf('Invalid parameter key: %s', $type)
            );
        }
        $this->parameters[$handler][$type] = new ParamBag($parameters);
    }

    /**
     * Return handler defaults, appends, or invariants.
     *
     * @param string $handler Request handler
     * @param string $type    Parameter type, one of 'defaults', 'appends',
     *                        or 'invariants'
     *
     * @return ParamBag
     *
     * @throws InvalidArgumentException Invalid parameter key
     */
    public function getParameters($handler, $type)
    {
        // Create ParamBag if not already present; this also handles validation
        // of the $type parameter.
        if (!isset($this->parameters[$handler][$type])) {
            $this->setParameters($handler, $type, []);
        }
        return $this->parameters[$handler][$type];
    }
}
