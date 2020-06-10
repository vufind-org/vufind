<?php
/**
 * Helper for Authority recommendations.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Solr;

/**
 * Helper for Authority recommendations.
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class AuthorityHelper
{
    /**
     * Index field for author2-ids.
     *
     * @var string
     */
    const AUTHOR2_ID_FACET = 'author2_id_str_mv';

    /**
     * Index field for author id-role combinations
     *
     * @var string
     */
    const AUTHOR_ID_ROLE_FACET = 'author2_id_role_str_mv';

    /**
     * Index field for author2-ids.
     *
     * @var string
     */
    const TOPIC_ID_FACET = 'topic_id_str_mv';

    /**
     * Delimiter used to separate author id and role.
     *
     * @var string
     */
    const AUTHOR_ID_ROLE_SEPARATOR = '###';

    /**
     * Record loader
     *
     * @var \VuFind\Record\Loader
     */
    protected $recordLoader;

    /**
     * Search runner
     *
     * @var \VuFind\Search\SearchRunner
     */
    protected $searchRunner;

    /**
     * Translator
     *
     * @var \VuFind\Translator
     */
    protected $translator;

    /**
     * Authority config
     *
     * @var \Zend\Config\Config
     */
    protected $authorityConfig;

    /**
     * Authority search config
     *
     * @var \Zend\Config\Config
     */
    protected $authoritySearchConfig;

    /**
     * Constructor
     *
     * @param \VuFind\Record\Loader              $recordLoader          Record loader
     * @param \VuFind\Search\SearchRunner        $searchRunner          Search runner
     * @param \VuFind\View\Helper\Root\Translate $translator            Translator
     * view helper
     * @param \Zend\Config\Config                $authorityConfig       Authority
     * config
     * @param \Zend\Config\Config                $authoritySearchConfig Authority
     * search config
     */
    public function __construct(
        \VuFind\Record\Loader $recordLoader,
        \VuFind\Search\SearchRunner $searchRunner,
        \VuFind\View\Helper\Root\Translate $translator,
        \Zend\Config\Config $authorityConfig,
        \Zend\Config\Config $authoritySearchConfig
    ) {
        $this->recordLoader = $recordLoader;
        $this->searchRunner = $searchRunner;
        $this->translator = $translator;
        $this->authorityConfig = $authorityConfig;
        $this->authoritySearchConfig = $authoritySearchConfig;
    }

    /**
     * Format displayTexts of a facet set.
     *
     * @param array $facetSet Facet set
     *
     * @return array
     */
    public function formatFacetSet($facetSet)
    {
        foreach ($this->getAuthorIdFacets() as $field) {
            if (isset($facetSet[$field])) {
                return $this->processFacets($facetSet);
            }
        }
        return $facetSet;
    }

    /**
     * Format displayTexts of a facet list.
     *
     * @param string $field  Facet field
     * @param array  $facets Facets
     *
     * @return array
     */
    public function formatFacetList($field, $facets)
    {
        if (!in_array($field, $this->getAuthorIdFacets())) {
            return $facets;
        }
        $result = $this->processFacets([$field => ['list' => $facets]]);
        return $result[$field]['list'];
    }

    /**
     * Helper function for processing a facet set.
     *
     * @param array $facetSet Facet set
     *
     * @return array
     */
    protected function processFacets($facetSet)
    {
        $authIds = [];
        foreach ($this->getAuthorIdFacets() as $field) {
            $facetList = $facetSet[$field]['list'] ?? [];
            $authIds[$field] = [];
            foreach ($facetList as $facet) {
                list($id, $role) = $this->extractRole($facet['displayText']);
                $authIds[$field][] = $id;
            }
        }
        foreach ($this->getAuthorIdFacets() as $field) {
            $facetList = $facetSet[$field]['list'] ?? [];
            $ids = $authIds[$field] ?? [];
            $records
                = $this->recordLoader->loadBatchForSource($ids, 'SolrAuth', true);
            foreach ($facetList as &$facet) {
                list($id, $role) = $this->extractRole($facet['displayText']);
                foreach ($records as $record) {
                    if ($record->getUniqueId() === $id) {
                        list($displayText, $role)
                            = $this->formatDisplayText($record, $role);
                        $facet['displayText'] = $displayText;
                        $facet['role'] = $role;
                        continue;
                    }
                }
            }
            $facetSet[$field]['list'] = $facetList;
        }
        return $facetSet;
    }

    /**
     * Return index fields that are used in authority searches.
     *
     * @return array
     */
    public function getAuthorIdFacets()
    {
        return [
            AuthorityHelper::AUTHOR_ID_ROLE_FACET,
            AuthorityHelper::AUTHOR2_ID_FACET,
            AuthorityHelper::TOPIC_ID_FACET
        ];
    }

    /**
     * Format facet value (display text).
     *
     * @param string  $value        Facet value
     * @param boolean $extendedInfo Wheter to return an array with
     * 'id', 'displayText' and 'role' fields.
     *
     * @return mixed string|array
     */
    public function formatFacet($value, $extendedInfo = false)
    {
        $id = $value;
        $role = null;
        list($id, $role) = $this->extractRole($value);
        $record = $this->recordLoader->load($id, 'SolrAuth', true);
        list($displayText, $role) = $this->formatDisplayText($record, $role);
        return $extendedInfo
            ? ['id' => $id, 'displayText' => $displayText, 'role' => $role]
            : $displayText;
    }

    /**
     * Parse authority id and role.
     *
     * @param string $value Authority id-role
     *
     * @return array
     */
    public function extractRole($value)
    {
        $id = $value;
        $role = null;
        $separator = self::AUTHOR_ID_ROLE_SEPARATOR;
        if (strpos($value, $separator) !== false) {
            list($id, $role) = explode($separator, $value, 2);
        }
        return [$id, $role];
    }

    /**
     * Return biblio records that are linked to author.
     *
     * @param string $id        Authority id
     * @param string $field     Solr field to search by (author, topic)
     * @param bool   $onlyCount Return only record count
     * (does not fetch record data from index)
     *
     * @return \VuFind\Search\Results|int
     */
    public function getRecordsByAuthorityId(
        $id, $field = AUTHOR2_ID_FACET, $onlyCount = false
    ) {
        $query = $this->getRecordsByAuthorityQuery($id, $field);
        $results = $this->searchRunner->run(
            ['lookfor' => $query, 'fl' => 'id'],
            'Solr',
            function ($runner, $params, $searchId) use ($onlyCount) {
                $params->setLimit($onlyCount ? 0 : 100);
                $params->setPage(1);
            }
        );
        return $onlyCount ? $results->getResultTotal() : $results;
    }

    /**
     * Return query for fetching biblio records by authority id.
     *
     * @param string $id    Authority id
     * @param string $field Solr field to search by (author, topic)
     *
     * @return string
     */
    public function getRecordsByAuthorityQuery($id, $field)
    {
        return "$field:\"$id\"";
    }

    /**
     * Check if authority search is enabled.
     *
     * @return bool
     */
    public function isAuthoritySearchEnabled()
    {
        return $this->authoritySearchConfig->General->enabled ?? false;
    }

    /**
     * Get authority link type.
     *
     * @param string $type authority type
     *
     * @return Link type (page or search) or null when authority links are disabled.
     */
    public function getAuthorityLinkType($type = 'author')
    {
        $setting = $this->authorityConfig->authority_links ?? null;
        $setting = $setting[$type] ?? $setting['*'] ?? $setting;
        if ($setting === '1') {
            // Backward compatibility
            $setting = 'search';
        }
        return in_array($setting, ['page', 'search']) ? $setting : null;
    }

    /**
     * Helper function for formatting author-role display text.
     *
     * @param \Finna\RecordDriver\SolrDefault $record Record driver
     * @param string                          $role   Author role
     *
     * @return string
     */
    protected function formatDisplayText($record, $role = null)
    {
        $displayText = $record->getTitle();
        if ($role) {
            $role = mb_strtolower(
                $this->translator->translate("CreatorRoles::$role")
            );
            $displayText .= " ($role)";
        }
        return [$displayText, $role];
    }
}
