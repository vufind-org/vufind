<?php

/**
 * Service for querying organisation info databases.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2023.
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
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\OrganisationInfo;

use Finna\OrganisationInfo\Provider\Kirkanta;
use Finna\OrganisationInfo\Provider\MuseotFi;
use Finna\OrganisationInfo\Provider\ProviderInterface;
use Finna\Search\Solr\HierarchicalFacetHelper;
use VuFind\Search\Results\PluginManager;

use function in_array;
use function is_array;

/**
 * Service for querying organisation info databases.
 *
 * @category VuFind
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class OrganisationInfo implements
    \Psr\Log\LoggerAwareInterface,
    \VuFind\I18n\HasSorterInterface,
    \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\HasSorterTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Organisation info configuration
     *
     * @var VuFind\Config\Config
     */
    protected $config = null;

    /**
     * Cache manager
     *
     * @var \VuFind\CacheManager
     */
    protected $cacheManager;

    /**
     * Language (use getLanguage())
     *
     * @var string
     */
    protected $language = null;

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
     * Kirkanta provider
     *
     * @var Kirkanta
     */
    protected $kirkanta;

    /**
     * MuseotFi provider
     *
     * @var MuseotFi
     */
    protected $museotFi;

    /**
     * Constructor.
     *
     * @param \VuFind\Config\Config   $config         Organisation info configuration
     * @param \VuFind\Cache\Manager   $cacheManager   Cache manager
     * @param PluginManager           $resultsManager Results manager
     * @param HierarchicalFacetHelper $facetHelper    Hierarchical facet helper
     * @param Kirkanta                $kirkanta       Kirkanta provider
     * @param MuseotFi                $museotFi       MuseotFi provider
     */
    public function __construct(
        \VuFind\Config\Config $config,
        \VuFind\Cache\Manager $cacheManager,
        PluginManager $resultsManager,
        HierarchicalFacetHelper $facetHelper,
        Kirkanta $kirkanta,
        MuseotFi $museotFi,
    ) {
        $this->config = $config;
        $this->cacheManager = $cacheManager;
        $this->resultsManager = $resultsManager;
        $this->facetHelper = $facetHelper;
        $this->kirkanta = $kirkanta;
        $this->museotFi = $museotFi;
    }

    /**
     * Check if organisation info is enabled
     *
     * @return bool
     */
    public function isAvailable()
    {
        return !empty($this->config->General->enabled);
    }

    /**
     * Check if a consortium is found in organisation info and return basic information
     *
     * @param array  $sectors Sectors if known, empty array otherwise
     * @param string $id      Parent organisation ID
     *
     * @return array
     */
    public function lookup(array $sectors, string $id): array
    {
        return $this->getProvider($sectors, $id)->lookup($this->getLanguage(), $id);
    }

    /**
     * Get consortium information (includes list of locations)
     *
     * @param array  $sectors        Sectors if known, empty array otherwise
     * @param string $id             Parent organisation ID
     * @param array  $locationFilter Optional list of locations to include
     *
     * @return array
     */
    public function getConsortiumInfo(array $sectors, string $id, array $locationFilter = []): array
    {
        return $this->getProvider($sectors, $id)->getConsortiumInfo($this->getLanguage(), $id, $locationFilter);
    }

    /**
     * Get location details
     *
     * @param array   $sectors    Sectors if known, empty array otherwise
     * @param string  $id         Parent organisation ID
     * @param string  $locationId Location ID
     * @param ?string $startDate  Start date (YYYY-MM-DD) of opening times (default is Monday of current week)
     * @param ?string $endDate    End date (YYYY-MM-DD) of opening times (default is Sunday of current week)
     *
     * @return array
     */
    public function getDetails(
        array $sectors,
        string $id,
        string $locationId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        return $this->getProvider($sectors, $id)
            ->getDetails($this->getLanguage(), $id, $locationId, $startDate, $endDate);
    }

    /**
     * Get all the sectors for an organisation.
     *
     * @param string $id Organisation ID
     *
     * @return array
     */
    public function getSectorsForOrganisation(string $id): array
    {
        $result = [];
        $id = mb_strtolower($id, 'UTF-8');
        foreach ($this->getOrganisationsList() as $organisations) {
            foreach ($organisations as $organisation) {
                if (!($sector = $organisation['sector'] ?? '')) {
                    continue;
                }
                $orgId = mb_strtolower(
                    $organisation['organisation'] ?? '',
                    'UTF-8'
                );
                if ($orgId === $id) {
                    $result[] = $sector;
                }
            }
        }
        // Assume lib if we didn't find any sources in the index:
        return $result ?: ['lib'];
    }

    /**
     * Get a list of current organisations.
     *
     * @return array
     */
    public function getOrganisationsList(): array
    {
        $cacheDir = $this->cacheManager->getCache('organisation-info')->getOptions()->getCacheDir();
        $locale = $this->getLanguage();
        $cacheFile = "$cacheDir/organisations_list_$locale.json";
        $maxAge = (int)($this->config['General']['organisationListCacheTime'] ?? 60);
        $sorter = $this->getSorter();
        $list = [];
        if (
            is_readable($cacheFile)
            && time() - filemtime($cacheFile) < $maxAge * 60
        ) {
            return json_decode(file_get_contents($cacheFile), true);
        } else {
            $emptyResults = $this->resultsManager->get('EmptySet');
            try {
                $sectorFacets = $this->getFacetList('sector_str_mv');
                $sectorFacets = $this->facetHelper->flattenFacetHierarchy($sectorFacets);
                foreach ($sectorFacets as $sectorFacet) {
                    $sectorParts = explode('/', $sectorFacet['value']);
                    $sectorParts = array_splice($sectorParts, 1, -1);
                    $sector = implode('/', $sectorParts);
                    $list[$sector] = [];

                    $collection = $this->getFacetList(
                        'building',
                        '0/',
                        'sector_str_mv:' . $sectorFacet['value']
                    );

                    foreach ($collection as $item) {
                        $link = $emptyResults->getUrlQuery()
                            ->addFacet('building', $item['value'])->getParams();
                        $displayText = $item['displayText'];
                        if ($displayText == $item['value']) {
                            $displayText = $this->facetHelper
                                ->formatDisplayText($displayText)
                                ->getDisplayString();
                        }
                        $organisationInfoId = $this->getOrganisationInfoId($item['value']);

                        $list[$sector][] = [
                            'name' => $displayText,
                            'link' => $link,
                            'organisation' => $organisationInfoId,
                            'sector' => $sector,
                        ];
                    }
                    usort(
                        $list[$sector],
                        function ($a, $b) use ($sorter) {
                            return $sorter->compare($a['name'], $b['name']);
                        }
                    );
                }
                $cacheJson = json_encode($list);
                file_put_contents($cacheFile, $cacheJson);
                return $list;
            } catch (\VuFindSearch\Backend\Exception\BackendException $e) {
                $this->logError(
                    'Error creating organisations list: ' . $e->getMessage()
                );
                throw $e;
            }
        }
        return [];
    }

    /**
     * Convert building code to Kirjastohakemisto finna_id
     *
     * @param string|array $building Building
     *
     * @return string|null ID or null if not found
     */
    public function getOrganisationInfoId($building)
    {
        if (is_array($building)) {
            $building = $building[0];
        }

        if (preg_match('/^0\/([^\/]*)\/$/', $building, $matches)) {
            // strip leading '0/' and trailing '/' from top-level building code
            return $matches[1];
        }
        return null;
    }

    /**
     * Get facet data from a field
     *
     * @param string $field  Field to return
     * @param string $prefix Optional facet prefix limiter
     * @param string $filter Optional filter
     *
     * @return array
     */
    protected function getFacetList(
        string $field,
        string $prefix = '',
        string $filter = ''
    ): array {
        $results = $this->resultsManager->get('Solr');
        $params = $results->getParams();
        // Disable deduplication so that facet results are not affected:
        $params->addFilter('finna.deduplication:"0"');
        $params->setLimit(0);
        $params->setFacetLimit(-1);
        if ('' !== $prefix) {
            $params->setFacetPrefix($prefix);
        }
        $options = $params->getOptions();
        $options->disableHighlighting();
        $options->spellcheckEnabled(false);

        $params->addFacet($field, $field, false);
        if ('' !== $filter) {
            $params->addFilter($filter);
        }
        $facetList = $results->getFacetList();
        return $facetList[$field]['list'] ?? [];
    }

    /**
     * Get the active language to use in a request
     *
     * @return string
     */
    protected function getLanguage()
    {
        if (null === $this->language) {
            $allLanguages = isset($this->config->General->languages)
                ? $this->config->General->languages->toArray() : [];

            $language = $this->config->General->language
                ?? $this->getTranslatorLocale();

            $this->language = $this->validateLanguage($language, $allLanguages);
        }
        return $this->language;
    }

    /**
     * Validate language
     *
     * @param string $language     Language version
     * @param array  $allLanguages List of valid languages
     *
     * @return string Language version
     */
    protected function validateLanguage($language, $allLanguages)
    {
        $map = ['en-gb' => 'en'];
        if (isset($map[$language])) {
            $language = $map[$language];
        }

        if (!in_array($language, $allLanguages)) {
            $language = $this->config->General->fallbackLanguage ?? 'fi';
        }

        return $language;
    }

    /**
     * Get organisation info provider based on sector information
     *
     * @param array  $sectors Sectors for the organisation
     * @param string $id      Parent organisation ID
     *
     * @return ProviderInterface;
     */
    protected function getProvider(array $sectors, string $id): ProviderInterface
    {
        if (!$sectors) {
            $sectors = $this->getSectorsForOrganisation($id);
        }
        return in_array('mus', $sectors) ? $this->museotFi : $this->kirkanta;
    }
}
