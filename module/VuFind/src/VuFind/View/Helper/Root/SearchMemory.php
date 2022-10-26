<?php
/**
 * View helper for remembering recent user searches/parameters.
 *
 * PHP version 7
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
        $last = $this->memory->retrieveSearch();
        if (!empty($last)) {
            $escaper = $this->getView()->plugin('escapeHtml');
            return $prefix . '<a href="' . $escaper($last) . '">' . $link . '</a>'
                . $suffix;
        }
        return '';
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
            $expectedPath
                = $this->view->url($params->getOptions()->getSearchAction());
            if (substr($lastUrl, 0, strlen($expectedPath)) === $expectedPath) {
                $params->initFromRequest($request);
            }
        }
        return $params;
    }
}
