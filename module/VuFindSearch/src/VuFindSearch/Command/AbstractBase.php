<?php

/**
 * Abstract base command.
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
use VuFindSearch\Exception\LogicException;
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
class AbstractBase implements CommandInterface
{
    /**
     * Search backend identifier
     *
     * @var string
     */
    protected $backend;

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
     * CallMethodCommand constructor.
     *
     * @param string    $backend Search backend identifier
     * @param mixed     $context Command context
     * @param ?ParamBag $params  Search backend parameters
     */
    public function __construct(string $backend, $context, ?ParamBag $params = null)
    {
        $this->backend = $backend;
        $this->context = $context;
        $this->params = $params ?: new ParamBag();
    }

    /**
     * Return name of target backend.
     *
     * @return string
     */
    public function getTargetBackendName(): string
    {
        return $this->backend;
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
        $this->executed = true;
        return $this;
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
            throw new LogicException("Command was not yet executed");
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
     * Return command context.
     *
     * @return mixed
     */
    public function getContext()
    {
        return $this->context;
    }
}
