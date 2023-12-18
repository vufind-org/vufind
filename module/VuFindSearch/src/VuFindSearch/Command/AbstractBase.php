<?php

/**
 * Abstract base command.
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
use VuFindSearch\Exception\LogicException;
use VuFindSearch\Exception\RuntimeException;
use VuFindSearch\ParamBag;

/**
 * Abstract base command.
 *
 * @category VuFind
 * @package  Search
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
abstract class AbstractBase implements CommandInterface
{
    /**
     * Search backend identifier
     *
     * @var string
     */
    protected $backendId;

    /**
     * Command context
     *
     * @var mixed
     */
    protected $context;

    /**
     * Search backend parameters
     *
     * @var ParamBag
     */
    protected $params;

    /**
     * Was the command executed?
     *
     * @var bool
     */
    protected $executed = false;

    /**
     * Result of executed operation
     *
     * @var mixed
     */
    protected $result;

    /**
     * Constructor.
     *
     * @param string    $backendId Search backend identifier
     * @param mixed     $context   Command context
     * @param ?ParamBag $params    Search backend parameters
     */
    public function __construct(
        string $backendId,
        $context,
        ?ParamBag $params = null
    ) {
        $this->backendId = $backendId;
        $this->context = $context;
        $this->params = $params ?: new ParamBag();
    }

    /**
     * Return target backend identifier.
     *
     * @return string
     */
    public function getTargetIdentifier(): string
    {
        return $this->backendId;
    }

    /**
     * Set target backend identifier.
     *
     * @param string $identifier New identifier
     *
     * @return void
     */
    public function setTargetIdentifier(string $identifier): void
    {
        $this->backendId = $identifier;
    }

    /**
     * Save a result, flag the command as executed, and return the command object;
     * useful as the final step in execute() implementations.
     *
     * @param mixed $result Result of execution.
     *
     * @return CommandInterface
     */
    protected function finalizeExecution($result): CommandInterface
    {
        $this->result = $result;
        $this->executed = true;
        return $this;
    }

    /**
     * Validate that the provided backend matches the expected target identifier.
     *
     * @param BackendInterface $backend Backend instance
     *
     * @return void
     * @throws RuntimeException
     */
    protected function validateBackend(BackendInterface $backend): void
    {
        if (($backendId = $backend->getIdentifier()) !== $this->backendId) {
            throw new RuntimeException(
                "Expected backend instance $this->backendId instead of $backendId"
            );
        }
    }

    /**
     * Was the command executed?
     *
     * @return bool
     */
    public function isExecuted(): bool
    {
        return $this->executed;
    }

    /**
     * Return result of executed operation.
     *
     * @throws LogicException Command was not yet executed
     *
     * @return mixed
     */
    public function getResult()
    {
        if (!$this->isExecuted()) {
            throw new LogicException('Command was not yet executed');
        }
        return $this->result ?? null;
    }

    /**
     * Return search parameters.
     *
     * @return ParamBag
     */
    public function getSearchParameters(): ParamBag
    {
        return $this->params;
    }

    /**
     * Set search parameters.
     *
     * @param ParamBag $params Params
     *
     * @return void
     */
    public function setSearchParameters(ParamBag $params): void
    {
        $this->params = $params;
    }

    /**
     * Return command context.
     *
     * @return mixed
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Set command context.
     *
     * @param mixed $context Context
     *
     * @return void
     */
    public function setContext($context)
    {
        $this->context = $context;
    }
}
