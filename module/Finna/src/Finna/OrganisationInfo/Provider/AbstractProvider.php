<?php

/**
 * Abstract service for querying organisation info databases.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2024.
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
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace Finna\OrganisationInfo\Provider;

use Finna\Search\Solr\HierarchicalFacetHelper;
use Laminas\Mvc\Controller\Plugin\Url;
use VuFind\I18n\Sorter;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Search\Results\PluginManager;

use function strlen;

/**
 * Abstract service for querying organisation info databases.
 *
 * @category VuFind
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
abstract class AbstractProvider implements
    TranslatorAwareInterface,
    \VuFindHttp\HttpServiceAwareInterface,
    \Laminas\Log\LoggerAwareInterface,
    ProviderInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Template with all the default details fields
     *
     * @var array
     */
    protected $detailsTemplate = [
        'name' => '-',
        'description' => '',
        'address' => [],
        'email' => '',
        'emails' => [],
        'phones' => [],
        'allServices' => [],
        'personnel' => [],
        'rss' => [],
        'homepage' => '',
        'slogan' => '',
        'routeUrl' => '',
        'links' => [],
    ];

    /**
     * Organisation info configuration
     *
     * @var VuFind\Config\Config
     */
    protected $config;

    /**
     * Cache manager
     *
     * @var \VuFind\CacheManager
     */
    protected $cacheManager;

    /**
     * Date converter
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    /**
     * URL plugin
     *
     * @var Url
     */
    protected $urlPlugin;

    /**
     * Results plugin manager
     *
     * @var PluginManager
     */
    protected $resultsManager;

    /**
     * Hierarchical facet helper
     *
     * @var HierarchicalFacetHelper
     */
    protected $facetHelper;

    /**
     * Sorter
     *
     * @var Sorter
     */
    protected $sorter;

    /**
     * Constructor.
     *
     * @param \VuFind\Config\Config   $config         Configuration
     * @param \VuFind\Cache\Manager   $cacheManager   Cache manager
     * @param \VuFind\Date\Converter  $dateConverter  Date converter
     * @param Url                     $url            URL plugin
     * @param PluginManager           $resultsManager Results manager
     * @param HierarchicalFacetHelper $facetHelper    Hierarchical facet helper
     * @param Sorter                  $sorter         Sorter
     */
    public function __construct(
        \VuFind\Config\Config $config,
        \VuFind\Cache\Manager $cacheManager,
        \VuFind\Date\Converter $dateConverter,
        Url $url,
        PluginManager $resultsManager,
        HierarchicalFacetHelper $facetHelper,
        Sorter $sorter
    ) {
        $this->config = $config;
        $this->cacheManager = $cacheManager;
        $this->dateConverter = $dateConverter;
        $this->urlPlugin = $url;
        $this->resultsManager = $resultsManager;
        $this->facetHelper = $facetHelper;
        $this->sorter = $sorter;
    }

    /**
     * Check if a consortium is found in organisation info and return basic information
     *
     * @param string $language Language
     * @param string $id       Parent organisation ID
     *
     * @return array Associative array with 'id', 'logo' and 'name'
     */
    public function lookup(string $language, string $id): array
    {
        $result = $this->doLookup($language, $id);
        if (!empty($result['logo'])) {
            $result['logo'] = $this->proxifyImageUrl($result['logo']);
        }
        return $result;
    }

    /**
     * Get consortium information (includes list of locations)
     *
     * @param string $language       Language
     * @param string $id             Parent organisation ID
     * @param array  $locationFilter Optional list of locations to include
     *
     * @return array
     */
    public function getConsortiumInfo(string $language, string $id, array $locationFilter = []): array
    {
        $result = $this->doGetConsortiumInfo($language, $id, $locationFilter);
        if (!empty($result['consortium']['logo']['small'])) {
            $result['consortium']['logo']['small'] = $this->proxifyImageUrl($result['consortium']['logo']['small']);
        }
        foreach ($result['list'] ?? [] as &$item) {
            $item = $this->processDetails($item);
        }
        unset($item);
        return $result;
    }

    /**
     * Get location details
     *
     * @param string  $language   Language
     * @param string  $id         Parent organisation ID
     * @param string  $locationId Location ID
     * @param ?string $startDate  Start date (YYYY-MM-DD) of opening times (default is Monday of current week)
     * @param ?string $endDate    End date (YYYY-MM-DD) of opening times (default is Sunday of start date week)
     *
     * @return array
     */
    public function getDetails(
        string $language,
        string $id,
        string $locationId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $result = $this->doGetDetails($language, $id, $locationId, $startDate, $endDate);
        return $this->processDetails($result);
    }

    /**
     * Check if a consortium is found in organisation info and return basic information (provider-specific part)
     *
     * @param string $language Language
     * @param string $id       Parent organisation ID
     *
     * @return array Associative array with 'id', 'logo' and 'name', or empty array if not found
     */
    abstract protected function doLookup(string $language, string $id): array;

    /**
     * Get consortium information (includes list of locations) (provider-specific part)
     *
     * @param string $language       Language
     * @param string $id             Parent organisation ID
     * @param array  $locationFilter Optional list of locations to include
     *
     * @return array
     */
    abstract protected function doGetConsortiumInfo(string $language, string $id, array $locationFilter = []): array;

    /**
     * Get location details (provider-specific part)
     *
     * @param string  $language   Language
     * @param string  $id         Parent organisation ID
     * @param string  $locationId Location ID
     * @param ?string $startDate  Start date (YYYY-MM-DD) of opening times (default is Monday of current week)
     * @param ?string $endDate    End date (YYYY-MM-DD) of opening times (default is Sunday of start date week)
     *
     * @return array
     */
    abstract protected function doGetDetails(
        string $language,
        string $id,
        string $locationId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array;

    /**
     * Fetch JSON data as an array from cache or external API
     *
     * @param string $url URL
     *
     * @return ?array Data or null on failure
     */
    protected function fetchJson(string $url): ?array
    {
        $cacheDir = $this->cacheManager->getCache('organisation-info')
            ->getOptions()->getCacheDir();

        $localFile = "$cacheDir/" . md5($url) . '.json';

        $response = null;
        if ($maxAge = $this->config->General->cachetime ?? 10) {
            if (
                is_readable($localFile)
                && time() - filemtime($localFile) < $maxAge * 60
            ) {
                $response = file_get_contents($localFile);
            }
        }
        if (!$response) {
            $client = $this->httpService->createClient(
                $url,
                \Laminas\Http\Request::METHOD_GET,
                $this->config->General->timeout ?? 20
            );
            $client->setOptions(['useragent' => 'VuFind']);
            $result = $client->send();
            if (!$result->isSuccess() || $result->getStatusCode() !== 200) {
                $this->logError(
                    'Error querying organisation info: '
                    . $result->getStatusCode() . ': ' . $result->getReasonPhrase()
                    . ", url: $url"
                );
                return null;
            }

            $response = $result->getBody();
            if ($maxAge) {
                file_put_contents($localFile, $response);
            }
        }

        if (!$response) {
            $this->logError("Response empty (url: $url)");
            return null;
        }

        try {
            return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            $this->logError('Error decoding JSON: ' . (string)$e . " (url: $url)");
            return null;
        }
    }

    /**
     * Proxify an image url for loading via the OrganisationInfo controller
     *
     * @param string $url Image URL
     *
     * @return string
     */
    protected function proxifyImageUrl(string $url): string
    {
        // Ensure that we don't proxify an empty or already proxified URL:
        if (!$url) {
            return '';
        }
        $check = $this->urlPlugin->fromRoute('organisation-info-image');
        if (strncasecmp($url, $check, strlen($check)) === 0) {
            return $url;
        }

        return $this->urlPlugin->fromRoute(
            'organisation-info-image',
            [],
            [
                'query' => [
                    'image' => $url,
                ],
            ]
        );
    }

    /**
     * Return a date and time string in RFC 3339 format.
     *
     * @param \DateTime $date Date
     * @param string    $time Time as string H:i
     *
     * @return Unix timestamp
     */
    protected function parseDateTime(\DateTime $date, string $time): int
    {
        $timePart = $this->dateConverter->convertToDateTime('H:i', $time);
        $date->setTime($timePart->format('H'), $timePart->format('i'), 0);
        return $date->getTimestamp();
    }

    /**
     * Process response data and extract various fields
     *
     * @param array $result Result
     *
     * @return array
     */
    protected function processDetails(array $result): array
    {
        if (!$result) {
            return $result;
        }
        $isAlwaysClosed = true;
        $hasSelfServiceTimes = false;
        // empty() needed because we can't use null coalescing without breaking the reference:
        if (!empty($result['openTimes']['schedules'])) {
            foreach ($result['openTimes']['schedules'] as &$schedule) {
                if (!$schedule['closed']) {
                    $isAlwaysClosed = false;
                }

                $firstOpeningDateTime = null;
                $lastClosingDateTime = null;
                $selfServiceTimes = [];
                $staffTimes = [];
                $gaps = [];
                $minutePrecision = false;

                foreach ($schedule['times'] ?? [] as $time) {
                    if ($time['closed'] ?? false) {
                        $gaps[] = $time;
                    } else {
                        if (null === $firstOpeningDateTime || $time['opens'] < $firstOpeningDateTime) {
                            $firstOpeningDateTime = $time['opens'];
                        }
                        if (null === $lastClosingDateTime || $time['closes'] > $lastClosingDateTime) {
                            $lastClosingDateTime = $time['closes'];
                        }
                        if ($time['selfservice']) {
                            $selfServiceTimes[] = $time;
                            $hasSelfServiceTimes = true;
                        } else {
                            $staffTimes[] = $time;
                        }
                        if (date('i', $time['opens']) !== '00' || date('i', $time['closes']) !== '00') {
                            $minutePrecision = true;
                        }
                    }
                }
                $schedule['firstOpeningTime'] = $firstOpeningDateTime;
                $schedule['lastClosingTime'] = $lastClosingDateTime;
                $schedule['gaps'] = $gaps;
                $schedule['selfServiceTimes'] = $selfServiceTimes;
                $schedule['staffTimes'] = $staffTimes;
                $schedule['closed'] = !$selfServiceTimes && !$staffTimes;
                $schedule['minutePrecision'] = $minutePrecision;
            }
        }
        $result['isAlwaysClosed'] = $isAlwaysClosed;
        $result['hasSelfServiceTimes'] = $hasSelfServiceTimes;

        if (!empty($result['address'])) {
            $address = $result['address'];
            $displayAddress = $address['street'] ?? '';
            if ($zip = $address['zipcode'] ?? null) {
                $displayAddress .= ", $zip";
            }
            if ($city = $address['city'] ?? null) {
                $displayAddress .= " $city";
            }
            $result['address']['displayAddress'] = $displayAddress;
        }

        if (isset($result['pictures'])) {
            foreach ($result['pictures'] as &$picture) {
                $picture['url'] = $this->proxifyImageUrl($picture['url']);
            }
            // Unset reference:
            unset($picture);
        }

        // Make sure we have all default elements:
        $result = array_merge($this->detailsTemplate, $result);

        return $result;
    }

    /**
     * Enrich organisation details.
     *
     * @param string $language   Language
     * @param string $id         Parent organisation ID
     * @param int    $locationId Location ID
     * @param array  $response   JSON array
     * @param array  $result     Results
     * @param string $enrichment Enrichment setting
     *
     * @return void
     */
    protected function enrich(
        string $language,
        string $id,
        string $locationId,
        array $response,
        array &$result,
        string $enrichment
    ): void {
        $parts = explode(':', $enrichment);
        switch ($parts[0]) {
            case 'TPRAccessibility':
                $this->enrichTPRAccessibility(
                    $language,
                    $id,
                    $locationId,
                    $response,
                    $parts[1] ?? '',
                    $result
                );
                break;
            default:
                throw new \Exception("Unknown enrichment: $enrichment");
                break;
        }
    }

    /**
     * Enrich organisation details with accessibility information from TPR
     * Palvelukuvausrekisteri
     *
     * @param string $language      Language
     * @param string $id            Parent organisation ID
     * @param string $locationId    Location ID
     * @param array  $response      JSON array
     * @param string $configSection Configuration section to use
     * @param array  $result        Results
     *
     * @return void
     */
    protected function enrichTPRAccessibility(
        string $language,
        string $id,
        string $locationId,
        array $response,
        string $configSection,
        array &$result
    ): void {
        if (!$configSection || !($baseUrl = $this->config->$configSection->url)) {
            throw new \Exception("Setting [$configSection] / url missing");
        }

        $customDataId = null;
        foreach ($response['customData'] ?? [] as $data) {
            if ('esteettömyys' === $data['id']) {
                $customDataId = $data['value'];
                break;
            }
        }
        if (null === $customDataId) {
            return;
        }
        $url = "$baseUrl/v4/unit/" . rawurlencode($customDataId);

        try {
            if (!($json = $this->fetchJson($url))) {
                return;
            }
            $headingKey = "sentence_group_$language";
            $sentenceKey = "sentence_$language";

            $accessibility = [];
            foreach ($json['accessibility_sentences'] ?? [] as $sentence) {
                if (
                    !($heading = $sentence[$headingKey] ?? '')
                    || !($sentence = $sentence[$sentenceKey] ?? '')
                ) {
                    continue;
                }
                if (!isset($accessibility[$heading])) {
                    $accessibility[$heading] = [
                        'heading' => $this->translate(['OrganisationInfo', $heading]),
                        'statements' => [
                            $sentence,
                        ],
                    ];
                } else {
                    $accessibility[$heading]['statements'][] = $sentence;
                }
            }
            $result['accessibilityInfo'] = array_values($accessibility);
        } catch (\Exception $e) {
            $this->logError("TPR request '$url' failed: " . (string)$e);
            $result['accessibilityInfo'] = [
                [
                    'heading' => $this->translate('An error has occurred'),
                    'statements' => [],
                ],
            ];
        }
    }
}
