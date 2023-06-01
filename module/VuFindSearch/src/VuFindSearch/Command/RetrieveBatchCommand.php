<?php

/**
 * Retrieve a batch of documents command.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
use VuFindSearch\Feature\RetrieveBatchInterface;
use VuFindSearch\ParamBag;

/**
 * Retrieve a batch of documents command.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RetrieveBatchCommand extends CallMethodCommand
{
    /**
     * Record identifiers.
     *
     * @var array
     */
    protected $ids;

    /**
     * RetrieveBatchCommand constructor.
     *
     * @param string    $backendId Search backend identifier
     * @param array     $ids       Record identifiers
     * @param ?ParamBag $params    Search backend parameters
     */
    public function __construct(
        string $backendId,
        array $ids,
        ?ParamBag $params = null
    ) {
        $this->ids = $ids;
        parent::__construct(
            $backendId,
            RetrieveBatchInterface::class,
            'retrieveBatch',
            $params
        );
    }

    /**
     * Return search backend interface method arguments.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return [
            $this->getRecordIdentifiers(),
            $this->getSearchParameters(),
        ];
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
        // If the backend implements the RetrieveBatchInterface, we can load
        // all the records at once.
        if ($backend instanceof RetrieveBatchInterface) {
            return parent::execute($backend);
        }

        // Otherwise, we need to load them one at a time and aggregate them.

        $response = false;
        foreach ($this->getRecordIdentifiers() as $id) {
            $next = $backend->retrieve($id, $this->params);
            if (!$response) {
                $response = $next;
            } elseif ($record = $next->first()) {
                $response->add($record);
            }
        }

        return $this->finalizeExecution($response);
    }

    /**
     * Return record identifiers.
     *
     * @return array
     */
    public function getRecordIdentifiers(): array
    {
        return $this->ids;
    }
}
