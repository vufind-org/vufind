<?php

/**
 * Matomo web analytics view helper for Matomo versions >= 4
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2014-2021.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\View\Helper\Root;

use VuFind\RecordDriver\AbstractBase as RecordDriverBase;
use VuFind\Search\Base\Results;

use function intval;
use function is_array;

/**
 * Matomo web analytics view helper for Matomo versions >= 4
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Matomo extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Matomo URL (empty if disabled)
     *
     * @var string
     */
    protected $url;

    /**
     * Matomo Site ID
     *
     * @var int
     */
    protected $siteId;

    /**
     * Search prefix (see config.ini for details)
     *
     * @var string
     */
    protected $searchPrefix;

    /**
     * Whether to disable cookies (see config.ini for details)
     *
     * @var bool
     */
    protected $disableCookies;

    /**
     * Whether to use custom variables to track additional information
     *
     * @var bool
     */
    protected $customVars;

    /**
     * Mappings from data fields to custom dimensions for tracking additional
     * information
     *
     * @var array
     */
    protected $customDimensions;

    /**
     * Request object
     *
     * @var \Laminas\Http\PhpEnvironment\Request
     */
    protected $request;

    /**
     * Router object
     *
     * @var \Laminas\Router\Http\RouteMatch
     */
    protected $router;

    /**
     * A timestamp used to identify the init function to avoid name clashes when
     * opening lightboxes.
     *
     * @var int
     */
    protected $timestamp;

    /**
     * Tracker initialization context ('', 'lightbox', 'accordion' or 'tabs')
     *
     * @var string
     */
    protected $context = '';

    /**
     * Additional parameters
     *
     * @var array
     */
    protected $params = [];

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config               $config  VuFind configuration
     * @param \Laminas\Router\Http\TreeRouteStack  $router  Router
     * @param \Laminas\Http\PhpEnvironment\Request $request Request
     */
    public function __construct(
        \Laminas\Config\Config $config,
        \Laminas\Router\Http\TreeRouteStack $router,
        \Laminas\Http\PhpEnvironment\Request $request
    ) {
        $this->url = $config->Matomo->url ?? '';
        if ($this->url && !str_ends_with($this->url, '/')) {
            $this->url .= '/';
        }
        $this->siteId = $config->Matomo->site_id ?? 1;
        $this->searchPrefix = $config->Matomo->searchPrefix ?? '';
        $this->disableCookies = $config->Matomo->disableCookies ?? false;
        $this->customVars = $config->Matomo->custom_variables ?? false;
        $this->customDimensions = $config->Matomo->custom_dimensions ?? [];
        $this->router = $router;
        $this->request = $request;
        $this->timestamp = round(microtime(true) * 1000);
    }

    /**
     * Returns Matomo code (if active) or empty string if not.
     *
     * @param array $params Parameters
     *
     * @return string
     */
    public function __invoke(array $params = []): string
    {
        if (!$this->url) {
            return '';
        }

        $this->params = $params;
        $this->context = $this->params['context'] ?? '';

        $results = $this->getSearchResults();
        if ($results && ($combinedResults = $this->getCombinedSearchResults())) {
            $code = $this->trackCombinedSearch($results, $combinedResults);
        } elseif ($results) {
            $code = $this->trackSearch($results);
        } elseif ($recordDriver = $this->getRecordDriver()) {
            $code = $this->trackRecordPage($recordDriver);
        } else {
            $code = $this->trackPageView();
        }

        $inlineScript = $this->getView()->plugin('inlinescript');
        return $inlineScript(\Laminas\View\Helper\HeadScript::SCRIPT, $code, 'SET');
    }

    /**
     * Track a Search
     *
     * @param Results $results Search Results
     *
     * @return string Tracking Code
     */
    protected function trackSearch(Results $results): string
    {
        $customData = 'lightbox' === $this->context
            ? $this->getLightboxCustomData()
            : $this->getSearchCustomData($results);

        $code = $this->getOpeningTrackingCode();
        $code .= $this->getCustomVarsCode($customData);
        $code .= $this->getTrackSearchCode($results, $customData);
        $code .= $this->getClosingTrackingCode();

        return $code;
    }

    /**
     * Track a Combined Search
     *
     * @param Results $results         Search Results
     * @param array   $combinedResults Combined Search Results
     *
     * @return string Tracking Code
     */
    protected function trackCombinedSearch(
        Results $results,
        array $combinedResults
    ): string {
        $customData = 'lightbox' === $this->context
            ? $this->getLightboxCustomData()
            : $this->getSearchCustomData($results);

        $code = $this->getOpeningTrackingCode();
        $code .= $this->getCustomVarsCode($customData);
        $code .= $this->getTrackCombinedSearchCode(
            $results,
            $combinedResults,
            $customData
        );
        $code .= $this->getClosingTrackingCode();

        return $code;
    }

    /**
     * Track a Record View
     *
     * @param RecordDriverBase $recordDriver Record Driver
     *
     * @return string Tracking Code
     */
    protected function trackRecordPage(RecordDriverBase $recordDriver): string
    {
        $customData = 'lightbox' === $this->context
            ? $this->getLightboxCustomData()
            : $this->getRecordPageCustomData($recordDriver);

        $code = $this->getOpeningTrackingCode();
        $code .= $this->getCustomVarsCode($customData);
        $code .= $this->getTrackPageViewCode($customData);
        $code .= $this->getClosingTrackingCode();

        return $code;
    }

    /**
     * Track a Generic Page View
     *
     * @return string Tracking Code
     */
    protected function trackPageView(): string
    {
        $customData = 'lightbox' === $this->context
            ? $this->getLightboxCustomData()
            : $this->getGenericCustomData();

        $code = $this->getOpeningTrackingCode();
        $code .= $this->getCustomVarsCode($customData);
        $code .= $this->getTrackPageViewCode($customData);
        $code .= $this->getClosingTrackingCode();

        return $code;
    }

    /**
     * Get Search Results if on a Results Page
     *
     * @return ?Results Search results or null if not on a search page
     */
    protected function getSearchResults(): ?Results
    {
        $viewModel = $this->getView()->plugin('view_model');
        $current = $viewModel->getCurrent();
        if (null === $current || 'layout/lightbox' === $current->getTemplate()) {
            return null;
        }
        $children = $current->getChildren();
        if (isset($children[0])) {
            $template = $children[0]->getTemplate();
            if (!strstr($template, '/home') && !strstr($template, 'facet-list')) {
                $results = $children[0]->getVariable('results');
                if ($results instanceof Results) {
                    return $results;
                }
            }
        }
        return null;
    }

    /**
     * Get Combined Search Results if on a Results Page
     *
     * @return ?array Array of search results or null if not on a combined search
     * page
     */
    protected function getCombinedSearchResults(): ?array
    {
        $viewModel = $this->getView()->plugin('view_model');
        $current = $viewModel->getCurrent();
        if (null === $current) {
            return null;
        }
        $children = $current->getChildren();
        if (isset($children[0])) {
            $results = $children[0]->getVariable('combinedResults');
            if (is_array($results)) {
                return $results;
            }
        }
        return null;
    }

    /**
     * Get Record Driver if on a Record Page
     *
     * @return ?RecordDriverBase Record driver or null if not on a record page
     */
    protected function getRecordDriver(): ?RecordDriverBase
    {
        $view = $this->getView();
        $viewModel = $view->plugin('view_model');
        $current = $viewModel->getCurrent();
        if (null === $current) {
            $driver = $view->vars('driver');
            if ($driver instanceof RecordDriverBase) {
                return $driver;
            }
            return null;
        }
        $children = $current->getChildren();
        if (isset($children[0])) {
            $driver = $children[0]->getVariable('driver');
            if ($driver instanceof RecordDriverBase) {
                return $driver;
            }
        }
        return null;
    }

    /**
     * Get custom data for search results
     *
     * @param Results $results Search results
     *
     * @return array Associative array of custom data
     */
    protected function getSearchCustomData(Results $results): array
    {
        $facets = [];
        $facetTypes = [];
        $params = $results->getParams();
        foreach ($params->getFilterList() as $filterType => $filters) {
            $facetTypes[] = $filterType;
            foreach ($filters as $filter) {
                $facets[] = $filter['field'] . '|' . $filter['value'];
            }
        }
        $facets = implode("\t", $facets);
        $facetTypes = implode("\t", $facetTypes);

        return [
            'Facets' => $facets,
            'FacetTypes' => $facetTypes,
            'SearchType' => $params->getSearchType(),
            'SearchBackend' => $params->getSearchClassId(),
            'Sort' => $params->getSort(),
            'Page' => $params->getPage(),
            'Limit' => $params->getLimit(),
            'View' => $params->getView(),
            'Context' => $this->context ?: 'page',
        ];
    }

    /**
     * Get custom data for record page
     *
     * @param RecordDriverBase $recordDriver Record driver
     *
     * @return array Associative array of custom data
     */
    protected function getRecordPageCustomData(RecordDriverBase $recordDriver): array
    {
        $id = $recordDriver->getUniqueID();
        $formats = $recordDriver->tryMethod('getFormats');
        if (is_array($formats)) {
            $formats = implode(',', $formats);
        }
        $formats = $formats;
        $author = $recordDriver->tryMethod('getPrimaryAuthor');
        if (empty($author)) {
            $author = '-';
        }
        // Use breadcrumb for title since it's guaranteed to return something
        $title = $recordDriver->tryMethod('getBreadcrumb');
        if (empty($title)) {
            $title = '-';
        }
        $institutions = $recordDriver->tryMethod('getInstitutions');
        if (is_array($institutions)) {
            $institutions = implode(',', $institutions);
        }
        $institutions = $institutions;

        return [
            'Context' => $this->context ?: 'page',
            'RecordFormat' => $formats,
            'RecordData' => "$id|$author|$title",
            'RecordInstitution' => $institutions,
        ];
    }

    /**
     * Get custom data for lightbox actions
     *
     * @return array Associative array of custom data
     */
    protected function getLightboxCustomData(): array
    {
        return [
            'Context' => $this->context ?: 'page',
        ];
    }

    /**
     * Get custom data for a generic page view
     *
     * @return array Associative array of custom data
     */
    protected function getGenericCustomData(): array
    {
        return [
            'Context' => $this->context ?: 'page',
        ];
    }

    /**
     * Get the Initialization Part of the Tracking Code
     *
     * @return string JavaScript Code Fragment
     */
    protected function getOpeningTrackingCode(): string
    {
        $escape = $this->getView()->plugin('escapejs');
        $cookieConsent = $this->getView()->plugin('cookieConsent');
        $pageUrl = $escape($this->getPageUrl());
        $code = <<<EOT
            var _paq = window._paq = window._paq || [];
            _paq.push(['enableLinkTracking']);
            _paq.push(['setCustomUrl', '$pageUrl']);

            EOT;
        if ($this->disableCookies) {
            $code .= "_paq.push(['disableCookies']);\n";
        } elseif ($cookieConsent->isEnabled()) {
            $code .= "_paq.push(['requireCookieConsent']);\n";
        }

        return $code;
    }

    /**
     * Get the Finalization Part of the Tracking Code
     *
     * @return string JavaScript Code Fragment
     */
    protected function getClosingTrackingCode(): string
    {
        $escape = $this->getView()->plugin('escapejs');
        $trackerUrl = $escape($this->getTrackerUrl());
        $url = $escape($this->getTrackerJsUrl());
        return <<<EOT
            (function() {
              var d=document;
              if (!d.getElementById('_matomo_js_script')) {
                _paq.push(['setTrackerUrl', '$trackerUrl']);
                _paq.push(['setSiteId', {$this->siteId}]);
                var g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
                g.async=true; g.src='$url';
                g.id = '_matomo_js_script';
                s.parentNode.insertBefore(g,s);
              }
            })();

            EOT;
    }

    /**
     * Get the URL for the current page
     *
     * @return string
     */
    protected function getPageUrl(): string
    {
        $path = $this->request->getUri()->toString();
        // Replace 'AjaxTab' with tab name in record page URLs:
        $routeMatch = $this->router->match($this->request);
        if (
            $routeMatch
            && ($tab = $this->request->getPost('tab'))
            && str_ends_with($routeMatch->getMatchedRouteName(), '-ajaxtab')
            && null !== ($pos = strrpos($path, '/AjaxTab'))
        ) {
            $path = substr_replace($path, $tab, $pos + 1, 7);
        }
        return $path;
    }

    /**
     * Convert a custom data array to JavaScript code
     *
     * @param array $customData Custom data
     *
     * @return string JavaScript Code Fragment
     */
    protected function getCustomVarsCode(array $customData): string
    {
        // Don't output custom vars if disabled:
        if (!$this->customVars) {
            return '';
        }

        $escape = $this->getView()->plugin('escapejs');
        $code = <<<EOT
            _paq.push(['deleteCustomVariables','page']);

            EOT;
        $i = 0;
        foreach ($customData as $key => $value) {
            ++$i;
            // We're committed to tracking a maximum of 10 custom variables at a
            // time:
            if ($i > 10) {
                break;
            }
            $value = $escape($value);
            $code .= <<<EOT
                _paq.push(['setCustomVariable',$i,'$key','$value','page']);

                EOT;
        }
        return $code;
    }

    /**
     * Convert a custom data array to JavaScript dimensions code
     *
     * @param array $customData Custom data
     *
     * @return string JavaScript Code Fragment
     */
    protected function getCustomDimensionsCode(array $customData): string
    {
        // Return immediately if custom dimensions are not configured:
        if (!$this->customDimensions) {
            return '{}';
        }

        $dimensions = [];
        foreach ($customData as $key => $value) {
            if (!empty($this->customDimensions[$key])) {
                $dimensionId = 'dimension' . intval($this->customDimensions[$key]);
                $dimensions[$dimensionId] = $value;
            }
        }
        return json_encode($dimensions);
    }

    /**
     * Get Site Search Tracking Code
     *
     * @param Results $results    Search results
     * @param array   $customData Custom data
     *
     * @return string JavaScript Code Fragment
     */
    protected function getTrackSearchCode(
        Results $results,
        array $customData
    ): string {
        $escape = $this->getView()->plugin('escapejs');
        $params = $results->getParams();
        $searchTerms = $escape($params->getDisplayQuery());
        $searchType = $escape($params->getSearchType());
        $resultCount = $results->getResultTotal();
        $backendId = $results->getOptions()->getSearchClassId();
        $dimensions = $this->getCustomDimensionsCode($customData);

        // Use trackSiteSearch *instead* of trackPageView in searches
        return "_paq.push(['trackSiteSearch', '{$this->searchPrefix}$backendId|"
            . "$searchTerms', '$searchType', $resultCount, $dimensions]);\n";
    }

    /**
     * Get site search tracking code for combined search
     *
     * @param Results $results         Search results
     * @param array   $combinedResults Combined search results
     * @param array   $customData      Custom data
     *
     * @return string JavaScript Code Fragment
     */
    protected function getTrackCombinedSearchCode(
        Results $results,
        array $combinedResults,
        array $customData
    ): string {
        $escape = $this->getView()->plugin('escapejs');
        $params = $results->getParams();
        $searchTerms = $escape($params->getDisplayQuery());
        $searchType = $escape($params->getSearchType());
        $resultCount = 0;
        foreach ($combinedResults as $currentSearch) {
            if (!empty($currentSearch['ajax'])) {
                // Some results fetched via ajax, so report that we don't know the
                // result count.
                $resultCount = 'false';
                break;
            }
            $resultCount += $currentSearch['view']->results
                ->getResultTotal();
        }
        $dimensions = $this->getCustomDimensionsCode($customData);

        // Use trackSiteSearch *instead* of trackPageView in searches
        return "_paq.push(['trackSiteSearch', '{$this->searchPrefix}Combined|"
            . "$searchTerms', '$searchType', $resultCount, $dimensions]);\n";
    }

    /**
     * Get Page View Tracking Code
     *
     * @param array $customData Custom data
     *
     * @return string JavaScript Code Fragment
     */
    protected function getTrackPageViewCode(array $customData): string
    {
        $titleJs = 'var title = null;';
        $dimensions = $this->getCustomDimensionsCode($customData);
        switch ($this->context) {
            case 'accordion':
                $translate = $this->getView()->plugin('translate');
                $escape = $this->getView()->plugin('escapejs');
                $title = $translate('ajaxview_label_information');
                if ($driver = $this->getRecordDriver()) {
                    $title .= ': ' . $driver->getBreadcrumb();
                }
                $titleJs = "var title = '" . $escape($title) . "';";
                break;
            case 'tabs':
                $escape = $this->getView()->plugin('escapejs');
                $headTitle = $this->getView()->plugin('headTitle');
                if ($title = $headTitle->renderTitle()) {
                    $title = $escape($title);
                    $titleJs = "var title = '$title';";
                } elseif ($driver = $this->getRecordDriver()) {
                    $title = $escape($driver->getBreadcrumb());
                    $titleJs = "var title = '$title';";
                    $titleJs .= <<<EOT
                        var a = document.querySelector('.record-tabs ul.nav-tabs li.active a');
                        if (a) { title = a.innerText + (title ? ': ' + title : ''); }

                        EOT;
                }
                break;
            case 'lightbox':
                $titleJs .= <<<EOT
                    var h = document.getElementsByClassName('lightbox-header');
                    if (h[0]) title = h[0].innerText;

                    EOT;
                break;
        }

        return <<<EOT
            $titleJs
            _paq.push(['trackPageView', title, $dimensions]);

            EOT;
    }

    /**
     * Get Matomo tracker URL
     *
     * @return string
     */
    protected function getTrackerUrl(): string
    {
        return $this->url . 'matomo.php';
    }

    /**
     * Get Matomo tracker JS URL
     *
     * @return string
     */
    protected function getTrackerJsUrl(): string
    {
        return $this->url . 'matomo.js';
    }

    /**
     * Get name of JS init function
     *
     * @return string
     */
    protected function getInitFunctionName(): string
    {
        return 'initMatomoTracker' . $this->timestamp;
    }
}
