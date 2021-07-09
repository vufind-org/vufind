<?php

/**
 * Call method command.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2021.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindSearch\Command;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Exception\RuntimeException;
use VuFindSearch\ParamBag;

/**
 * Call method command.
 *
 * @category VuFind
 * @package  Search
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
abstract class CallMethodCommand extends AbstractBase
{
    /**
     * Search backend interface
     *
     * @var string
     */
    protected $interface;

    /**
     * Search backend interface method
     *
     * @var string
     */
    protected $method;

    /**
     * Search backend interface method arguments
     *
     * @var array
     */
    protected $args;

    /**
     * Should the search backend parameters be added as the last method argument?
     *
     * @var bool
     */
    protected $addParamsToArgs;

    /**
     * CallMethodCommand constructor.
     *
     * @param string    $backend         Search backend identifier
     * @param string    $interface       Search backend interface
     * @param string    $method          Search backend interface method
     * @param array     $args            Search backend interface method arguments,
     *                                   excluding search backend parameters
     * @param ?ParamBag $params          Search backend parameters
     * @param bool      $addParamsToArgs Should the search backend parameters be
     *                                   added as the last method argument?
     * @param mixed     $context         Command context. Optional, if left out the
     *                                   search interface method is used as the
     *                                   context.
     */
    public function __construct(string $backend, string $interface, string $method,
        array $args, ?ParamBag $params = null, bool $addParamsToArgs = true,
        $context = null
    ) {
        parent::__construct($backend, $context ?: $method, $params);
        $this->interface = $interface;
        $this->method = $method;
        $this->args = $args;
        $this->addParamsToArgs = $addParamsToArgs;
    }

    /**
     * Execute command on backend.
     *
     * @param BackendInterface $backendInstance Backend instance
     *
     * @return CommandInterface
     */
    public function execute(BackendInterface $backendInstance): CommandInterface
    {
        if (($backend = $backendInstance->getIdentifier()) !== $this->backend) {
            throw new RuntimeException(
                "Excpected backend instance $this->backend instead of $backend"
            );
        }
        if (!($backendInstance instanceof $this->interface)
            || !method_exists($this->interface, $this->method)
        ) {
            throw new BackendException(
                "$this->backend does not support $this->method()"
            );
        }
        $callArgs = $this->args;
        if ($this->addParamsToArgs) {
            $callArgs[] = $this->params;
        }
        $this->result
            = call_user_func([$backendInstance, $this->method], ...$callArgs);

        return parent::execute($backendInstance);
    }
}
