<?php

/**
 * Record formatter for API responses
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2023.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace FinnaApi\Formatter;

use Finna\RecordDriver\RenderContext;
use Laminas\View\HelperPluginManager;

use function count;
use function in_array;
use function is_array;

/**
 * Record formatter for API responses
 *
 * @category VuFind
 * @package  API_Formatter
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class RecordFormatter extends \VuFindApi\Formatter\RecordFormatter
{
    /**
     * User locale
     *
     * @var string
     */
    protected $locale;

    /**
     * Current record render context
     *
     * @var RenderContext
     */
    protected RenderContext $renderContext = RenderContext::RECORD;

    /**
     * Constructor
     *
     * @param array               $recordFields  Record field definitions
     * @param HelperPluginManager $helperManager View helper plugin manager
     * @param string              $locale        User locale
     */
    public function __construct(
        $recordFields,
        HelperPluginManager $helperManager,
        string $locale
    ) {
        parent::__construct($recordFields, $helperManager);
        $this->locale = $locale;
    }

    /**
     * Set current record render context
     *
     * @param string $context Render context
     *
     * @return void
     */
    public function setRecordRenderContext(string $context): void
    {
        $this->renderContext = RenderContext::tryFrom($context);
    }

    /**
     * Get extended image information
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array
     */
    protected function getExtendedImages($record)
    {
        $imageHelper = $this->helperManager->get('recordImage');
        $recordHelper = $this->helperManager->get('record');
        $translate = $this->helperManager->get('translate');
        $images = $imageHelper($recordHelper($record))->getAllImagesAsCoverLinks(
            $this->locale,
            [],
            false,
            false
        );
        foreach ($images as &$image) {
            if (empty($image['rights']['copyright'])) {
                $image['rights']['copyright'] = $translate('Image Rights Default');
            }
        }
        return $images;
    }

    /**
     * Get record identifier
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return mixed
     */
    public function getIdentifier($record)
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
     * Get amount of current images rendered if result does not contain all the results.
     * This result will be added to the search result if any field related to images is being
     * requested in search context.
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return string
     */
    public function getRecordImagesCountNotice($record): string
    {
        $imageRenderLimit = $record->tryMethod('getImagesRenderLimit');
        $translate = $this->helperManager->get('translate');
        $totalAmountOfImages = $record->tryMethod('getTotalAmountOfImages');
        if (in_array($imageRenderLimit, [null, -1])) {
            return '';
        }
        if ($imageRenderLimit < $totalAmountOfImages) {
            return $translate(
                'component_parts_entries_on_page',
                [
                        '_START_' => 1,
                        '_END_' => $imageRenderLimit,
                        '_TOTAL_' => $totalAmountOfImages,
                    ]
            );
        }
        return '';
    }

    /**
     * Get image rights
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getImageRights($record)
    {
        $rights = $record->tryMethod('getImageRights', [$this->locale]);
        return $rights ? $rights : null;
    }

    /**
     * Get images
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array
     */
    protected function getImages($record)
    {
        $images = $this->getExtendedImages($record);
        return array_map(
            fn ($url) => $url['urls']['large'],
            $images
        );
    }

    /**
     * Get institutions
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getInstitutions($record)
    {
        if ($institutions = $record->tryMethod('getInstitutions')) {
            $result = [];
            $translate = $this->helperManager->get('translate');
            foreach ((array)$institutions as $institution) {
                $result[] = [
                    'value' => $institution,
                    'translated' => $translate(
                        "0/$institution/",
                        null,
                        $institution
                    ),
                ];
            }
            return $result;
        }
        return null;
    }

    /**
     * Get online URLs for a record as an array
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getOnlineURLs($record)
    {
        $urls = $record->tryMethod('getOnlineURLs');

        if ($urls) {
            $translate = $this->helperManager->get('translate');
            foreach ($urls as &$url) {
                if (isset($url['source'])) {
                    if (is_array($url['source'])) {
                        $translated = [];
                        foreach ($url['source'] as $source) {
                            $translated[] = $translate->translate(
                                "source_$source",
                                null,
                                $source
                            );
                        }
                    } else {
                        $translated = $translate->translate(
                            'source_' . $url['source']
                        );
                    }
                    $url['source'] = [
                        'value' => $url['source'],
                        'translated' => $translated,
                    ];
                }
            }
        }
        return $urls;
    }

    /**
     * Get raw data for a record as an array
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array
     */
    protected function getRawData($record)
    {
        $rawData = $record->tryMethod('getRawData');

        // Filter out fullrecord since it has its own field
        unset($rawData['fullrecord']);

        // description in MARC and QDC records may contain non-CC0 text, so leave
        // it out
        if (
            $record instanceof \VuFind\RecordDriver\SolrMarc
            || $record instanceof \Finna\RecordDriver\SolrQdc
        ) {
            unset($rawData['description']);
        }

        // Leave out spelling data
        unset($rawData['spelling']);

        return $rawData;
    }

    /**
     * Get record links for a record as an array
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getRecordLinks($record)
    {
        $links = $record->tryMethod('getAllRecordLinks');
        if ($links) {
            $translate = $this->helperManager->get('translate');
            $translationEmpty = $this->helperManager->get('translationEmpty');
            foreach ($links as &$link) {
                if (
                    isset($link['title'])
                    && !$translationEmpty($link['title'])
                ) {
                    $link['translated'] = $translate($link['title']);
                }
            }
        }
        return $links;
    }

    /**
     * Get sectors
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getSectors($record)
    {
        $rawData = $record->tryMethod('getRawData');
        if (empty($rawData['sector_str_mv'])) {
            return null;
        }
        $result = [];
        $translate = $this->helperManager->get('translate');
        foreach ($rawData['sector_str_mv'] as $sector) {
            $result[] = [
               'value' => (string)$sector,
               'translated' => $translate($sector),
            ];
        }
        return $result;
    }

    /**
     * Get sources
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return     array|null
     * @deprecated For back-compatibility, use getSources
     */
    protected function getSource($record)
    {
        return $this->getSources($record);
    }

    /**
     * Get sources
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getSources($record)
    {
        if ($sources = $record->tryMethod('getSources')) {
            $result = [];
            $translate = $this->helperManager->get('translate');
            foreach ($sources as $source) {
                $result[] = [
                    'value' => $source,
                    'translated' => $translate("source_$source", null, $source),
                ];
            }
            return $result;
        }
        return null;
    }

    /**
     * Get URLs for a record as an array
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getURLs($record)
    {
        $urls = $record->getURLs();
        $serviceUrls = $record->tryMethod('getServiceUrls');

        $translationEmpty = $this->helperManager->get('translationEmpty');
        $translate = $this->helperManager->get('translate');
        if ($urls) {
            foreach ($urls as &$url) {
                if (
                    isset($url['desc'])
                    && !$translationEmpty('link_' . $url['desc'])
                ) {
                    $url['translated'] = $translate('link_' . $url['desc']);
                    unset($url['desc']);
                }
            }
        }

        if ($serviceUrls) {
            $source = $record->tryMethod('getDataSource');
            foreach ($serviceUrls as &$url) {
                if (
                    isset($url['desc'])
                    && !$translationEmpty($source . '_' . $url['desc'])
                ) {
                    $url['translated']
                        = $translate($source . '_' . $url['desc']);
                    unset($url['desc']);
                }
            }
            $urls += $serviceUrls;
        }
        return $urls ? $urls : null;
    }

    /**
     * Get record index.
     *
     * Returns '__primary__' for the default search backend.
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return string
     */
    protected function getIndex($record)
    {
        $backend = $record->getSearchBackendIdentifier();
        if (DEFAULT_SEARCH_BACKEND === $backend) {
            $backend = '__primary__';
        }
        return $backend;
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
        if (
            isset($this->recordFields['recordImagesCountNotice'])
            && array_intersect($requestedFields, ['images', 'imagesExtended', 'imageRights'])
            && !in_array('recordImagesCountNotice', $requestedFields)
        ) {
            $requestedFields[] = 'recordImagesCountNotice';
        }
        $results = array_map(
            function ($record) {
                $record->tryMethod('setRenderContext', [$this->renderContext->value]);
                return $record;
            },
            $results
        );
        return parent::format($results, $requestedFields);
    }
}
