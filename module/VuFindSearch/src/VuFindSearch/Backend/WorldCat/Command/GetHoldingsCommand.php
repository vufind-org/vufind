<?php

/**
 * Command to fetch holdings from the WorldCat backend.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\WorldCat\Command;

use VuFindSearch\Command\Feature\RecordIdentifierTrait;

/**
 * Command to fetch holdings from the WorldCat backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GetHoldingsCommand extends \VuFindSearch\Command\CallMethodCommand
{
    use RecordIdentifierTrait;

    /**
     * Constructor
     *
     * @param string $backendId Search backend identifier
     * @param string $id        WorldCat record identifier
     */
    public function __construct(string $backendId, string $id)
    {
        $this->id = $id;
        parent::__construct(
            $backendId,
            \VuFindSearch\Backend\WorldCat\Backend::class,
            'getHoldings'
        );
    }

    /**
     * Return search backend interface method arguments.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return [$this->getRecordIdentifier()];
    }
}
