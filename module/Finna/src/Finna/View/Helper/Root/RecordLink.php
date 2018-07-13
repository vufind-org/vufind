<?php
/**
 * RecordLink view helper
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2017.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class RecordLink extends \VuFind\View\Helper\Root\RecordLink
{
    /**
     * Returns 'data-embed-iframe' if url is vimeo or youtube url
     *
     * @param string $url record url
     *
     * @return string
     */
    public function getEmbeddedVideo($url)
    {
        if (preg_match(
            '/^https?:\/\/(www\.)?(youtube\.com\/watch\?|youtu\.?be\/)\w+/', $url
        ) || preg_match('/^https?:\/\/vimeo\.com\/\d+/', $url)
        ) {
            return 'data-embed-iframe';
        }
        return '';
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
}
