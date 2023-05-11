<?php

/**
 * Command interface definition.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2013.
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
 * @author   David Maus <maus@hab.de>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Command;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Exception\LogicException;
use VuFindSearch\ParamBag;

/**
 * Command interface definition.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
interface CommandInterface
{
    /**
     * Return target backend identifier.
     *
     * @return string
     */
    public function getTargetIdentifier(): string;

    /**
     * Execute command on backend.
     *
     * @param BackendInterface $backend Backend
     *
     * @return CommandInterface Command instance for method chaining
     */
    public function execute(BackendInterface $backend): CommandInterface;

    /**
     * Was the command executed?
     *
     * @return bool
     */
    public function isExecuted(): bool;

    /**
     * Return result of executed operation.
     *
     * @throws LogicException Command was not yet executed
     *
     * @return mixed
     */
    public function getResult();

    /**
     * Return search parameters.
     *
     * @return ParamBag
     */
    public function getSearchParameters(): ParamBag;

    /**
     * Return command context.
     *
     * @return mixed
     */
    public function getContext();
}
