<?php

/**
 * Record formatter for API responses.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  API_Formatter
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFindApi\Formatter;

use VuFind\Http\ServerUrlHelper;
use VuFind\I18n\TranslatableString;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\RecordDriver\AbstractBase as AbstractRecord;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\Record;
use VuFind\View\Helper\Root\RecordLinker;

use function is_object;

/**
 * Record formatter for API responses.
 *
 * @category VuFind
 * @package  API_Formatter
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class RecordFormatter extends BaseFormatter implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Constructor.
     *
     * @param RecordLinker    $recordLinker    Record linker
     * @param Record          $recordHelper    Record view helper
     * @param ServerUrlHelper $serverUrlHelper Server URL helper
     */
    public function __construct(
        #[Autowire(container: 'ViewHelperManager')]
        protected RecordLinker $recordLinker,
        #[Autowire(container: 'ViewHelperManager')]
        protected Record $recordHelper,
        protected ?ServerUrlHelper $serverUrlHelper = null
    ) {
    }

    /**
     * Get dedup IDs.
     *
     * @param AbstractRecord $record Record driver
     *
     * @return ?array
     */
    protected function getDedupIds(AbstractRecord $record): ?array
    {
        if (!($dedupData = $record->tryMethod('getDedupData'))) {
            return null;
        }
        return array_column($dedupData, 'id') ?: null;
    }

    /**
     * Get extended subject headings.
     *
     * @param AbstractRecord $record Record driver
     *
     * @return ?array
     */
    protected function getExtendedSubjectHeadings(AbstractRecord $record): ?array
    {
        $result = $record->tryMethod('getAllSubjectHeadings', [true]);
        // Make sure that the record driver returned the additional information and return data only if it did:
        return $result && isset($result[0]['heading']) ? $result : null;
    }

    /**
     * Get full record for a record as XML.
     *
     * @param AbstractRecord $record Record driver
     *
     * @return ?string
     */
    protected function getFullRecord(AbstractRecord $record): ?string
    {
        if ($xml = $record->tryMethod('getFilteredXML')) {
            return $xml;
        }
        $rawData = $record->tryMethod('getRawData');
        return $rawData['fullrecord'] ?? null;
    }

    /**
     * Get raw data for a record as an array.
     *
     * @param AbstractRecord $record Record driver
     *
     * @return array
     */
    protected function getRawData(AbstractRecord $record): array
    {
        $rawData = $record->tryMethod('getRawData', default: []);

        // Leave out spelling data
        unset($rawData['spelling']);

        return $rawData;
    }

    /**
     * Get relative link to record page.
     *
     * @param AbstractRecord $record Record driver
     *
     * @return string
     *
     * @deprecated Use getRecordPageRelativeLink instead
     */
    protected function getRecordPage(AbstractRecord $record): string
    {
        return $this->getRecordPageRelativeLink($record);
    }

    /**
     * Get relative link to record page.
     *
     * @param AbstractRecord $record Record driver
     *
     * @return string
     */
    protected function getRecordPageRelativeLink(AbstractRecord $record): string
    {
        return $this->recordLinker->getUrl($record);
    }

    /**
     * Get absolute link to record page.
     *
     * @param AbstractRecord $record Record driver
     *
     * @return string
     */
    protected function getRecordPageAbsoluteLink(AbstractRecord $record): string
    {
        $recordPage = $this->getRecordPageRelativeLink($record);
        return $this->serverUrlHelper->getUrlForPath($recordPage);
    }

    /**
     * Get URLs.
     *
     * @param AbstractRecord $record Record driver
     *
     * @return array
     */
    protected function getURLs(AbstractRecord $record): array
    {
        return ($this->recordHelper)($record)->getLinkDetails();
    }

    /**
     * Get fields from a record as an array.
     *
     * @param \VuFind\RecordDriver\AbstractBase $record            Record driver
     * @param array                             $fields            Fields to get
     * @param array                             $recordFieldConfig Record field configuration
     *
     * @return array
     */
    protected function getFields(AbstractRecord $record, array $fields, array $recordFieldConfig): array
    {
        $result = [];
        foreach ($fields as $field) {
            if (!isset($recordFieldConfig[$field])) {
                continue;
            }
            $method = $recordFieldConfig[$field]['vufind.method'];
            $value = strncmp($method, 'Formatter::', 11) == 0
                ? $this->{substr($method, 11)}($record)
                : $record->tryMethod($method);
            $result[$field] = $value;
        }
        // Convert any translation aware string classes to strings
        array_walk_recursive(
            $result,
            function (&$value): void {
                if (is_object($value)) {
                    if ($value instanceof TranslatableString) {
                        $value = [
                            'value' => (string)$value,
                            'translated' => $this->translator->translate($value),
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
     * Return record field specs for the API specification.
     *
     * @param array $recordFieldConfig Record field configuration
     *
     * @return array
     */
    public function getRecordFieldSpec(array $recordFieldConfig): array
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
            $recordFieldConfig
        );
        return $fields;
    }

    /**
     * Format the results.
     *
     * @param array $results           Results to process (array of record drivers)
     * @param array $requestedFields   Fields to include in response
     * @param array $recordFieldConfig Record field configuration
     *
     * @return array
     */
    public function format($results, $requestedFields, array $recordFieldConfig)
    {
        $records = [];
        foreach ($results as $result) {
            $records[] = $this->getFields($result, $requestedFields, $recordFieldConfig);
        }

        $this->filterArrayValues($records);

        return $records;
    }
}
