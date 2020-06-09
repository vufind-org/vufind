<?php
/**
 * Record formatter for API responses
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2017.
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
namespace FinnaApi\Formatter;

use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\View\HelperPluginManager;

/**
 * Record formatter for API responses
 *
 * @category VuFind
 * @package  API_Formatter
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class RecordFormatter extends \VuFindApi\Formatter\RecordFormatter
{
    /**
     * Translator
     *
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * Constructor
     *
     * @param array               $recordFields  Record field definitions
     * @param HelperPluginManager $helperManager View helper plugin manager
     * @param TranslatorInterface $translator    Translator
     */
    public function __construct($recordFields, HelperPluginManager $helperManager,
        TranslatorInterface $translator
    ) {
        parent::__construct($recordFields, $helperManager);
        $this->translator = $translator;
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
        $lang = $this->translator->getLocale();
        $imageHelper = $this->helperManager->get('recordImage');
        $recordHelper = $this->helperManager->get('record');
        $translate = $this->helperManager->get('translate');
        $images = $imageHelper($recordHelper($record))->getAllImagesAsCoverLinks(
            $lang, [], false, false
        );
        foreach ($images as &$image) {
            if (empty($image['rights'])) {
                $image['rights'] = [
                    'copyright' => $translate('Image Rights Default')
                ];
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
     * Get image rights
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getImageRights($record)
    {
        $lang = $this->translator->getLocale();
        $rights = $record->tryMethod('getImageRights', [$lang]);
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
        $images = [];
        $imageHelper = $this->helperManager->get('recordImage');
        $recordHelper = $this->helperManager->get('record');
        $serverUrlHelper = $this->helperManager->get('serverUrl');
        for ($i = 0;
             $i < $recordHelper($record)->getNumOfRecordImages('large', false);
             $i++
        ) {
            $images[] = $serverUrlHelper()
                . $imageHelper($recordHelper($record))
                    ->getLargeImage($i, [], false, false);
        }
        if (empty($images) && $record->getCleanISBN()) {
            $url = $imageHelper($recordHelper($record))
                ->getLargeImage(0, [], true, false);
            if ($url) {
                $images[] = $url;
            }
        }
        // Output relative Cover generator urls
        foreach ($images as &$image) {
            $parts = parse_url($image);
            $image = $parts['path'] . '?' . $parts['query'];
        }
        return $images;
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
                        "0/$institution/", null, $institution
                    )
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
        $urls = $record->getOnlineURLs();

        if ($urls) {
            $translate = $this->helperManager->get('translate');
            foreach ($urls as &$url) {
                if (isset($url['source'])) {
                    if (is_array($url['source'])) {
                        $translated = [];
                        foreach ($url['source'] as $source) {
                            $translated[] = $translate->translate(
                                "source_$source", null, $source
                            );
                        }
                    } else {
                        $translated = $translate->translate(
                            'source_' . $url['source']
                        );
                    }
                    $url['source'] = [
                        'value' => $url['source'],
                        'translated' => $translated
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
        if ($record instanceof SolrMarc or $record instanceof SolrQdc) {
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
                if (isset($link['title'])
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
               'translated' => $translate($sector)
            ];
        }
        return $result;
    }

    /**
     * Get source
     *
     * @param \VuFind\RecordDriver\SolrDefault $record Record driver
     *
     * @return array|null
     */
    protected function getSource($record)
    {
        if ($sources = $record->tryMethod('getSource')) {
            $result = [];
            $translate = $this->helperManager->get('translate');
            foreach ($sources as $source) {
                $result[] = [
                    'value' => $source,
                    'translated' => $translate("source_$source", null, $source)
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
                if (isset($url['desc'])
                    && !$translationEmpty('link_' . $url['desc'])
                ) {
                    $url['translated'] = $translate('link_' . $url['desc']);
                    unset($url['desc']);
                }
            }
        }

        if ($serviceUrls) {
            $source = $record->getDataSource();
            foreach ($serviceUrls as &$url) {
                if (isset($url['desc'])
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
}
