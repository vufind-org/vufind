<?php
/**
 * "Search tabs" view helper
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use VuFind\Search\Results\PluginManager, Zend\View\Helper\Url;

/**
 * "Search tabs" view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SearchTabs extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Search manager
     *
     * @var PluginManager
     */
    protected $results;

    /**
     * Tab configuration
     *
     * @var array
     */
    protected $config;

    /**
     * URL helper
     *
     * @var Url
     */
    protected $url;

    /**
     * Constructor
     *
     * @param PluginManager $results Search results plugin manager
     * @param array         $config  Tab configuration
     * @param Url           $url     URL helper
     */
    public function __construct(PluginManager $results, array $config, Url $url)
    {
        $this->results = $results;
        $this->config = $config;
        $this->url = $url;
    }

    /**
     * Determine information about search tabs
     *
     * @param string $activeSearchClass The search class ID of the active search
     * @param string $query             The current search query
     * @param string $handler           The current search handler
     * @param string $type              The current search type (basic/advanced)
     *
     * @return array
     */
    public function __invoke($activeSearchClass, $query, $handler, $type = 'basic')
    {
        $retVal = [];
        foreach ($this->config as $class => $label) {
            if ($class == $activeSearchClass) {
                $retVal[] = $this->createSelectedTab($class, $label);
            } else if ($type == 'basic') {
                if (!isset($activeOptions)) {
                    $activeOptions
                        = $this->results->get($activeSearchClass)->getOptions();
                }
                $newUrl = $this
                    ->remapBasicSearch($activeOptions, $class, $query, $handler);
                $retVal[] = $this->createBasicTab($class, $label, $newUrl);
            } else if ($type == 'advanced') {
                $retVal[] = $this->createAdvancedTab($class, $label);
            } else {
                $retVal[] = $this->createHomeTab($class, $label);
            }
        }
        return $retVal;
    }

    /**
     * Create information representing a selected tab.
     *
     * @param string $class Search class ID
     * @param string $label Display text for tab
     *
     * @return array
     */
    protected function createSelectedTab($class, $label)
    {
        return [
            'class' => $class,
            'label' => $label,
            'selected' => true
        ];
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

        // Build new URL:
        $results->getParams()->setBasicSearch($query, $targetHandler);
        return $this->url->__invoke($options->getSearchAction())
            . $results->getUrlQuery()->getParams(false);
    }

    /**
     * Create information representing a basic search tab.
     *
     * @param string $class  Search class ID
     * @param string $label  Display text for tab
     * @param string $newUrl Target search URL
     *
     * @return array
     */
    protected function createBasicTab($class, $label, $newUrl)
    {
        return [
            'class' => $class,
            'label' => $label,
            'selected' => false,
            'url' => $newUrl
        ];
    }

    /**
     * Create information representing a tab linking to "search home."
     *
     * @param string $class Search class ID
     * @param string $label Display text for tab
     *
     * @return array
     */
    protected function createHomeTab($class, $label)
    {
        // If an advanced search is available, link there; otherwise, just go
        // to the search home:
        $options = $this->results->get($class)->getOptions();
        $url = $this->url->__invoke($options->getSearchHomeAction());
        return [
            'class' => $class,
            'label' => $label,
            'selected' => false,
            'url' => $url
        ];
    }

    /**
     * Create information representing an advanced search tab.
     *
     * @param string $class Search class ID
     * @param string $label Display text for tab
     *
     * @return array
     */
    protected function createAdvancedTab($class, $label)
    {
        // If an advanced search is available, link there; otherwise, just go
        // to the search home:
        $options = $this->results->get($class)->getOptions();
        $advSearch = $options->getAdvancedSearchAction();
        $url = $this->url
            ->__invoke($advSearch ? $advSearch : $options->getSearchHomeAction());
        return [
            'class' => $class,
            'label' => $label,
            'selected' => false,
            'url' => $url
        ];
    }
}