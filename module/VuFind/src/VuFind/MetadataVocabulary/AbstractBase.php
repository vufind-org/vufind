<?php

/**
 * Metadata vocabulary base class
 * (provides results from available RecordDriver methods in a standardized form)
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

use function is_array;

/**
 * Metadata vocabulary base class
 * (provides results from available RecordDriver methods in a standardized form)
 *
 * @category VuFind
 * @package  Metadata_Vocabularies
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractBase implements MetadataVocabularyInterface
{
    /**
     * This variable can be overwritten by child classes
     * to define which custom field is filled by which generic fields.
     *
     * @var array
     */
    protected $vocabFieldToGenericFieldsMap = [];

    /**
     * Generate standardized data from available RecordDriver methods
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return array
     */
    protected function getGenericData(\VuFind\RecordDriver\AbstractBase $driver)
    {
        return [
            'author' => array_unique(
                array_merge(
                    $driver->tryMethod('getPrimaryAuthors') ?? [],
                    $driver->tryMethod('getSecondaryAuthors') ?? [],
                    $driver->tryMethod('getCorporateAuthors') ?? []
                )
            ),
            'container_title' => $driver->tryMethod('getContainerTitle'),
            'date' => $driver->tryMethod('getPublicationDates'),
            'doi' => $driver->tryMethod('getCleanDOI'),
            'endpage' => $driver->tryMethod('getContainerEndPage'),
            'isbn' => $driver->tryMethod('getCleanISBN'),
            'issn' => $driver->tryMethod('getCleanISSN'),
            'issue' => $driver->tryMethod('getContainerIssue'),
            'language' => $driver->tryMethod('getLanguages'),
            'publisher' => $driver->tryMethod('getPublishers'),
            'startpage' => $driver->tryMethod('getContainerStartPage'),
            'title' => $driver->tryMethod('getTitle'),
            'volume' => $driver->tryMethod('getContainerVolume'),
        ];
    }

    /**
     * Perform mapping from generic data to vocabulary data
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return array
     */
    public function getMappedData(\VuFind\RecordDriver\AbstractBase $driver)
    {
        $genericData = $this->getGenericData($driver);
        $mappedData = [];

        foreach ($this->vocabFieldToGenericFieldsMap as $vocabField => $genFields) {
            foreach ((array)$genFields as $genericField) {
                $genericValues = $genericData[$genericField] ?? [];
                if ($genericValues) {
                    if (!is_array($genericValues)) {
                        $genericValues = [$genericValues];
                    }
                    foreach ($genericValues as $genericValue) {
                        if (!isset($mappedData[$vocabField])) {
                            $mappedData[$vocabField] = [];
                        }
                        $mappedData[$vocabField][] = $genericValue;
                    }
                }
            }
        }

        return $mappedData;
    }
}
