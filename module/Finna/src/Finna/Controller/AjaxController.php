<?php
/**
 * Ajax Controller Module
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2018.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

use Finna\Search\Solr\Params;
use VuFind\RecordDriver\Missing;
use VuFind\Search\RecommendListener;
use VuFindSearch\Query\Query as Query;

/**
 * This controller handles Finna AJAX functionality
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AjaxController extends \VuFind\Controller\AjaxController
{
    use OnlinePaymentControllerTrait,
        SearchControllerTrait,
        CatalogLoginTrait;


    /**
     * Handle online payment notification callback.
     *
     * An empty response with HTTP code 200 is returned
     *
     * @return \Zend\Http\Response
     */
    public function onlinePaymentNotifyAction()
    {
        // Use text/html to avoid any output
        return $this->callAjaxMethod('onlinePaymentNotify', 'text/html');
    }

    /**
     * Get Autocomplete suggestions.
     *
     * @return \Zend\Http\Response
     */
    protected function getACSuggestionsAjax()
    {
        if ($type = $this->getBrowseAction($this->getRequest())) {
            $query = $this->getRequest()->getQuery();
            $query->set('type', "Browse_$type");
            $query->set('searcher', 'Solr');
        }
        return parent::getACSuggestionsAjax();
    }

    /**
     * Get hierarchical facet data for jsTree
     *
     * Parameters:
     * facetName  The facet to retrieve
     * facetSort  By default all facets are sorted by count. Two values are available
     * for alternative sorting:
     *   top = sort the top level alphabetically, rest by count
     *   all = sort all levels alphabetically
     *
     * @return \Zend\Http\Response
     */
    protected function getFacetDataAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        if ($type = $this->getBrowseAction($this->getRequest())) {
            $config
                = $this->serviceLocator->get('VuFind\Config')->get('browse');

            if (!isset($config[$type])) {
                return $this->output(
                    "Missing configuration for browse action: $type",
                    self::STATUS_ERROR,
                    500
                );
            }

            $config = $config[$type];
            $query = $this->getRequest()->getQuery();
            if (!$query->get('sort')) {
                $query->set('sort', $config['sort'] ?: 'title');
            }
            if (!$query->get('type')) {
                $query->set('type', $config['type'] ?: 'Title');
            }
            $query->set('browseHandler', $query->get('type'));
            $query->set('hiddenFilters', $config['filter']->toArray());
        }

        $result = parent::getFacetDataAjax();

        // Filter facet array. Need to decode the JSON response, which is not quite
        // optimal..
        $resultContent = json_decode($result->getContent(), true);

        $facet = $this->params()->fromQuery('facetName');
        $facetConfig = $this->getConfig('facets');
        if (!empty($facetConfig->FacetFilters->$facet)
            || !empty($facetConfig->ExcludeFilters->$facet)
        ) {
            $facetHelper = $this->serviceLocator
                ->get('VuFind\HierarchicalFacetHelper');
            $filters = !empty($facetConfig->FacetFilters->$facet)
                ? $facetConfig->FacetFilters->$facet->toArray()
                : [];
            $excludeFilters = !empty($facetConfig->ExcludeFilters->$facet)
                ? $facetConfig->ExcludeFilters->$facet->toArray()
                : [];

            $resultContent['data'] = $facetHelper->filterFacets(
                $resultContent['data'],
                $filters,
                $excludeFilters
            );
        }

        $result->setContent(json_encode($resultContent));
        return $result;
    }

    /**
     * Return browse action from the request.
     *
     * @param Zend\Http\Request $request Request
     *
     * @return null|string Browse action or null if request is not a browse action
     */
    protected function getBrowseAction($request)
    {
        $referer = $request->getServer()->get('HTTP_REFERER');
        $match = null;
        $regex = '/^http[s]?:.*\/Browse\/(Database|Journal)[\/.*]?/';
        if (preg_match($regex, $referer, $match)) {
            return $match[1];
        }
        return null;
    }

    /**
     * Return an error response in JSON format and log the error message.
     *
     * @param string $outputMsg  Message to include in the JSON response.
     * @param string $logMsg     Message to output to the error log.
     * @param int    $httpStatus HTTPs status of the JSOn response.
     *
     * @return \Zend\Http\Response
     */
    protected function handleError($outputMsg, $logMsg = '', $httpStatus = 400)
    {
        $this->setLogger($this->serviceLocator->get('VuFind\Logger'));
        $this->logError(
            $outputMsg . ($logMsg ? " ({$logMsg})" : null)
        );

        return $this->output($outputMsg, self::STATUS_ERROR, $httpStatus);
    }

}
