<?php
/**
 * Additional functionality for Finna Solr records.
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
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Additional functionality for Finna Solr records.
 *
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
trait SolrFinna
{

    /**
     * Returns an array of parameter to send to Finna's cover generator.
     * Fallbacks to VuFind's getThumbnail if no record image with the 
     * given index was found.
     *
     * @param string $size  Size of thumbnail
     * @param int    $index Image index
     *
     * @return string|array|bool
     */
    public function getRecordImage($size = 'small', $index = 0)
    {        
        if ($urls = $this->getAllThumbnails($size)) {
            $urls = array_keys($urls);
            if ($index == 0) {
                $url = $urls[0];
            } else {
                if (isset($urls[$index])) {
                    $url = $urls[$index];
                }
            }
            if (!is_array($url)) {
                return array('id' => $this->getUniqueId(), 'url' => $url);
            }
        }
        return parent::getThumbnail($size);
    }
    
    /**
     * Return building from index.
     *
     * @return string
     */
    public function getBuilding()
    {
        return $this->fields['building'];
    }

    /**
     * Return record format.
     *
     * @return string.
     */
    public function getRecordType()
    {     
        return $this->fields['recordtype'];
    }

    /**
     * Return an associative array of image URLs associated with this record
     * (key = URL, value = description), if available; false otherwise.
     *
     * @param string $size Size of requested images
     *
     * @return mixed
     */
    public function getAllThumbnails($size = 'large')
    {
        return false;
    }

    /**
     * Return access restriction notes for the record.
     *
     * @return array
     */
    public function getAccessRestrictions()
    {
        // Not currently stored in the Solr index
        return array();
    }

    /**
     * Return type of access restriction for the record.
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0')
     *   'link'        Link to copyright info, see IndexRecord::getRightsLink
     *   or false if no access restriction type is defined.
     */
    public function getAccessRestrictionsType()
    {
        return false;
    }

    /**
     * Return image rights.
     *
     * @return mixed array with keys:
     *   'copyright'  Copyright (e.g. 'CC BY 4.0') (optional)
     *   'description Human readable description (array)
     *   'link'       Link to copyright info
     *   or false if the record contains no images
     */
    public function getImageRights()
    {
        return false;
    }
    
    /**
     * Return URL to copyright information.
     *
     * @param string $copyright Copyright
     * @param string $language  Language
     *
     * @return mixed URL or false if no URL for the given copyright
     */
    public function getRightsLink($copyright, $language)
    {
        if (isset($this->mainConfig['ImageRights'][$language][$copyright])) {
            return $this->mainConfig['ImageRights'][$language][$copyright];
        }
        return false;
    }
}
