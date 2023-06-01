<?php

/**
 * Get information from the EDS backend
 *
 * PHP version 8
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

namespace VuFindSearch\Backend\EDS\Command;

use VuFindSearch\Backend\EDS\Backend;
use VuFindSearch\Command\CallMethodCommand;
use VuFindSearch\ParamBag;

/**
 * Get information from the EDS backend
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GetInfoCommand extends CallMethodCommand
{
    /**
     * Constructor.
     *
     * @param string    $backendId Search backend identifier
     * @param ?ParamBag $params    Search backend parameters
     */
    public function __construct(
        string $backendId = 'EDS',
        ParamBag $params = null
    ) {
        parent::__construct(
            $backendId,
            Backend::class,
            'getInfo',
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
        return [];
    }
}
