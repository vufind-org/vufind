<?php
/**
 * Additional functionality for SolrForward and SolrForwardAuth records.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library 2019.
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
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Additional functionality for SolrForward and SolrForwardAuth records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
trait SolrForwardTrait
{
    /**
     * Return an array of image URLs associated with this record with keys:
     * - url         Image URL
     * - description Description text
     * - rights      Rights
     *   - copyright   Copyright (e.g. 'CC BY 4.0') (optional)
     *   - description Human readable description (array)
     *   - link        Link to copyright info
     *
     * @param string $language   Language for copyright information
     * @param bool   $includePdf Whether to include first PDF file when no image
     *                           links are found
     *
     * @return array
     */
    public function getAllImages($language = 'fi', $includePdf = false)
    {
        $images = [];

        foreach ($this->getAllRecordsXML() as $xml) {
            foreach ($xml->ProductionEvent as $event) {
                $attributes = $event->ProductionEventType->attributes();
                if (empty($attributes->{'elokuva-elonet-materiaali-kuva-url'})) {
                    continue;
                }
                $url = (string)$attributes->{'elokuva-elonet-materiaali-kuva-url'};
                if (!$this->isUrlLoadable($url, $this->getUniqueID())) {
                    continue;
                }
                if (!empty($xml->Title->PartDesignation->Value)) {
                    $partAttrs = $xml->Title->PartDesignation->Value->attributes();
                    $desc = (string)$partAttrs->{'kuva-kuvateksti'};
                } else {
                    $desc = '';
                }
                $rights = [];
                if (!empty($attributes->{'finna-kayttooikeus'})) {
                    $rights['copyright']
                        = (string)$attributes->{'finna-kayttooikeus'};
                    $link = $this->getRightsLink(
                        strtoupper($rights['copyright']), $language
                    );
                    if ($link) {
                        $rights['link'] = $link;
                    }
                }
                $images[] = [
                    'urls' => [
                        'small' => $url,
                        'medium' => $url,
                        'large' => $url
                    ],
                    'description' => $desc,
                    'rights' => $rights
                ];
            }
        }
        return $images;
    }
}
