<?php

/**
 * Command to look up a DOI in the BrowZine backend.
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
namespace VuFindSearch\Backend\BrowZine\Command;

use VuFindSearch\Backend\BrowZine\Backend;

/**
 * Command to look up a DOI in the BrowZine backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class LookupDoiCommand extends \VuFindSearch\Command\CallMethodCommand
{
    /**
     * Constructor
     *
     * @param string $backend Search backend identifier
     * @param string $doi     DOI to look up
     */
    public function __construct(string $backend, string $doi)
    {
        parent::__construct(
            $backend,
            Backend::class,
            'lookupDoi',
            [$doi],
            null,
            false
        );
    }
}
