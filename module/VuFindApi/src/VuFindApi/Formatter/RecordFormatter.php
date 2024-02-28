<?php

/**
 * Record formatter for API responses
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  API_Formatter
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFindApi\Formatter;

use Laminas\View\HelperPluginManager;
use VuFind\I18n\TranslatableString;

use function is_object;

/**
 * Record formatter for API responses
 *
 * @category VuFind
 * @package  API_Formatter
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class RecordFormatter extends BaseFormatter
{
    /**
     * Record field definitions
     *
     * @var array
     */
    protected $recordFields;

    /**
     * View helper plugin manager
     *
     * @var HelperPluginManager
     */
    protected $helperManager;

    /**
     * Constructor
     *
     * @param array               $recordFields  Record field definitions
     * @param HelperPluginManager $helperManager View helper plugin manager
     */
    public function __construct(
        $recordFields,
        HelperPluginManager $helperManager
    ) {
        $this->recordFields = $recordFields;
        $this->helperManager = $helperManager;
    }

    /**
     * Get dedup IDs
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return array|null
     */
    protected function getDedupIds($record)
    {
        if (!($dedupData = $record->tryMethod('getDedupData'))) {
            return null;
        }
        $result = [];
        foreach ($dedupData as $item) {
            $result[] = $item['id'];
        }
        return $result ? $result : null;
    }

    /**
     * Get extended subject headings
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getExtendedSubjectHeadings($record)
    {
        $result = $record->getAllSubjectHeadings(true);
        // Make sure that the record driver returned the additional information and
        // return data only if it did
        return $result && isset($result[0]['heading']) ? $result : null;
    }

    /**
     * Get full record for a record as XML
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return string|null
     */
    protected function getFullRecord($record)
    {
        if ($xml = $record->tryMethod('getFilteredXML')) {
            return $xml;
        }
        $rawData = $record->tryMethod('getRawData');
        return $rawData['fullrecord'] ?? null;
    }

    /**
     * Get raw data for a record as an array
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return array
     */
    protected function getRawData($record)
    {
        $rawData = $record->tryMethod('getRawData');

        // Leave out spelling data
        unset($rawData['spelling']);

        return $rawData;
    }

    /**
     * Get (relative) link to record page
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return string
     */
    protected function getRecordPage($record)
    {
        $urlHelper = $this->helperManager->get('recordLinker');
        return $urlHelper->getUrl($record);
    }

    /**
     * Get URLs
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return array
     */
    protected function getURLs($record)
    {
        $recordHelper = $this->helperManager->get('record');
        return $recordHelper($record)->getLinkDetails();
    }

    /**
     * Get fields from a record as an array
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     * @param array                             $fields Fields to get
     *
     * @return array
     */
    protected function getFields($record, $fields)
    {
        $result = [];
        foreach ($fields as $field) {
            if (!isset($this->recordFields[$field])) {
                continue;
            }
            $method = $this->recordFields[$field]['vufind.method'];
            if (strncmp($method, 'Formatter::', 11) == 0) {
                $value = $this->{substr($method, 11)}($record);
            } else {
                $value = $record->tryMethod($method);
            }
            $result[$field] = $value;
        }
        // Convert any translation aware string classes to strings
        $translator = $this->helperManager->get('translate');
        array_walk_recursive(
            $result,
            function (&$value) use ($translator) {
                if (is_object($value)) {
                    if ($value instanceof TranslatableString) {
                        $value = [
                            'value' => (string)$value,
                            'translated' => $translator->translate($value),
                        ];
                    } else {
                        $value = (string)$value;
                    }
                }
            }
        );

        return $result;
    }

    /**
     * Get record field definitions.
     *
     * @return array
     */
    public function getRecordFields()
    {
        return $this->recordFields;
    }

    /**
     * Return record field specs for the API specification
     *
     * @return array
     */
    public function getRecordFieldSpec()
    {
        $fields = array_map(
            function ($item) {
                foreach (array_keys($item) as $key) {
                    if (strncmp($key, 'vufind.', 7) == 0) {
                        unset($item[$key]);
                    }
                }
                return $item;
            },
            $this->recordFields
        );
        return $fields;
    }

    /**
     * Format the results.
     *
     * @param array $results         Results to process (array of record drivers)
     * @param array $requestedFields Fields to include in response
     *
     * @return array
     */
    public function format($results, $requestedFields)
    {
        $records = [];
        foreach ($results as $result) {
            $records[] = $this->getFields($result, $requestedFields);
        }

        $this->filterArrayValues($records);

        return $records;
    }
}
