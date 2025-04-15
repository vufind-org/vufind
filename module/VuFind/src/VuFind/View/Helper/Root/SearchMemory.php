<?php

/**
 * View helper for remembering recent user searches/parameters.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use VuFind\Search\Memory;

/**
 * View helper for remembering recent user searches/parameters.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SearchMemory extends AbstractHelper
{
    /**
     * Search memory
     *
     * @var Memory
     */
    protected $memory;

    /**
     * Constructor
     *
     * @param Memory $memory Search memory
     */
    public function __construct(Memory $memory)
    {
        $this->memory = $memory;
    }

    /**
     * If a previous search is recorded in the session, return a link to it;
     * otherwise, return a blank string.
     *
     * @param string $link   Text to use as body of link
     * @param string $prefix Text to place in front of link
     * @param string $suffix Text to place after link
     *
     * @return string
     */
    public function getLastSearchLink($link, $prefix = '', $suffix = '')
    {
        if ($url = $this->getLastSearchUrl()) {
            $escaper = $this->getView()->plugin('escapeHtml');
            return $prefix . '<a href="' . $escaper($url) . '">' . $link . '</a>' . $suffix;
        }
        return '';
    }

    /**
     * If a previous search is recorded in the session, return its URL
     *
     * @return string|null
     */
    public function getLastSearchUrl(): ?string
    {
        if ($lastSearch = $this->getLastSearch()) {
            $searchClassId = $lastSearch->getBackendId();
            $params = $lastSearch->getParams();
            // Use last settings for params that are not stored in the search:
            foreach (['limit', 'view', 'sort'] as $setting) {
                $value = $this->memory->retrieveLastSetting($searchClassId, $setting);
                if ($value) {
                    $method = 'set' . ucfirst($setting);
                    $params->$method($value);
                }
            }

            $urlHelper = $this->getView()->plugin('url');
            $url = $urlHelper($lastSearch->getOptions()->getSearchAction());
            $queryHelper = $lastSearch->getUrlQuery();
            // Try to append page number and page size from search context parameters saved in params object
            $searchContext = $params->getSavedSearchContextParameters();
            if (!empty($searchContext['limit'])) {
                $queryHelper = $queryHelper->setLimit($searchContext['limit']);
            }
            if (!empty($searchContext['page'])) {
                $queryHelper = $queryHelper->setPage($searchContext['page']);
            }
            $queryHelper = $queryHelper->setJumpto(false);

            $url .= $queryHelper->getParams(false);

            // Make sure the URL stored in search memory stays in sync; if the stored URL has been manipulated
            // through the EditMemory action and the user goes back to a page with a sid parameter, things can
            // get into a bad state. Refreshing the value here ensures consistent behavior.
            $this->memory->rememberSearch($url);

            return $url;
        }
        return null;
    }

    /**
     * Retrieve the last hidden filters used.
     *
     * @param string $context Context of search (usually search class ID).
     *
     * @return array
     */
    public function getLastHiddenFilters($context)
    {
        return $this->memory->retrieveLastSetting($context, 'hiddenFilters', []);
    }

    /**
     * Retrieve the last limit option used.
     *
     * @param string $context Context of search (usually search class ID).
     *
     * @return string
     */
    public function getLastLimit($context)
    {
        return $this->memory->retrieveLastSetting($context, 'limit');
    }

    /**
     * Retrieve the last sort option used.
     *
     * @param string $context Context of search (usually search class ID).
     *
     * @return string
     */
    public function getLastSort($context)
    {
        return $this->memory->retrieveLastSetting($context, 'sort');
    }

    /**
     * Get the URL to edit the last search.
     *
     * @param string $searchClassId Search class
     * @param string $action        Action to take
     * @param mixed  $value         Value for action
     *
     * @return string
     */
    public function getEditLink($searchClassId, $action, $value)
    {
        $query = compact('searchClassId') + [$action => $value];
        $url = $this->getView()->plugin('url');
        return $url('search-editmemory', [], compact('query'));
    }

    /**
     * Retrieve the parameters of the last search by the search class
     *
     * @param string $searchClassId Search class
     *
     * @return \VuFind\Search\Base\Params
     */
    public function getLastSearchParams($searchClassId)
    {
        $lastUrl = $this->memory->retrieveSearch();
        $queryParams = $lastUrl ? parse_url($lastUrl, PHP_URL_QUERY) : '';
        $request = new \Laminas\Stdlib\Parameters();
        $request->fromString($queryParams ?? '');
        $paramsPlugin = $this->getView()->plugin('searchParams');
        $params = $paramsPlugin($searchClassId);
        // Make sure the saved URL represents search results from $searchClassId;
        // if the user jumps from search results of one backend to a record of a
        // different backend, we don't want to display irrelevant filters. If there
        // is a backend mismatch, don't initialize the parameter object!
        if ($lastUrl) {
            $expectedPath = $this->view->url($params->getOptions()->getSearchAction());
            if (str_starts_with($lastUrl, $expectedPath)) {
                $params->initFromRequest($request);
            }
        }
        return $params;
    }

    /**
     * Get current search id
     *
     * @return ?int
     */
    public function getCurrentSearchId(): ?int
    {
        return $this->memory->getCurrentSearchId();
    }

    /**
     * Get current search
     *
     * @return ?\VuFind\Search\Base\Results
     */
    public function getCurrentSearch(): ?\VuFind\Search\Base\Results
    {
        return $this->memory->getCurrentSearch();
    }

    /**
     * Get last search id
     *
     * @return ?int
     */
    public function getLastSearchId(): ?int
    {
        return $this->memory->getLastSearchId();
    }

    /**
     * Get last search
     *
     * @return ?\VuFind\Search\Base\Results
     */
    public function getLastSearch(): ?\VuFind\Search\Base\Results
    {
        return $this->memory->getLastSearch();
    }
}
