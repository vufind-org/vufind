<?php

/**
 * ILS (integrated library system) view helper
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

/**
 * ILS (integrated library system) view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Ils extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * ILS connection
     *
     * @var \VuFind\ILS\Connection
     */
    protected $connection;

    /**
     * Constructor
     *
     * @param \VuFind\ILS\Connection $connection ILS connection
     */
    public function __construct(\VuFind\ILS\Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get the ILS connection object.
     *
     * @return \VuFind\ILS\Connection
     */
    public function __invoke()
    {
        return $this->connection;
    }
}
