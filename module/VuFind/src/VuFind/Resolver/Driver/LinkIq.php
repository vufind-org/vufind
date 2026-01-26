<?php

/**
 * EBSCO LinkIQ Resolver Driver
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Resolver_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */

namespace VuFind\Resolver\Driver;

use VuFind\Date\Converter as DateConverter;
use VuFind\Http\GuzzleService;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

/**
 * EBSCO LinkIQ Resolver Driver
 *
 * @category VuFind
 * @package  Resolver_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */
class LinkIq extends AbstractBase implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Service type map.
     *
     * @var array
     */
    protected array $serviceTypeMap = [
        'FullText' => 'getFullTxt',
        'LibraryCatalog' => 'getHolding',
        'DocumentDelivery' => 'getWebService',
        'AbstractIndexDatabases' => 'getWebService',
        'ILL' => 'getWebService',
        'SearchEngines' => 'getWebService',
        'Other' => 'getWebService',
        'NoveListBIR' => 'getWebService',
        'SmartLinks' => 'getWebService',
        'SectionLabel' => null,
    ];

    /**
     * Constructor
     *
     * @param string        $baseUrl            Base URL for link resolver
     * @param GuzzleService $httpService        HTTP Service
     * @param DateConverter $dateConverter      Date converter
     * @param string        $password           LinkIQ password
     * @param ?string       $moreOptionsBaseUrl Base URL for more options link, or null if disabled
     */
    public function __construct(
        string $baseUrl,
        protected GuzzleService $httpService,
        protected DateConverter $dateConverter,
        protected string $password,
        protected ?string $moreOptionsBaseUrl,
    ) {
        parent::__construct($baseUrl);
    }

    /**
     * Get Resolver Url for more options link
     *
     * Transform the OpenURL as needed to get a working link to the resolver.
     *
     * @param string $openURL openURL (url-encoded)
     *
     * @return string Returns resolver specific url
     */
    public function getResolverUrlForMoreOptions(string $openURL): string
    {
        if (!$this->supportsMoreOptionsLink()) {
            throw new \Exception('More options URL unavailable');
        }
        return $this->moreOptionsBaseUrl . (str_contains($this->moreOptionsBaseUrl, '?') ? '&' : '?')
            . $openURL;
    }

    /**
     * This controls whether a "More options" link will be shown below the fetched
     * resolver links eventually linking to the resolver page previously being
     * parsed.
     *
     * @return bool
     */
    public function supportsMoreOptionsLink()
    {
        return (bool)$this->moreOptionsBaseUrl;
    }

    /**
     * Fetch Links
     *
     * Fetches a set of links corresponding to an OpenURL
     *
     * @param string $openURL openURL (url-encoded)
     *
     * @return string raw response returned by resolver
     */
    public function fetchLinks($openURL)
    {
        // Make the call to resolver and load results
        $url = $this->getResolverUrl($openURL);
        $response = $this->httpService->get($url, headers: ['password' => $this->password]);
        return $response->getBody()->getContents();
    }

    /**
     * Parse Links
     *
     * Parses a JSON response returned by a link resolver
     * and converts it to a standardised format for display
     *
     * @param string $response Raw JSON returned by resolver
     *
     * @return array Array of values
     */
    public function parseLinks($response)
    {
        try {
            $json = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            return [];
        }

        $results = [];
        foreach ($json['contextObjects'] ?? [] as $contextObject) {
            foreach ($contextObject['targetLinks'] ?? [] as $link) {
                if ($result = $this->parseTargetLink($link)) {
                    $results[] = $result;
                }
            }
        }

        return $results;
    }

    /**
     * Get link text.
     *
     * @param array $link Link
     *
     * @return ?array
     */
    protected function parseTargetLink(array $link): ?array
    {
        if (!($serviceType = $this->getServiceType($link))) {
            return null;
        }
        return [
            'title' => $this->getLinkText($link),
            'href' => $this->getLinkUrl($link),
            'service_type' => $serviceType,
            'coverage' => $this->getCoverage($link),
            'embargo' => $this->getEmbargo($link),
        ];
    }

    /**
     * Get VuFind link category for a LinkIQ link.
     *
     * @param array $link Link
     *
     * @return ?string Link category, or null to skip the link
     */
    protected function getServiceType(array $link): ?string
    {
        return $this->serviceTypeMap[$link['category']] ?? null;
    }

    /**
     * Get link text.
     *
     * @param array $link Link
     *
     * @return string
     */
    protected function getLinkText(array $link): string
    {
        return $link['linkText'] ?? '';
    }

    /**
     * Get link URL.
     *
     * @param array $link Link
     *
     * @return string
     */
    protected function getLinkUrl(array $link): string
    {
        return $link['targetUrl'] ?? '';
    }

    /**
     * Get coverage description for a link.
     *
     * @param array $link Link
     *
     * @return string
     */
    protected function getCoverage(array $link): string
    {
        $allDateRanges = [];
        foreach ($link['packageInfo']['coverage']['coverageDates'] ?? [] as $dates) {
            $start = $dates['coverageBegin'] ?? '';
            $end = $dates['coverageEnd'] ?? '';
            if (str_starts_with($end, '9999')) {
                $end = '';
            }
            if ($start || $end) {
                $tokens = [
                    '%%startDate%%' => $start
                        ? $this->dateConverter->convertToDisplayDate('Ymd', $start)
                        : '',
                    '%%endDate%%' => $end
                        ? $this->dateConverter->convertToDisplayDate('Ymd', $end)
                        : '',
                ];
                $allDateRanges[] = $this->translate('openurl_coverage_daterange', $tokens);
            }
        }
        $coverageDates = implode($this->translate('openurl_coverage_daterange_joiner', default: ', '), $allDateRanges);
        $statement = trim($link['packageInfo']['coverage']['coverageStatement'] ?? '');

        return $statement
            ? $this->translate(
                'openurl_coverage_dateranges_statement',
                ['%%dateranges%%' => $coverageDates, '%%statement%%' => $statement]
            ) : $this->translate(
                'openurl_coverage_dateranges_only',
                ['%%dateranges%%' => $coverageDates]
            );
    }

    /**
     * Get embargo description for a link.
     *
     * @param array $link Link
     *
     * @return string
     */
    protected function getEmbargo(array $link): string
    {
        if (!($embargo = $link['packageInfo']['coverage']['embargoValue'] ?? null)) {
            return '';
        }
        $tokens = [
            'value' => $embargo,
            'unit' => $link['packageInfo']['coverage']['embargoUnitType'] ?? '',
        ];
        return $this->translate('openurl_embargo_statement', $tokens, useIcuFormatter: true);
    }
}
