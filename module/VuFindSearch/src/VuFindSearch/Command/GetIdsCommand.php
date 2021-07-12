<?php

/**
 * Get identifiers of records command.
 *
 * PHP version 7
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindSearch\Command;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Feature\GetIdsInterface;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\QueryInterface;

/**
 * Get identifiers of records command.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GetIdsCommand extends CallMethodCommand
{
    /**
     * GetIdsCommand constructor.
     *
     * @param string         $backend Search backend identifier
     * @param QueryInterface $query   Search query
     * @param int            $offset  Search offset
     * @param int            $limit   Search limit
     * @param ?ParamBag      $params  Search backend parameters
     */
    public function __construct(string $backend, QueryInterface $query,
        int $offset = 0, int $limit = 20, ?ParamBag $params = null
    ) {
        parent::__construct(
            $backend, GetIdsInterface::class, 'getIds', [$query, $offset, $limit],
            $params, true, 'getids'
        );
    }

    /**
     * Execute command on backend.
     *
     * @param BackendInterface $backendInstance Backend instance
     *
     * @return CommandInterface Command instance for method chaining
     */
    public function execute(BackendInterface $backendInstance): CommandInterface
    {
        if (!($backendInstance instanceof GetIdsInterface)) {
            $this->interface = BackendInterface::class;
            $this->method = 'search';
        }
        return parent::execute($backendInstance);
    }
}
