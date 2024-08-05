<?php

/**
 * Simple factory for record collection.
 *
 * PHP version 8
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\LibGuides\Response;

/**
 * Simple factory for record collection.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RecordCollectionFactory extends \VuFindSearch\Response\AbstractJsonRecordCollectionFactory
{
    /**
     * Get the class name of the record collection to use by default.
     *
     * @return string
     */
    protected function getDefaultRecordCollectionClass(): string
    {
        return RecordCollection::class;
    }

    /**
     * Given a backend response, return an array of documents.
     *
     * @param array $response Backend response
     *
     * @return array
     */
    protected function getDocumentListFromResponse($response): array
    {
        return $response['documents'];
    }
}
