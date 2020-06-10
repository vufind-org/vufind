<?php
/**
 * Model for EAC-CPF records in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2012-2019.
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
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for EAC-CPF records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrAuthEacCpf extends SolrAuthDefault
{
    use SolrAuthFinnaTrait;
    use XmlReaderTrait;

    /**
     * Get authority title
     *
     * @return string|null
     */
    public function getTitle()
    {
        $record = $this->getXmlRecord();
        return isset($record->cpfDescription->identity->nameEntry->part[0])
            ? (string)$record->cpfDescription->identity->nameEntry->part[0]
            : null;
    }

    /**
     * Get an array of alternative titles for the record.
     *
     * @return array
     */
    public function getAlternativeTitles()
    {
        $titles = [];
        $path = 'cpfDescription/identity/nameEntryParallel/nameEntry';
        foreach ($this->getXmlRecord()->xpath($path) as $name) {
            $titles[] = $name->part[0];
        }
        return $titles;
    }

    /**
     * Return description
     *
     * @return array|null
     */
    public function getSummary()
    {
        $record = $this->getXmlRecord();
        if (isset($record->cpfDescription->description->biogHist->p)) {
            return [(string)$record->cpfDescription->description->biogHist->p];
        }
        return null;
    }

    /**
     * Set preferred language for display strings.
     *
     * @param string $language Language
     *
     * @return void
     */
    public function setPreferredLanguage($language)
    {
    }
}
