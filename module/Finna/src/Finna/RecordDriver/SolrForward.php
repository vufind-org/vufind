<?php
/**
 * Model for FORWARD records in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for FORWARD records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrForward extends \VuFind\RecordDriver\SolrDefault
{
    use SolrFinna;

    /**
     * Non-presenter author relator codes.
     *
     * @var array
     */
    protected $nonPresenterAuthorRelators = [
        'A00', 'A03', 'A06', 'A50', 'A99', 'D01', 'D02', 'F01', 'F02'
    ];

    /**
     * Presenter author relator codes.
     *
     * @var array
     */
    protected $presenterAuthorRelators = [
        'E01'
    ];

    /**
     * Relator to RDA role mapping.
     *
     * @var array
     */
    protected $roleMap = [
        'A00' => 'oth',
        'A03' => 'aus',
        'A06' => 'cmp',
        'A50' => 'aud',
        'A99' => 'oth',
        'D01' => 'fmp',
        'D02' => 'drt',
        'E01' => 'act',
        'F01' => 'cng',
        'F02' => 'edt'
    ];

    /**
     * ELONET role to RDA role mapping.
     *
     * @var array
     */
    protected $elonetRoleMap = [
        'dialogi' => 'aud',
        'lavastus' => 'std',
        'puvustus' => 'cst',
        'tuotannon suunnittelu' => 'prs',
        'tuotantopäällikkö' => 'pmn',
        'muusikko' => 'mus',
        'äänitys' => 'rce'
    ];

    /**
     * Record metadata
     *
     * @var \SimpleXMLElement
     */
    protected $lazyRecordXML;

    /**
     * Return all subject headings
     *
     * @return array
     */
    public function getAllSubjectHeadings()
    {
        $subjects = [];
        $xml = $this->getRecordXML();
        foreach ($xml->ProductionEvent as $event) {
            $attributes = $event->ProductionEventType->attributes();
            if (!empty($attributes{'elokuva-asiasana'})) {
                $subjects[] = $attributes{'elokuva-asiasana'};
            }
        }
        return $subjects;
    }

    /**
     * Return an associative array of image URLs associated with this record
     * (key = URL, value = description).
     *
     * @param string $size Size of requested images
     *
     * @return array
     */
    public function getAllThumbnails($size = 'large')
    {
        $urls = [];
        foreach ($this->getRecordXML()
            ->xpath('file') as $node
        ) {
            $attributes = $node->attributes();
            if ($attributes->bundle
                && $attributes->bundle == 'ORIGINAL' && $size == 'large'
                || $attributes->bundle == 'THUMBNAIL' && $size != 'large'
            ) {
                $mimes = ['image/jpeg', 'image/png'];
                $url = isset($attributes->href)
                    ? (string)$attributes->href : (string)$node;

                if ($size == 'large') {
                    if (isset($attributes->type)) {
                        if (!in_array($attributes->type, $mimes)) {
                            continue;
                        }
                    } else {
                        if (!preg_match('/\.(jpg|png)$/i', $url)) {
                            continue;
                        }
                    }
                }
                $urls[$url] = $url;
            }
        }
        return $urls;
    }

    /**
     * Get an array of alternative titles for the record.
     *
     * @return array
     */
    public function getAlternativeTitles()
    {
        return isset($this->fields['title_alt']) ? $this->fields['title_alt'] : [];
    }

    /**
     * Return aspect ratio
     *
     * @return string
     */
    public function getAspectRatio()
    {
        return $this->getProductionEventAttribute('elokuva-kuvasuhde');
    }

    /**
     * Get assistants
     *
     * @return array
     */
    public function getAssistants()
    {
        return $this->getAgentsWithActivityAttribute('elokuva-avustajat');
    }

    /**
     * Return type
     *
     * @return string
     */
    public function getType()
    {
        return trim(
            $this->getProductionEventAttribute('elokuva-laji1fin') . ' '
            . $this->getProductionEventAttribute('elokuva-laji2fin')
        );
    }

    /**
     * Return color
     *
     * @return string
     */
    public function getColor()
    {
        return $this->getProductionEventAttribute('elokuva-alkupvari');
    }

    /**
     * Return color system
     *
     * @return string
     */
    public function getColorSystem()
    {
        return $this->getProductionEventAttribute('elokuva-alkupvarijarjestelma');
    }

    /**
     * Get country
     *
     * @return string
     */
    public function getCountry()
    {
        $xml = $this->getRecordXML();
        return isset($xml->CountryOfReference->Country->RegionName)
            ? $xml->CountryOfReference->Country->RegionName : '';
    }

    /**
     * Return description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->getProductionEventAttribute('elokuva-sisaltoseloste');
    }

    /**
     * Get distributors
     *
     * @return array
     */
    public function getDistributors()
    {
        $result = [];
        $xml = $this->getRecordXML();
        foreach ($xml->HasAgent as $agent) {
            if ((string)$agent->Activity == 'A99'
                && !empty($agent->Activity->attributes()->{'elokuva-elolevittaja'})
            ) {
                $attributes = $agent->AgentName->attributes();
                $result[] = [
                    'name' => (string)$agent->AgentName,
                    'date' => (string)$attributes->{'elokuva-elolevittaja-vuosi'},
                    'method'
                        => (string)$attributes->{'elokuva-elolevittaja-levitystapa'}
                ];
            }
        }
        return $result;
    }

    /**
     * Get funders
     *
     * @return array
     */
    public function getFunders()
    {
        return $this->getAgentsWithActivityAttribute(
            'elokuva-elorahoitusyhtio',
            ['amount' => 'elokuva-elorahoitusyhtio-summa']
        );
    }

    /**
     * Return music information
     *
     * @return string
     */
    public function getMusicInfo()
    {
        $result = $this->getProductionEventAttribute('elokuva-musiikki');
        $result = preg_replace('/(\d+\. )/', '<br/>\1', $result);
        if (strncmp($result, '<br/>', 5) == 0) {
            $result = substr($result, 5);
        }
        return $result;
    }

    /**
     * Get all authors apart from presenters
     *
     * @return array
     */
    public function getNonPresenterAuthors()
    {
        return $this->getAuthorsByRelators($this->nonPresenterAuthorRelators);
    }

    /**
     * Return playing times
     *
     * @return string
     */
    public function getPlayingTimes()
    {
        return [$this->getProductionEventAttribute('elokuva-alkupkesto')];
    }

    /**
     * Get presenters
     *
     * @return array
     */
    public function getPresenters()
    {
        return [
            'presenters'
                => $this->getAuthorsByRelators($this->presenterAuthorRelators)
        ];
    }

    /**
     * Get producers
     *
     * @return array
     */
    public function getProducers()
    {
        return $this->getAgentsWithActivityAttribute('elokuva-elotuotantoyhtio');
    }

    /**
     * Return sound
     *
     * @return string
     */
    public function getSound()
    {
        return $this->getProductionEventAttribute('elokuva-alkupaani');
    }

    /**
     * Return sound system
     *
     * @return string
     */
    public function getSoundSystem()
    {
        return $this->getProductionEventAttribute('elokuva-alkupaanijarjestelma');
    }

    /**
     * Check if a datasource has patron functions in order to show or hide the
     * patron login
     *
     * @return bool
     */
    public function hasPatronFunctions()
    {
        return false;
    }

    /**
     * Set raw data to initialize the object.
     *
     * @param mixed $data Raw data representing the record; Record Model
     * objects are normally constructed by Record Driver objects using data
     * passed in from a Search Results object.  The exact nature of the data may
     * vary depending on the data source -- the important thing is that the
     * Record Driver + Search Results objects work together correctly.
     *
     * @return void
     */
    public function setRawData($data)
    {
        parent::setRawData($data);
        $this->lazyRecordXML = null;
    }

    /**
     * Get all agents that have the given attribute in Activity element
     *
     * @param string $attribute    Attribute to look for
     * @param array  $includeAttrs Attributes to include from AgentName
     *
     * @return array
     */
    protected function getAgentsWithActivityAttribute($attribute, $includeAttrs = [])
    {
        $result = [];
        $xml = $this->getRecordXML();
        foreach ($xml->HasAgent as $agent) {
            $attributes = $agent->Activity->attributes();
            if (!empty($attributes->{$attribute})) {
                $item = [
                    'name' => (string)$agent->AgentName
                ];
                $agentAttrs = $agent->AgentName->attributes();
                foreach ($includeAttrs as $key => $attr) {
                    if (!empty($agentAttrs{$attr})) {
                        $item[$key] = (string)$agentAttrs{$attr};
                    }
                }
                $result[] = $item;
            }
        }
        return $result;
    }

    /**
     * Get authors by relator codes
     *
     * @param array $relators Array of relator codes
     *
     * @return array
     */
    protected function getAuthorsByRelators($relators)
    {
        $result = [];
        $xml = $this->getRecordXML();
        foreach ($xml->HasAgent as $agent) {
            $relator = (string)$agent->Activity;
            if (!in_array($relator, $relators)) {
                continue;
            }
            $normalizedRelator = mb_strtoupper($relator, 'UTF-8');
            $role = isset($this->roleMap[$normalizedRelator])
                    ? $this->roleMap[$normalizedRelator] : $relator;

            $attributes = $agent->Activity->attributes();
            if (in_array($normalizedRelator, ['A00', 'A99'])) {
                if (!empty($attributes->{'elokuva-elolevittaja'})
                ) {
                    continue;
                }
                if (!empty($attributes->{'elokuva-avustajat'})
                    || !empty($attributes->{'elokuva-elotuotantoyhtio'})
                    || !empty($attributes->{'elokuva-elorahoitusyhtio'})
                    || !empty($attributes->{'elokuva-elolaboratorio'})
                ) {
                    continue;
                }
                if (!empty($attributes->{'elokuva-elotekija-tehtava'})) {
                    $role = (string)$attributes->{'elokuva-elotekija-tehtava'};
                    if (isset($this->elonetRoleMap[$role])) {
                        $role = $this->elonetRoleMap[$role];
                    }
                }
            }

            $nameAttrs = $agent->AgentName->attributes();
            $roleName = '';
            $uncredited = false;
            $uncreditedRole = 'elokuva-elokreditoimatonnayttelija-rooli';
            if (!empty($nameAttrs->{'elokuva-elotekija-rooli'})) {
                $roleName = $nameAttrs->{'elokuva-elotekija-rooli'};
            } elseif (!empty($nameAttrs->{$uncreditedRole})) {
                $roleName = $nameAttrs->{$uncreditedRole};
                $uncredited = true;
            }

            $result[] = [
                'name' => (string)$agent->AgentName,
                'role' => $role,
                'roleName' => $roleName,
                'uncredited' => $uncredited
            ];
        }
        return $result;
    }

    /**
     * Return a production event attribute
     *
     * @param string $attribute Attribute name
     *
     * @return string
     */
    protected function getProductionEventAttribute($attribute)
    {
        $xml = $this->getRecordXML();
        foreach ($xml->ProductionEvent as $event) {
            $attributes = $event->ProductionEventType->attributes();
            if (!empty($attributes{$attribute})) {
                return (string)$attributes{$attribute};
            }
        }
        return '';
    }

    /**
     * Get the original record as a SimpleXML object
     *
     * @return SimpleXMLElement The record as SimpleXML
     */
    protected function getRecordXML()
    {
        if ($this->lazyRecordXML === null) {
            $xml = new \SimpleXMLElement(
                $this->fields['fullrecord']
            );
            $nodes = $xml->children();
            $this->lazyRecordXML = reset($nodes);
        }
        return $this->lazyRecordXML;
    }
}
