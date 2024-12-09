<?php

/**
 * Exception for paging too deep into search results.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Exception;

use Exception;
use VuFindSearch\Exception\RuntimeException;

/**
 * Exception for paging too deep into search results.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class DeepPagingException extends RuntimeException
{
    /**
     * A legal page number.
     *
     * @var int
     */
    protected $legalPage;

    /**
     * Constructor.
     *
     * @param string    $message   Exception message
     * @param int       $code      Exception code
     * @param int       $legalPage A legal page number for results
     * @param Exception $prev      Previous exception
     *
     * @return void
     */
    public function __construct(
        $message,
        $code,
        $legalPage = 0,
        Exception $prev = null
    ) {
        parent::__construct($message, $code, $prev);
        $this->legalPage = $legalPage;
    }

    /**
     * Get a legal page we can redirect the user to.
     *
     * @return int
     */
    public function getLegalPage()
    {
        return $this->legalPage;
    }
}
