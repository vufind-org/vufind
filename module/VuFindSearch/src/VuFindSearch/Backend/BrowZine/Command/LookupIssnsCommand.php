<?php

/**
 * Command to look up ISSNs in the BrowZine backend.
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

namespace VuFindSearch\Backend\BrowZine\Command;

use VuFindSearch\Backend\BrowZine\Backend;

/**
 * Command to look up ISSNs in the BrowZine backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class LookupIssnsCommand extends \VuFindSearch\Command\CallMethodCommand
{
    /**
     * ISSNs to look up.
     *
     * @var string|string[]
     */
    protected $issns;

    /**
     * Constructor
     *
     * @param string          $backendId Search backend identifier
     * @param string|string[] $issns     ISSNs to look up
     */
    public function __construct(string $backendId, $issns)
    {
        $this->issns = $issns;
        parent::__construct(
            $backendId,
            Backend::class,
            'lookupIssns'
        );
    }

    /**
     * Return search backend interface method arguments.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return [$this->getIssns()];
    }

    /**
     * Return ISSNs to look up.
     *
     * @return string|string[]
     */
    public function getIssns()
    {
        return $this->issns;
    }
}
