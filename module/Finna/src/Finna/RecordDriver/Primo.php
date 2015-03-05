<?php
/**
 * Model for Primo Central records.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for Primo Central records.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class Primo extends \VuFind\RecordDriver\Primo
{
    /**
     * Record metadata
     *
     * @var \SimpleXMLElement
     */
    protected $simpleXML;

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        $rec = $this->getSimpleXML();
        if (isset($rec->search->creationdate)) {
            return array($rec->search->creationdate);
        }
    }

    /**
     * Get default OpenURL parameters.
     *
     * @return array|false
     */
    protected function getDefaultOpenURLParams()
    {
        if (!isset($this->mainConfig->OpenURL->rfr_id)
            || empty($this->mainConfig->OpenURL->rfr_id)
        ) {
            return false;
        }

        $link = $this->fields['url'];

        if ($link && strpos($link, 'url_ver=Z39.88-2004') !== false) {
            parse_str(substr($link, strpos($link, '?') + 1), $params);
            $params['rfr_id'] = $this->mainConfig->OpenURL->rfr_id;
            $params['rft.date'] = implode('', $this->getPublicationDates());
            return $params;
        }

        return false;
    }

    /**
     * Get the original record as a SimpleXML object
     *
     * @return SimpleXMLElement The record as SimpleXML
     */
    protected function getSimpleXML()
    {
        if ($this->simpleXML !== null) {
            return $this->simpleXML;
        }
        $this->simpleXML = new \SimpleXmlElement($this->fields['fullrecord']);

        return $this->simpleXML;
    }
}
