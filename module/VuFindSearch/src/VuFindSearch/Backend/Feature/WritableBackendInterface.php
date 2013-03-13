<?php

/**
 * Writable backend feature interface definition.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

namespace VuFindSearch\Backend\Feature;

use VuFindSearch\ParamBag;

/**
 * Writable backend feature interface definition.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
interface WritableBackendInterface
{
    /**
     * Delete a single record.
     *
     * @param string|array $id     Record identifier or array of record identifiers
     * @param ParamBag     $params Search backend parameters
     *
     * @return void
     */
    public function delete ($id, ParamBag $params = null);

    /**
     * Delete all records.
     *
     * @param ParamBag $params Backend parameters
     *
     * @return void
     */
    public function deleteAll (ParamBag $params = null);

    /**
     * Update a single record.
     *
     * @param mixed    $record Record
     * @param ParamBag $params Backend parameters
     *
     * @return void
     */
    public function update ($record, ParamBag $params = null);

}