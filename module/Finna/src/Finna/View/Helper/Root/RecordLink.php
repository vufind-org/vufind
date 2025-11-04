<?php

/**
 * RecordLink view helper (DEPRECATED -- use RecordLinker instead)
 *
 * Note that RecordLink has been removed from upstream and the Finna version only
 * remains for compatibility with existing production views.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2017-2023.
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
 * @category   VuFind
 * @package    View_Helpers
 * @author     Anna Niku <anna.niku@gofore.com>
 * @author     Ere Maijala <ere.maijala@helsinki.fi>
 * @author     Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link       https://vufind.org/wiki/development Wiki
 * @deprecated RecordLink has been removed from upstream and the Finna version only
 * remains for compatibility with existing production views.
 */

namespace Finna\View\Helper\Root;

use function func_get_args;

/**
 * RecordLink view helper (DEPRECATED -- use RecordLinker instead)
 *
 * @category   VuFind
 * @package    View_Helpers
 * @author     Anna Niku <anna.niku@gofore.com>
 * @author     Ere Maijala <ere.maijala@helsinki.fi>
 * @author     Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link       https://vufind.org/wiki/development Wiki
 * @deprecated RecordLink has been removed from upstream and the Finna version only
 * remains for compatibility with existing production views.
 */
class RecordLink extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Data source configuration
     *
     * @var array
     */
    protected $datasourceConfig;

    /**
     * Constructor
     *
     * @param array $config Configuration for search box
     */
    public function __construct($config)
    {
        // parent no longer has a constructor that uses $router
        $this->datasourceConfig = $config;
    }

    /**
     * Magic method to proxy recordLinker functionality.
     *
     * @param string $method Method being called
     * @param array  $args   Method arguments
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        return $this->getView()->plugin('recordLinker')->$method(...$args);
    }

    /**
     * Alias for getBreadcrumbHtml(), for backward compatibility with
     * VuFind 7.x and earlier versions.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record to link to.
     *
     * @return string
     */
    public function getBreadcrumb($driver)
    {
        return $this->__call('getBreadcrumbHtml', [$driver]);
    }

    /**
     * Given a string or array of parts, build a request (e.g. hold) URL.
     *
     * @param string|array $url           URL to process
     * @param bool         $includeAnchor Should we include an anchor?
     *
     * @return string
     */
    public function getRequestUrl($url, $includeAnchor = true)
    {
        $finalUrl = $this->__call(__FUNCTION__, func_get_args());
        // Make sure everything is properly HTML encoded:
        $escaper = $this->getView()->plugin('escapehtml');
        return $escaper($finalUrl);
    }

    /**
     * Return search URL for all versions
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return string
     */
    public function getVersionsSearchUrl($driver)
    {
        $url = $this->__call(__FUNCTION__, func_get_args());
        // Make sure everything is properly HTML encoded:
        $escaper = $this->getView()->plugin('escapehtml');
        return $escaper($url);
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
                parse_str($parts['query'], $query);
                $embedUrl = 'https://www.youtube.com/embed/' . $query['v'];
                break;
        }
        return $embedUrl;
    }
}
