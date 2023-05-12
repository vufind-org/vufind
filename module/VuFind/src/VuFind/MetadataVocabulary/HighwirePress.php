<?php

/**
 * Metadata vocabulary implementation for Highwire Press
 *
 * PHP version 8
 *
 * Copyright (C) University of Tübingen 2019.
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
 * @package  Metadata_Vocabularies
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\MetadataVocabulary;

/**
 * Metadata vocabulary implementation for Highwire Press
 *
 * @category VuFind
 * @package  Metadata_Vocabularies
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class HighwirePress extends AbstractBase
{
    /**
     * Mapping from Highwire Press to VuFind fields; see
     * https://jira.duraspace.org/secure/attachment/13020/Invisible_institutional.pdf
     *
     * @var array
     */
    protected $vocabFieldToGenericFieldsMap = [
        'citation_author' => 'author',
        'citation_date' => 'date',
        'citation_doi' => 'doi',
        'citation_firstpage' => 'startpage',
        'citation_isbn' => 'isbn',
        'citation_issn' => 'issn',
        'citation_issue' => 'issue',
        'citation_journal_title' => 'container_title',
        'citation_language' => 'language',
        'citation_lastpage' => 'endpage',
        'citation_publisher' => 'publisher',
        'citation_title' => 'title',
        'citation_volume' => 'volume',
    ];

    /**
     * Special implementation for date formats
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return array
     */
    public function getMappedData(\VuFind\RecordDriver\AbstractBase $driver)
    {
        $mappedData = parent::getMappedData($driver);

        // special handling for dates
        if (isset($mappedData['citation_date'])) {
            foreach ($mappedData['citation_date'] as $key => $date) {
                // If we only have a year, leave it as-is
                // If we have a date, we need to convert to MM-DD-YYYY or MM/DD/YYYY
                if (!preg_match('"^\d+$"', $date)) {
                    $mappedData['citation_date'][$key]
                        = date('m/d/Y', strtotime($date));
                }
            }
        }

        return $mappedData;
    }
}
