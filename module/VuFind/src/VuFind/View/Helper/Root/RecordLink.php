<?php
/**
 * Record link view helper
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

/**
 * Record link view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class RecordLink extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Record router
     *
     * @var \VuFind\Record\Router
     */
    protected $router;

    /**
     * Constructor
     *
     * @param \VuFind\Record\Router $router Record router
     */
    public function __construct(\VuFind\Record\Router $router)
    {
        $this->router = $router;
    }

    /**
     * Given an array representing a related record (which may be a bib ID or OCLC
     * number), this helper renders a URL linking to that record.
     *
     * @param array $link   Link information from record model
     * @param bool  $escape Should we escape the rendered URL?
     *
     * @return string       URL derived from link information
     */
    public function related($link, $escape = true)
    {
        $urlHelper = $this->getView()->plugin('url');
        switch ($link['type']) {
        case 'bib':
            $url = $urlHelper('search-results')
                . '?lookfor=' . urlencode($link['value'])
                . '&type=id&jumpto=1';
            break;
        case 'dlc':
            $url = $urlHelper('search-results')
                . '?lookfor=' . urlencode('"' . $link['value'] . '"')
                . '&type=lccn&jumpto=1';
            break;
        case 'isn':
            $url = $urlHelper('search-results')
                . '?join=AND&bool0[]=AND&lookfor0[]=%22'
                . urlencode($link['value'])
                . '%22&type0[]=isn&bool1[]=NOT&lookfor1[]=%22'
                . urlencode($link['exclude'])
                . '%22&type1[]=id&sort=title&view=list';
            break;
        case 'oclc':
            $url = $urlHelper('search-results')
                . '?lookfor=' . urlencode($link['value'])
                . '&type=oclc_num&jumpto=1';
            break;
        case 'title':
            $url = $urlHelper('search-results')
                . '?lookfor=' . urlencode($link['value'])
                . '&type=title';
            break;
        default:
            throw new \Exception('Unexpected link type: ' . $link['type']);
        }

        $escapeHelper = $this->getView()->plugin('escapeHtml');
        return $escape ? $escapeHelper($url) : $url;
    }

    /**
     * Given a record driver, get a URL for that record.
     *
     * @param \VuFind\RecordDriver\AbstractBase|string $driver Record driver
     * representing record to link to, or source|id pipe-delimited string
     * @param string                                   $action Record
     * action to access
     *
     * @return string
     */
    public function getActionUrl($driver, $action)
    {
        // Build the URL:
        $urlHelper = $this->getView()->plugin('url');
        $details = $this->router->getActionRouteDetails($driver, $action);
        return $urlHelper($details['route'], $details['params']);
    }

    /**
     * Given a string or array of parts, build a hold URL.
     *
     * @param string|array $url           URL to process
     * @param bool         $includeAnchor Should we include an anchor?
     *
     * @return string
     */
    public function getHoldUrl($url, $includeAnchor = true)
    {
        if (is_array($url)) {
            // Assemble URL string from array parts:
            $urlHelper = $this->getView()->plugin('url');
            $finalUrl
                = $this->getActionUrl('VuFind|' . $url['record'], $url['action']);
            if (isset($url['query'])) {
                $finalUrl .= '?' . $url['query'];
            }
            if (isset($url['anchor']) && $includeAnchor) {
                $finalUrl .= $url['anchor'];
            }
        } else {
            // If URL is already a string but we don't want anchors, strip
            // the anchor now:
            if (!$includeAnchor) {
                list($finalUrl) = explode('#', $url);
            } else {
                $finalUrl = $url;
            }
        }
        // Make sure everything is properly HTML encoded:
        $escaper = $this->getView()->plugin('escapehtml');
        return $escaper($finalUrl);
    }
    /**
     * Given a record driver, get a URL for that record.
     *
     * @param \VuFind\RecordDriver\AbstractBase|string $driver Record driver
     * representing record to link to, or source|id pipe-delimited string
     * @param string                                   $tab    Optional record
     * tab to access
     *
     * @return string
     */
    public function getTabUrl($driver, $tab = null)
    {
        // Build the URL:
        $urlHelper = $this->getView()->plugin('url');
        $details = $this->router->getTabRouteDetails($driver, $tab);
        return $urlHelper($details['route'], $details['params']);
    }

    /**
     * Get the default URL for a record.
     *
     * @param \VuFind\RecordDriver\AbstractBase|string $driver Record driver
     * representing record to link to, or source|id pipe-delimited string
     *
     * @return string
     */
    public function getUrl($driver)
    {
        // Display default tab by default:
        return $this->getTabUrl($driver);
    }

    /**
     * Given a record driver, generate HTML to link to the record from breadcrumbs.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record to link to.
     *
     * @return string
     */
    public function getBreadcrumb($driver)
    {
        $truncateHelper = $this->getView()->plugin('truncate');
        $escapeHelper = $this->getView()->plugin('escapeHtml');
        return '<a href="' . $this->getUrl($driver) . '">' .
            $escapeHelper($truncateHelper($driver->getBreadcrumb(), 30))
            . '</a>';
    }
}