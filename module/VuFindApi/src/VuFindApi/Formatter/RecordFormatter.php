<?php
/**
 * Record formatter for API responses
 *
 * PHP Version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind
 * @package  API_Formatter
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFindApi\Formatter;
use VuFind\I18n\TranslatableString;
use Zend\I18n\Translator\TranslatorInterface;
use Zend\View\HelperPluginManager;

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
     * Translator
     *
     * @var TranslatorInterface
     */
    protected $translator;

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
     * @param TranslatorInterface $translator    Translator
     * @param HelperPluginManager $helperManager View helper plugin manager
     */
    public function __construct($recordFields, TranslatorInterface $translator,
        HelperPluginManager $helperManager
    ) {
        $this->recordFields = $recordFields;
        $this->translator = $translator;
        $this->helperManager = $helperManager;
    }

    /**
     * Get dedup IDs
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return array|null
     */
    protected function getRecordDedupIds($record)
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
     * Get full record for a record as XML
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return string|null
     */
    protected function getRecordFullRecord($record)
    {
        if ($xml = $record->tryMethod('getFilteredXML')) {
            return $xml;
        }
        $rawData = $record->tryMethod('getRawData');
        return isset($rawData['fullrecord']) ? $rawData['fullrecord'] : null;
    }

    /**
     * Get record identifier
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return mixed
     */
    protected function getRecordIdentifier($record)
    {
        if ($id = $record->tryMethod('getIdentifier')) {
            if (is_array($id) && count($id) === 1) {
                $id = reset($id);
            }
            return $id;
        }
        return null;
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
        $urlHelper = $this->helperManager->get('recordLink');
        return $urlHelper->getUrl($record);
    }

    /**
     * Get raw data for a record as an array
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return array
     */
    protected function getRecordRawData($record)
    {
        $rawData = $record->tryMethod('getRawData');

        // Leave out spelling data
        unset($rawData['spelling']);

        return $rawData;
    }

    /**
     * Get source
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return array|null
     */
    protected function getRecordSource($record)
    {
        if ($sources = $record->tryMethod('getSource')) {
            $result = [];
            foreach ($sources as $source) {
                $result[] = [
                    'value' => $source,
                    'translated' => $this->translator->translate("source_$source")
                ];
            }
            return $result;
        }
        return null;
    }

    /**
     * Get URLs
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return array
     */
    protected function getRecordURLs($record)
    {
        $recordHelper = $this->helperManager->get('Record');
        return $recordHelper($record)->getLinkDetails();
    }

    /**
     * Get fields from a record as an array
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     * @param array                            $fields Fields to get
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
            $method = isset($this->recordFields[$field]['method'])
                ? $this->recordFields[$field]['method']
                : $this->recordFields[$field];
            if (method_exists($this, $method)) {
                $value = $this->{$method}($record);
            } else {
                $value = $record->tryMethod($method);
            }
            $result[$field] = $value;
        }
        // Convert any translation aware string classes to strings
        $translator = $this->translator;
        array_walk_recursive(
            $result,
            function (&$value) use ($translator) {
                if (is_object($value)) {
                    if ($value instanceof TranslatableString) {
                        $value = [
                            'value' => (string)$value,
                            'translated' => $translator->translate($value)
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
     * Return record field specs for the Swagger specification
     *
     * @return array
     */
    public function getRecordFieldSpec()
    {
        $fields = array_map(
            function ($item) {
                if (isset($item['method'])) {
                    unset($item['method']);
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
     * @param array $results         Results to process
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
