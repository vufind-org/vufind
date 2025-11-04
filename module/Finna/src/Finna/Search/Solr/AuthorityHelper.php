<?php

/**
 * Helper for Authority recommendations.
 *
 * PHP version 8
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

use VuFind\RecordDriver\DefaultRecord;

use function in_array;
use function is_string;

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
    public const AUTHOR2_ID_FACET = 'author2_id_str_mv';

    /**
     * Index field for author id-role combinations
     *
     * @var string
     */
    public const AUTHOR_ID_ROLE_FACET = 'author2_id_role_str_mv';

    /**
     * Index field for topic ids.
     *
     * @var string
     */
    public const TOPIC_ID_FACET = 'topic_id_str_mv';

    /**
     * Index field for place ids.
     *
     * @var string
     */
    public const GEOGRAPHIC_ID_FACET = 'geographic_id_str_mv';

    /**
     * Delimiter used to separate author id and role.
     *
     * @var string
     */
    public const AUTHOR_ID_ROLE_SEPARATOR = '###';

    /**
     * Authority link type: authority page.
     *
     * @var string
     */
    public const LINK_TYPE_PAGE = 'page';

    /**
     * Authority link type: search results filtered by authority id.
     *
     * @var string
     */
    public const LINK_TYPE_SEARCH = 'search';

    /**
     * Authority link type: search results filtered by topic_id_tr_mv (either
     * an internal authority index id or external URI (YSO etc)).
     *
     * @var string
     */
    public const LINK_TYPE_SEARCH_SUBJECT = 'search-subject';

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
     * @var \VuFind\Config\Config|null
     */
    protected $authorityConfig;

    /**
     * Authority search config
     *
     * @var \VuFind\Config\Config
     */
    protected $authoritySearchConfig;

    /**
     * Constructor
     *
     * @param \VuFind\Record\Loader              $recordLoader          Record loader
     * @param \VuFind\Search\SearchRunner        $searchRunner          Search runner
     * @param \VuFind\View\Helper\Root\Translate $translator            Translator
     * view helper
     * @param \VuFind\Config\Config              $config                Config
     *                                                                  config
     * @param \VuFind\Config\Config              $authoritySearchConfig Authority
     *                                                                  search
     *                                                                  config
     */
    public function __construct(
        \VuFind\Record\Loader $recordLoader,
        \VuFind\Search\SearchRunner $searchRunner,
        \VuFind\View\Helper\Root\Translate $translator,
        \VuFind\Config\Config $config,
        \VuFind\Config\Config $authoritySearchConfig
    ) {
        $this->recordLoader = $recordLoader;
        $this->searchRunner = $searchRunner;
        $this->translator = $translator;
        $this->authorityConfig = $config->Authority ?? null;
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
                [$id, $role] = $this->extractRole($facet['displayText']);
                $authIds[$field][] = $id;
            }
        }
        foreach ($this->getAuthorIdFacets() as $field) {
            $facetList = $facetSet[$field]['list'] ?? [];
            $ids = $authIds[$field] ?? [];
            $records
                = $this->recordLoader->loadBatchForSource($ids, 'SolrAuth', true);
            foreach ($facetList as &$facet) {
                [$id, $role] = $this->extractRole($facet['displayText']);
                foreach ($records as $record) {
                    if ($record->getUniqueId() === $id) {
                        [$displayText, $role]
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
            AuthorityHelper::TOPIC_ID_FACET,
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
        [$id, $role] = $this->extractRole($value);
        $record = $this->recordLoader->load($id, 'SolrAuth', true);
        [$displayText, $role] = $this->formatDisplayText($record, $role);
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
        if (str_contains($value, $separator)) {
            [$id, $role] = explode($separator, $value, 2);
        }
        return [$id, $role];
    }

    /**
     * Return biblio records that are linked to an authority
     *
     * @param string $id        Authority id(s)
     * @param string $field     Solr field to search by (author, topic)
     * @param bool   $onlyCount Return only record count (does not fetch record data from index)
     *
     * @return \VuFind\Search\Results|int
     */
    public function getRecordsByAuthorityId(
        string $id,
        string $field = AuthorityHelper::AUTHOR2_ID_FACET,
        bool $onlyCount = false
    ) {
        $query = $this->getRecordsByAuthorityQuery($id, $field);
        $results = $this->searchRunner->run(
            [],
            'Solr',
            function ($runner, $params, $searchId) use ($onlyCount, $query): void {
                $params->setLimit($onlyCount ? 0 : 100);
                $params->setPage(1);
                $params->addFilter($query);
                $options = $params->getOptions();
                $options->disableHighlighting();
                $options->spellcheckEnabled(false);
            }
        );
        return $onlyCount ? $results->getResultTotal() : $results;
    }

    /**
     * Return identifiers for an authority record
     *
     * @param DefaultRecord $record Authority record
     *
     * @return array
     */
    public function getIdentifiersForAuthority(DefaultRecord $record)
    {
        $ids = [$record->getUniqueID()];
        foreach ($record->tryMethod('getOtherIdentifiers', [], []) as $id) {
            if (preg_match('/^https?:/', $id['data'])) {
                // Never prefix http(s) url's
                $ids[] = $id['data'];
            } else {
                $ids[] = '(' . $id['detail'] . ')' . $id['data'];
            }
        }
        return $ids;
    }

    /**
     * Return query for fetching biblio records by authority id.
     *
     * @param string|array $id    Authority id
     * @param string       $field Solr field to search by (author, topic)
     *
     * @return string
     */
    public function getRecordsByAuthorityQuery($id, $field)
    {
        $escapeAndQuote = function ($s): string {
            return '"' . addcslashes($s, '"') . '"';
        };

        if (is_string($id)) {
            return "$field:" . $escapeAndQuote($id);
        }
        $ids = array_map($escapeAndQuote, $id);
        return "$field:(" . implode(' OR ', $ids) . ')';
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
            $setting = self::LINK_TYPE_SEARCH;
        }
        return
            in_array(
                $setting,
                [
                    self::LINK_TYPE_PAGE, self::LINK_TYPE_SEARCH,
                    self::LINK_TYPE_SEARCH_SUBJECT,
                ]
            )
            ? $setting : null;
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
        $displayText = $record instanceof \VuFind\RecordDriver\Missing
            ? $this->translator->translate('not_applicable')
            : $record->getTitle();
        if ($role) {
            $role = mb_strtolower(
                $this->translator->translate("CreatorRoles::$role")
            );
            $displayText .= " ($role)";
        }
        return [$displayText, $role];
    }
}
