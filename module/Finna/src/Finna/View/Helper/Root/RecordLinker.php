<?php

/**
 * RecordLinker view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2017-2021.
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
 * @package  View_Helpers
 * @author   Anna Niku <anna.niku@gofore.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use Finna\Search\UrlQueryHelper;
use VuFind\Search\Memory;

use function sprintf;

/**
 * RecordLinker view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Anna Niku <anna.niku@gofore.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RecordLinker extends \VuFind\View\Helper\Root\RecordLinker
{
    /**
     * Data source configuration
     *
     * @var array
     */
    protected $datasourceConfig;

    /**
     * Search memory
     *
     * @var Memory
     */
    protected $searchMemory = null;

    /**
     * Constructor
     *
     * @param \VuFind\Record\Router $router   Record router
     * @param array                 $dsConfig Data source configuration
     */
    public function __construct(\VuFind\Record\Router $router, array $dsConfig)
    {
        parent::__construct($router);
        $this->datasourceConfig = $dsConfig;
    }

    /**
     * Inject the search memory
     *
     * @param Memory $memory Search memory
     *
     * @return void
     */
    public function setSearchMemory(Memory $memory): void
    {
        $this->searchMemory = $memory;
    }

    /**
     * Returns 'data-embed-iframe' if url is vimeo or youtube url
     *
     * @param string $url record url
     *
     * @return string
     */
    public function getEmbeddedVideo($url)
    {
        if ($this->getEmbeddedVideoUrl($url)) {
            return 'data-embed-iframe';
        }
        return '';
    }

    /**
     * Returns url for video embedding if url is vimeo or youtube url
     *
     * @param string $url record url
     *
     * @return string
     */
    public function getEmbeddedVideoUrl($url)
    {
        $parts = parse_url($url);
        if (!$parts || !isset($parts['host'])) {
            return '';
        }
        $embedUrl = '';
        switch ($parts['host']) {
            case 'vimeo.com':
                $embedUrl = 'https://player.vimeo.com/video' . $parts['path'];
                break;
            case 'youtu.be':
                $embedUrl = 'https://www.youtube.com/embed' . $parts['path'];
                break;
            case 'youtube.com':
                parse_str($parts['query'] ?? '', $query);
                if (!isset($query['v'])) {
                    return '';
                }
                $embedUrl = 'https://www.youtube.com/embed/' . $query['v'];
                break;
            case 'players.icareus.com':
                // Icareus URLs can be returned as is.
                $embedUrl = $url;
                break;
        }
        return $embedUrl;
    }

    /**
     * Given an array representing a related record (which may be a bib ID or OCLC
     * number), this helper renders a URL linking to that record.
     *
     * @param array  $link   Link information from record model
     * @param string $source Source ID for backend being used to retrieve records
     *
     * @return string       URL derived from link information
     */
    public function related($link, $source = DEFAULT_SEARCH_BACKEND)
    {
        $driver = $this->getView()->plugin('record')->getDriver();

        if ('identifier' === $link['type']) {
            $urlHelper = $this->getView()->plugin('url');
            $result = $urlHelper(
                $this->getSearchActionForSource($source),
                [],
                [
                    'query' => [
                        'lookfor' => $link['value'],
                        'type' => 'Identifier',
                        'jumpto' => 1,
                    ],
                ],
            );
        } elseif ('linkingId' === $link['type']) {
            $urlHelper = $this->getView()->plugin('url');
            $lookFor = sprintf(
                'linking_id_str_mv:"%s" AND datasource_str_mv:"%s"',
                $link['value'],
                $driver->getDataSource()
            );
            $result = $urlHelper(
                $this->getSearchActionForSource($source),
                [],
                [
                        'query' => [
                            'lookfor' => $lookFor,
                            'filter[]' => 'finna.include_hidden_parts:1',
                            'jumpto' => 1,
                        ],
                    ],
            );
        } else {
            $result = parent::related($link, $source);
        }

        $prepend = (!str_contains($result, '?')) ? '?' : '&amp;';
        $hiddenFilters = null;
        // Try to get hidden filters for the current search:
        if ($this->searchMemory) {
            $searchId = $driver->getExtraDetail('searchId')
                ?? $this->getView()->plugin('searchMemory')->getLastSearchId();
            if ($searchId && ($search = $this->searchMemory->getSearchById($searchId))) {
                $filters = UrlQueryHelper::buildQueryString(
                    [
                        'hiddenFilters' => $search->getParams()->getHiddenFiltersAsQueryParams(),
                    ]
                );
                $hiddenFilters = $filters ? $prepend . $filters : '';
            }
        }
        // If we couldn't get hidden filters for the current search, use last filters:
        if (null === $hiddenFilters) {
            $hiddenFilters = $this->getView()->plugin('searchTabs')
                ->getCurrentHiddenFilterParams(
                    $driver->getSearchBackendIdentifier(),
                    false,
                    $prepend
                );
        }
        $result .= $hiddenFilters;

        if ($filters = ($link['filter'] ?? [])) {
            $result .= '&' . implode(
                '&',
                array_map(
                    function ($key, $val) {
                        return 'filter[]=' . urlencode("$key:$val");
                    },
                    array_keys($filters),
                    $filters
                )
            );
        }

        return $result;
    }

    /**
     * Return URL of the record in staff interface if available
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return string
     */
    public function getStaffUiUrl($driver)
    {
        $parts = explode('.', $driver->getUniqueId(), 2);

        if (!isset($parts[1])) {
            return '';
        }
        $source = $parts[0];
        $id = $parts[1];

        if (!empty($this->datasourceConfig[$source]['staffUiUrl'])) {
            $url = $this->datasourceConfig[$source]['staffUiUrl'];
            return str_replace('%%id%%', $id, $url);
        }
        return '';
    }
}
