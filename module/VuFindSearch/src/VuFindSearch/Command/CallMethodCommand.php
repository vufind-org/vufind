<?php

/**
 * Call method command.
 *
 * PHP version 8
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
use VuFindSearch\Feature\ExtraRequestDetailsInterface;
use VuFindSearch\ParamBag;

use function call_user_func;

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
     * Optional search details.
     *
     * @var ?array
     */
    protected $extraRequestDetails = null;

    /**
     * CallMethodCommand constructor.
     *
     * @param string    $backendId Search backend identifier
     * @param string    $interface Search backend interface
     * @param string    $method    Search backend interface method
     * @param ?ParamBag $params    Search backend parameters
     * @param mixed     $context   Command context. Optional, if left out the search
     * interface method is used as the context.
     */
    public function __construct(
        string $backendId,
        string $interface,
        string $method,
        ?ParamBag $params = null,
        $context = null
    ) {
        parent::__construct(
            $backendId,
            $context ?: $method,
            $params
        );
        $this->interface = $interface;
        $this->method = $method;
    }

    /**
     * Return search backend interface method arguments.
     *
     * @return array
     */
    abstract public function getArguments(): array;

    /**
     * Get extra request details.
     *
     * @return ?array
     */
    public function getExtraRequestDetails(): ?array
    {
        return $this->extraRequestDetails;
    }

    /**
     * Execute command on backend.
     *
     * @param BackendInterface $backend Backend
     *
     * @return CommandInterface Command instance for method chaining
     */
    public function execute(BackendInterface $backend): CommandInterface
    {
        $this->validateBackend($backend);
        if (
            !($backend instanceof $this->interface)
            || !method_exists($this->interface, $this->method)
        ) {
            throw new BackendException(
                "$this->backendId does not support $this->method()"
            );
        }
        $args = $this->getArguments();
        if ($backend instanceof ExtraRequestDetailsInterface) {
            $backend->resetExtraRequestDetails();
        }
        $this->finalizeExecution(
            call_user_func([$backend, $this->method], ...$args)
        );
        if ($backend instanceof ExtraRequestDetailsInterface) {
            $this->extraRequestDetails = $backend->getExtraRequestDetails();
        }
        return $this;
    }
}
