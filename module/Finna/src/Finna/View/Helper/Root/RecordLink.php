<?php
/**
 * RecordLink view helper
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2017-2018.
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
 * @author   Anna Niku <anna.niku@gofore.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * RecordLink view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Anna Niku <anna.niku@gofore.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class RecordLink extends \VuFind\View\Helper\Root\RecordLink
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
     * @param \VuFind\Record\Router $router Record router
     * @param array                 $config Configuration for search box
     */
    public function __construct(\VuFind\Record\Router $router, $config)
    {
        parent::__construct($router);
        $this->datasourceConfig = $config;
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
        $embedUrl = '';
        switch ($parts['host']) {
        case 'vimeo.com':
            $embedUrl = "https://player.vimeo.com/video" . $parts['path'];
            break;
        case 'youtu.be':
            $embedUrl = "https://www.youtube.com/embed" . $parts['path'];
            break;
        case 'youtube.com':
            parse_str($parts['query'], $query);
            $embedUrl = "https://www.youtube.com/embed/" . $query['v'];
            break;
        default:
            $embedUrl = '';
        }
        return $embedUrl;
    }

    /**
     * Given an array representing a related record (which may be a bib ID or OCLC
     * number), this helper renders a URL linking to that record.
     *
     * @param array  $link   Link information from record model
     * @param bool   $escape Should we escape the rendered URL?
     * @param string $source Source ID for backend being used to retrieve records
     *
     * @return string       URL derived from link information
     */
    public function related($link, $escape = true, $source = DEFAULT_SEARCH_BACKEND)
    {
        $result = parent::related($link, $escape, $source);

        $driver = $this->getView()->plugin('record')->getDriver();
        $result .= $this->getView()->plugin('searchTabs')
            ->getCurrentHiddenFilterParams($driver->getSourceIdentifier());

        return $result;
    }

    /**
     * Return URL of the record in staff interface if available
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return string
     */
    public function getStaffUiUrl($driver)
    {
        $parts = explode('.', $driver->getUniqueId(), 2);

        if (!isset($parts[1])) {
            return '';
        }
        $source = $parts[0];
        $id = $parts[1];

        if (!empty($this->datasourceConfig[$source]['staffUiUrl'])) {
            $url = $this->datasourceConfig[$source]['staffUiUrl'];
            return str_replace('%%id%%', $id, $url);
        }
        return '';
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
        $mapFunc = function ($val) {
            return addcslashes($val, '"');
        };
        $keys = $driver->tryMethod('getWorkKeys', [], []);
        $imploded = implode('" OR "', array_map($mapFunc, $keys));
        $urlParams = [
            'join' => 'AND',
            'lookfor0[]' => "\"$imploded\"",
            'type0[]' => 'WorkKeys',
            'bool0[]' => 'AND',
            'sort' => 'main_date_str desc'
        ];

        $urlHelper = $this->getView()->plugin('url');
        $route = $this->getSearchActionForSource($driver->getSourceIdentifier());
        return $urlHelper($route, [], ['query' => $urlParams]);
    }
}
