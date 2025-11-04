<?php

/**
 * SolrAuth record fallback loader
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Record
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\Record\FallbackLoader;

/**
 * SolrAuth record fallback loader
 *
 * @category VuFind
 * @package  Record
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SolrAuth extends Solr
{
    /**
     * Record source
     *
     * @var string
     */
    protected $source = 'SolrAuth';

    /**
     * Fetch a single record (null if not found).
     *
     * @param string $id ID to load
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     */
    protected function fetchSingleRecord($id)
    {
        // Try to find the record with an identifier:
        $result = $this->loadRecordWithIdentifier($id, null, 'identifier_str_mv');
        if ($result->first()) {
            return $result;
        }

        return new \VuFindSearch\Backend\Solr\Response\Json\RecordCollection(['recordCount' => 0]);
    }

    /**
     * When a record ID has changed, update the record driver and database to
     * reflect the changes.
     *
     * @param RecordDriver&PreviousUniqueIdInterface $record     Record to update
     * @param string                                 $previousId Old ID of record
     *
     * @return void
     */
    protected function updateRecord($record, $previousId)
    {
        // Do nothing, the fallback isn't really an identifier
    }
}
