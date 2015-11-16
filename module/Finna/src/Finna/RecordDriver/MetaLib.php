<?php
/**
 * Model for MetaLib records.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2012-2015.
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
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for MetaLib records.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class MetaLib extends \VuFind\RecordDriver\SolrMarc
{
    /**
     * Used for identifying search backends
     *
     * @var string
     */
    protected $sourceIdentifier = 'MetaLib';

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        return $this->getTitle();
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        $urls = parent::getURLS();
        foreach ($urls as &$url) {
            $urlParts = parse_url($url['url']);
            $url['urlShort']
                = isset($urlParts['host']) ? $urlParts['host'] : $url['url'];
        }
        return $urls;
    }

    /**
     * Get the item's source.
     *
     * @return array
     */
    public function getSource()
    {
        return $this->fields['source'] ?: null;
    }

    /**
     * Returns an array of parameter to send to Finna's cover generator.
     * Fallbacks to VuFind's getThumbnail if no record image with the
     * given index was found.
     *
     * @param string $size  Size of thumbnail
     * @param int    $index Image index
     *
     * @return array|bool
     */
    public function getRecordImage($size = 'small', $index = 0)
    {
        $params = parent::getThumbnail($size);
        if ($params && !is_array($params)) {
            $params = ['url' => $params];
        }
        return $params;
    }

    /**
     * Return record format.
     *
     * @return string.
     */
    public function getRecordType()
    {
        return null;
    }

    /**
     * Return image rights.
     *
     * @param string $language Language
     *
     * @return mixed array with keys:
     *   'copyright'  Copyright (e.g. 'CC BY 4.0') (optional)
     *   'description Human readable description (array)
     *   'link'       Link to copyright info
     *   or false if the record contains no images
     */
    public function getImageRights($language)
    {
        return false;
    }

    /**
     * Get default OpenURL parameters.
     *
     * @return array
     */
    protected function getDefaultOpenUrlParams()
    {
        $params = parent::getDefaultOpenUrlParams();
        if (isset($this->fields['isbn'])) {
            $isbn = $this->fields['isbn'];
            if (is_array($isbn) && !empty($isbn)) {
                $isbn = $isbn[0];
            }
            $params['rft.isbn'] = $isbn;
        }
        if (isset($this->fields['issn'])) {
            $issn = $this->fields['issn'];
            if (is_array($issn) && !empty($issn)) {
                $issn = $issn[0];
            }
            $params['rft.issn'] = $issn;
        }
        if (isset($this->fields['container_volume'])) {
            $params['rft.volume'] = $this->fields['container_volume'];
        }
        if (isset($this->fields['container_issue'])) {
            $params['rft.issue'] = $this->fields['container_issue'];
        }
        $params['rft.atitle'] = $params['rft.title'];
        return $params;
    }

    /**
     * Indicate whether export is disabled for a particular format.
     *
     * @param string $format Export format
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function exportDisabled($format)
    {
        // Support export for EndNote and RefWorks
        return !in_array($format, ['EndNote', 'RefWorks']);
    }

    /**
     * Returns true if the record supports real-time AJAX status lookups.
     *
     * @return bool
     */
    public function supportsAjaxStatus()
    {
        return false;
    }

    /**
     * Returns true if record links should be proxified.
     *
     * @return bool
     */
    public function proxyLinks()
    {
        return $this->fields['proxy'];
    }
}
