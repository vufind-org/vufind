<?php
/**
 * VuFind Search Memory
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Search;

use Laminas\Session\Container;

/**
 * Wrapper class to handle search memory
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Memory
{
    /**
     * Is memory currently active? (i.e. will we save new URLs?)
     *
     * @var bool
     */
    protected $active = true;

    /**
     * Session container
     *
     * @var Container
     */
    protected $session;

    /**
     * Constructor
     *
     * @param Container $session Session container for storing URLs (optional)
     */
    public function __construct(Container $session)
    {
        $this->session = $session;
    }

    /**
     * Stop updating the URL in memory -- used in combined search to prevent
     * multiple search URLs from overwriting one another.
     *
     * @return void
     */
    public function disable()
    {
        $this->active = false;
    }

    /**
     * Clear the last accessed search URL in the session.
     *
     * @return void
     */
    public function forgetSearch()
    {
        unset($this->session->last);
    }

    /**
     * Remember a user's last search parameters.
     *
     * @param string $context Context of search (usually search class ID).
     * @param array  $params  Associative array of keys/values to store.
     *
     * @return void
     */
    public function rememberLastSettings($context, $params)
    {
        if (!$this->active) {
            return;
        }
        foreach ($params as $setting => $value) {
            $this->session->{"params|$context|$setting"} = $value;
        }
    }

    /**
     * Wrapper around rememberLastSettings() to extract key values from a
     * search Params object.
     *
     * @param \VuFind\Search\Base\Params $params Parameter object
     *
     * @return void
     */
    public function rememberParams(\VuFind\Search\Base\Params $params)
    {
        // Since default sort may vary based on search handler, we don't want
        // to force the sort value to stick unless the user chose a non-default
        // option. Otherwise, if you switch between search types, unpredictable
        // sort options may result.
        $sort = $params->getSort();
        $defaultSort = $params->getDefaultSort();
        $settings = [
            'hiddenFilters' => $params->getHiddenFilters(),
            'limit' => $params->getLimit(),
            'sort' => $sort === $defaultSort ? null : $sort,
            'view' => $params->getView(),
        ];
        // Special case: RSS view should not be persisted:
        if (strtolower($settings['view']) == 'rss') {
            unset($settings['view']);
        }
        $this->rememberLastSettings($params->getSearchClassId(), $settings);
    }

    /**
     * Store the last accessed search URL in the session for future reference.
     *
     * @param string $url URL to remember
     *
     * @return void
     */
    public function rememberSearch($url)
    {
        // Do nothing if disabled.
        if (!$this->active) {
            return;
        }

        // Only remember URL if string is non-empty... otherwise clear the memory.
        if (strlen(trim($url)) > 0) {
            $this->session->last = $url;
        } else {
            $this->forgetSearch();
        }
    }

    /**
     * Retrieve a previous user parameter, if available. Return $default if
     * not found.
     *
     * @param string $context Context of search (usually search class ID).
     * @param string $setting Name of setting to retrieve.
     * @param mixed  $default Default value if setting is absent.
     *
     * @return mixed
     */
    public function retrieveLastSetting($context, $setting, $default = null)
    {
        return $this->session->{"params|$context|$setting"} ?? $default;
    }

    /**
     * Retrieve last accessed search URL, if available.  Returns null if no URL
     * is available.
     *
     * @return string|null
     */
    public function retrieveSearch()
    {
        return $this->session->last ?? null;
    }
}
