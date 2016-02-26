<?php

/**
 * Organisations list view helper
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
 * @category VuFind
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Organisations list view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class OrganisationsList extends \Zend\View\Helper\AbstractHelper implements
    \VuFind\I18n\Translator\TranslatorAwareInterface, \Zend\Log\LoggerAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected $cache;

    /**
     * Hierarchial facet helper
     *
     * @var HierarchicalFacetHelper
     */
    protected $facetHelper;

    /**
     * Search result plugin manager
     *
     * @var PluginManager
     */
    protected $resultsManager;

    /**
     * Constructor
     *
     * @param CacheManager            $cache          cache manager
     * @param HierarchicalFacetHelper $facetHelper    facet helper
     * @param PluginManager           $resultsManager search result manager
     */
    public function __construct($cache, $facetHelper, $resultsManager)
    {
        $this->cache = $cache;
        $this->facetHelper = $facetHelper;
        $this->resultsManager = $resultsManager;
    }

    /**
     * List of current organisations.
     *
     * @return array
     */
    public function __invoke()
    {
        $language = $this->translator->getLocale();
        $cacheName = 'organisations_list_' . $language;
        $list = $this->cache->getItem($cacheName);

        if (!$list) {
            $emptyResults = $this->resultsManager->get('EmptySet');

            $sectors = ['arc', 'lib', 'mus'];
            try {
                foreach ($sectors as $sector) {
                    $list[$sector] = [];
                    $results = $this->resultsManager->get('Solr');
                    $params = $results->getParams();
                    $params->addFacet('building', 'Building', false);
                    $params->addFilter('sector_str_mv:0/' . $sector . '/');
                    $params->setLimit(0);
                    $params->setFacetPrefix('0');
                    $params->setFacetLimit('-1');

                    $collection = $results->getFacetList()['building']['list'];

                    foreach ($collection as $item) {
                        $link = $emptyResults->getUrlQuery()
                            ->addFacet('building', $item['value']);
                        $displayText = $item['displayText'];
                        if ($displayText == $item['value']) {
                            $displayText = $this->facetHelper
                                ->formatDisplayText($displayText)
                                ->getDisplayString();
                        }
                        $list[$sector][] = [
                            'name' => $displayText,
                            'link' => $link,
                        ];
                    }
                    usort(
                        $list[$sector],
                        function ($a,$b) {
                            return strtolower($a['name']) > strtolower($b['name']);
                        }
                    );
                }
                $this->cache->setItem($cacheName, $list);
            } catch (\VuFindSearch\Backend\Exception\BackendException $e) {
                foreach ($sectors as $sector) {
                    $list[$sector] = [];
                }
                $this->logError(
                    'Error creating organisations list: ' . $e->getMessage()
                );
            }
        }

        return $list;
    }

}
