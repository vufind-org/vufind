<?php
/**
 * "Search tabs" view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * "Search tabs" view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SearchTabs extends \VuFind\View\Helper\Root\SearchTabs
{
    /**
     * Database manager
     *
     * @var PluginManager
     */
    protected $table;

    /**
     * Active search class
     *
     * @var string
     */
    protected $activeSearchClass;

    /**
     * Constructor
     *
     * @param PluginManager $table   Database manager
     * @param PluginManager $results Search results plugin manager
     * @param array         $config  Tab configuration
     * @param Url           $url     URL helper
     */
    public function __construct(
        \VuFind\Db\Table\PluginManager $table,
        \VuFind\Search\Results\PluginManager $results,
        array $config, \Zend\View\Helper\Url $url
    ) {
        $this->table = $table;
        if (isset($config['Combined'])) {
            // Make sure that combined view is the first tab
            $config = ['Combined' => $config['Combined']] + $config;
        }
        parent::__construct($results, $config, $url);
    }

    /**
     * Determine information about search tabs
     *
     * @param string $activeSearchClass The search class ID of the active search
     * @param string $query             The current search query
     * @param string $handler           The current search handler
     * @param string $type              The current search type (basic/advanced)
     * @param array  $savedSearches     Saved search ids from all search tabs
     *
     * @return array
     */
    public function __invoke(
        $activeSearchClass, $query, $handler, $type = 'basic', $savedSearches = []
    ) {
        $this->activeSearchClass = $activeSearchClass;
        $helper = $this->getView()->results->getUrlQuery();

        $tabs = parent::__invoke($activeSearchClass, $query, $handler, $type);
        $searchTable = $this->table->get('Search');

        foreach ($tabs as &$tab) {
            if (isset($tab['url'])) {
                $searchClass = $tab['class'];
                if (isset($savedSearches[$searchClass])) {
                    $searchId = $savedSearches[$tab['class']];
                    $filters = $searchTable->getSearchFilters(
                        $searchId, $this->results
                    );
                    $targetClass = $tab['class'];

                    // Make sure that tab url does not contain the
                    // search id for the same tab.
                    $parts = parse_url($tab['url']);
                    parse_str($parts['query'], $params);

                    if (isset($params['search'])) {
                        $filtered = [];
                        foreach ($params['search'] as $search) {
                            list($searchClass, $searchId) = explode(':', $search);
                            if ($searchClass !== $targetClass) {
                                $filtered[] = $search;
                            }
                        }
                        if (!empty($filtered)) {
                            $params['search'] = $filtered;
                        } else {
                            unset($params['search']);
                        }
                    }

                    $url = $parts['path'] . '?' . http_build_query($params);
                    $tab['url'] = $url;
                    if ($filters) {
                        $tab['url'] .= '&' .
                            $helper->buildQueryString(
                                ['filter' => $filters], false
                            );
                    }
                }
            }
        }

        return $tabs;
    }

    /**
     * Map a search query from one class to another.
     *
     * @param \VuFind\Search\Base\Options $activeOptions Search options for source
     * @param string                      $targetClass   Search class ID for target
     * @param string                      $query         Search query to map
     * @param string                      $handler       Search handler to map
     *
     * @return string
     */
    protected function remapBasicSearch($activeOptions, $targetClass, $query,
        $handler
    ) {
        // Set up results object for URL building:
        $results = $this->results->get($targetClass);
        $options = $results->getOptions();

        // Find matching handler for new query (and use default if no match):
        $targetHandler = $options->getHandlerForLabel(
            $activeOptions->getLabelForBasicHandler($handler)
        );

        // Clone helper so that we can remove active filters
        $urlQuery = $this->getView()->results->getUrlQuery();
        $urlQuery = clone($urlQuery);

        // Remove current filters
        $urlQuery->removeAllFilters();

        $filters = $this->getView()->results->getParams()->getFilters();
        if (!empty($filters)) {
            // Filters active, include current search id in the url
            $searchClass = $this->activeSearchClass;
            $searchId = $this->getView()->results->getSearchHash();
            $query = $urlQuery->setSearchId($searchClass, $searchId);
        } else {
            $query = $urlQuery->getParams(false);
        }

        // Build new URL:
        $results->getParams()->setBasicSearch($query, $targetHandler);
        return $this->url->__invoke($options->getSearchAction())
            . $query;
    }
}
