<?php

/**
 * Simple WorldCat2 record.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

namespace VuFindSearch\Backend\WorldCat2\Response;

use VuFindSearch\Response\RecordInterface;

/**
 * Simple WorldCat record.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Record implements RecordInterface
{
    use \VuFindSearch\Response\RecordTrait;

    /**
     * Constructor.
     *
     * @param array $rawData JSON data parsed into an array
     *
     * @return void
     */
    public function __construct(protected array $rawData)
    {
        $this->setSourceIdentifiers('WorldCat2');
    }

    /**
     * Get raw record
     *
     * @return array
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }
}
