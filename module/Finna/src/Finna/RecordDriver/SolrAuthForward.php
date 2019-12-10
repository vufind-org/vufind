<?php
/**
 * Model for Forward authority records in Solr.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for Forward authority records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrAuthForward extends SolrAuthDefault
{
    use XmlReaderTrait;

    /**
     * Get an array of alternative titles for the record.
     *
     * @return array
     */
    public function getAlternativeTitles()
    {
        $doc = $this->getMainElement();

        $names = [];
        foreach ($doc->CAgentName as $name) {
            if ((string)$name->AgentNameType === '00') {
                $attr = $name->AgentNameType->attributes();
                $name = (string)$name->PersonName;
                if (isset($attr->{'henkilo-muu_nimi-tyyppi'})) {
                    $type = (string)$attr->{'henkilo-muu_nimi-tyyppi'};
                    $name .= " ($type)";
                }
                $names[] = $name;
            }
        }
        return $names;
    }

    /**
     * Return description
     *
     * @return string|null
     */
    public function getSummary()
    {
        return explode(
            PHP_EOL,
            $this->getBiographicalNote('henkilo-biografia-tyyppi', 'biografia')
        );
    }

    /**
     * Return birth date and place.
     *
     * @param boolean $force Return established date for corporations?
     *
     * @return string
     */
    public function getBirthDate($force = false)
    {
        if (!$this->isPerson() && !$force) {
            return '';
        }

        if ($date = $this->getAgentDate('birth')) {
            return $this->formatDateAndPlace($date);
        }
        return '';
    }

    /**
     * Return death date and place.
     *
     * @param boolean $force Return terminated date for corporations?
     *
     * @return string
     */
    public function getDeathDate($force = false)
    {
        if (!$this->isPerson() && !$force) {
            return '';
        }

        if ($date = $this->getAgentDate('death')) {
            return $this->formatDateAndPlace($date);
        }
        return '';
    }

    /**
     * Return corporation establishment date and place.
     *
     * @return string
     */
    public function getEstablishedDate()
    {
        if ($this->isPerson()) {
            return '';
        }
        return $this->getBirthDate(true);
    }

    /**
     * Return corporation termination date and place.
     *
     * @return string
     */
    public function getTerminatedDate()
    {
        if ($this->isPerson()) {
            return '';
        }
        return $this->getDeathDate(true);
    }

    /**
     * Format death/birth date and place.
     *
     * @param array $date Array with keys 'date' and possibly 'place'
     *
     * @return string
     */
    protected function formatDateAndPlace($date)
    {
        $result = $date['date'];
        if ($place = ($date['place'] ?? null)) {
            $result .= " ($place)";
        }
        return $result;
    }

    /**
     * Return awards.
     *
     * @return string[]
     */
    public function getAwards()
    {
        return explode(
            PHP_EOL,
            $this->getBiographicalNote('henkilo-biografia-tyyppi', 'palkinnot')
        );
    }

    /**
     * Return biographical note.
     *
     * @param string $type    Note type
     * @param string $typeVal Note type value
     *
     * @return string
     */
    protected function getBiographicalNote($type, $typeVal)
    {
        $doc = $this->getMainElement();
        if (isset($doc->BiographicalNote)) {
            foreach ($doc->BiographicalNote as $bio) {
                $attr = $bio->attributes();
                if (isset($attr->{$type})
                    && (string)$attr->{$type} === $typeVal
                ) {
                    return (string)$bio;
                }
            }
        }
        return null;
    }

    /**
     * Get the main metadata element
     *
     * @return SimpleXMLElement
     */
    protected function getMainElement()
    {
        $nodes = (array)$this->getXmlRecord()->children();
        $node = reset($nodes);
        return is_array($node) ? reset($node) : $node;
    }

    /**
     * Return agent event date.
     *
     * @param string $type Date event type
     *
     * @return string
     */
    protected function getAgentDate($type)
    {
        $doc = $this->getMainElement();
        if (isset($doc->AgentDate)) {
            foreach ($doc->AgentDate as $d) {
                if (isset($d->AgentDateEventType)) {
                    $dateType = (int)$d->AgentDateEventType;
                    $date = (string)$d->DateText;
                    $place =  (string)$d->LocationName;
                    if (($type === 'birth' && $dateType === 51)
                        || ($type == 'death' && $dateType === 52)
                    ) {
                        return ['date' => $date, 'place' => $place];
                    }
                }
            }
        }

        return null;
    }
}
