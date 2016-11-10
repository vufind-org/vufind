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
        'A00', 'A03', 'A06', 'A50', 'A99', 'D01', 'D02', 'E10', 'F01', 'F02',
        'anm', 'aud', 'chr', 'cnd', 'cst', 'exp', 'fds', 'lgd', 'oth', 'pmn', 'prn',
        'sds', 'std', 'trl', 'wst'
    ];

    /**
     * Presenter author relator codes.
     *
     * @var array
     */
    protected $presenterAuthorRelators = [
        'E01', 'E99', 'cmm'
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
        'E10' => 'fmp',
        'F01' => 'cng',
        'F02' => 'flm'
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
     * @var array
     */
    protected $lazyRecordXML;

    /**
     * Return access restriction notes for the record.
     *
     * @return array
     */
    public function getAccessRestrictions()
    {
        $results = [];
        foreach ($this->getAllRecordsXML() as $xml) {
            foreach ($xml->ProductionEvent as $event) {
                if ($event->ProductionEventType) {
                    $attributes = $event->ProductionEventType->attributes();
                    if (!empty($attributes['finna-kayttooikeus'])) {
                        $results[(string)$attributes['finna-kayttooikeus']] = 1;
                    }
                }
            }
        }
        return array_keys($results);
    }

    /**
     * Return type of access restriction for the record.
     *
     * @param string $language Language
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0')
     *   'link'        Link to copyright info, see IndexRecord::getRightsLink
     *   or false if no access restriction type is defined.
     */
    public function getAccessRestrictionsType($language)
    {
        foreach ($this->getAllRecordsXML() as $xml) {
            foreach ($xml->ProductionEvent as $event) {
                if ($event->ProductionEventType) {
                    $attributes = $event->ProductionEventType->attributes();
                    if (!empty($attributes['finna-kayttooikeus'])) {
                        $type = (string)$attributes['finna-kayttooikeus'];
                        $result = ['copyright' => $type];
                        $link = $this->getRightsLink(strtoupper($type), $language);
                        if ($link) {
                            $result['link'] = $link;
                        }
                        return $result;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Return all subject headings
     *
     * @return array
     */
    public function getAllSubjectHeadings()
    {
        $results = [];
        foreach ($this->getRecordXML()->SubjectTerms as $subjectTerms) {
            foreach ($subjectTerms->Term as $term) {
                $results[] = [$term];
            }
        }
        return $results;
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
        $images = [];

        foreach ($this->getAllRecordsXML() as $xml) {
            foreach ($xml->ProductionEvent as $event) {
                $attributes = $event->ProductionEventType->attributes();
                if (!empty($attributes{'elokuva-elonet-materiaali-kuva-url'})) {
                    $url = (string)$attributes{'elokuva-elonet-materiaali-kuva-url'};
                    if (!empty($xml->Title->PartDesignation->Value)) {
                        $attributes = $xml->Title->PartDesignation->Value
                            ->attributes();
                        $desc = (string)$attributes{'kuva-kuvateksti'};
                    } else {
                        $desc = '';
                    }
                    $images[$url] = $desc;
                }
            }
        }
        return $images;
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        $results = [];
        foreach ($this->getAllRecordsXML() as $xml) {
            foreach ($xml->ProductionEvent as $event) {
                $attributes = $event->ProductionEventType->attributes();
                if (empty($attributes->{'elokuva-elonet-materiaali-video-url'})) {
                    continue;
                }
                $url = (string)$attributes->{'elokuva-elonet-materiaali-video-url'};
                $type = '';
                $description = '';
                if ($xml->Title->PartDesignation->Value) {
                    $attributes = $xml->Title->PartDesignation->Value->attributes();
                    $type = ucfirst((string)$attributes->{'video-tyyppi'});
                    $description = (string)$attributes->{'video-lisatieto'};
                }
                $description = $description ? $description : $type;
                if ($this->urlBlacklisted($url, $description)) {
                    continue;
                }

                $embed = '';
                if (strpos($url, 'elonet.fi') > 0 && strpos($url, '/video/') > 0) {
                    $url = str_replace('/video/', '/embed/', $url);
                    $url = str_replace('http://', '//', $url);
                    $embed = 'iframe';
                }

                $results[] = [
                    'url' => $url,
                    'desc' => $description,
                    'embed' => $embed
                ];
            }
        }
        return $results;
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
     * Get award notes for the record.
     *
     * @return array
     */
    public function getAwards()
    {
        $results = [];
        foreach ($this->getRecordXML()->Award as $award) {
            $results[] = (string)$award;
        }
        return $results;
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
        return !empty($xml->CountryOfReference->Country->RegionName)
            ? (string)$xml->CountryOfReference->Country->RegionName : '';
    }

    /**
     * Return descriptions
     *
     * @return array
     */
    public function getDescription()
    {
        list($locale) = explode('-', $this->getTranslatorLocale());

        $result = $this->getDescriptionData('Synopsis', $locale);
        if (empty($result)) {
            $result = $this->getDescriptionData('Synopsis');
        }
        return $result;
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
     * Get general notes on the record.
     *
     * @return array
     */
    public function getGeneralNotes()
    {
        return $this->getProductionEventElement('elokuva_huomautukset');
    }

    /**
     * Return image description.
     *
     * @param int $index Image index
     *
     * @return string
     */
    public function getImageDescription($index = 0)
    {
        $images = array_values($this->getAllThumbnails());
        if (!empty($images[$index])) {
            return $images[$index];
        }
        return '';
    }

    /**
     * Return image rights.
     *
     * @param string $language Language
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0') (optional)
     *   'description' Human readable description (array)
     *   'link'        Link to copyright info
     *   or false if the record contains no images
     */
    public function getImageRights($language)
    {
        if (!$this->getAllThumbnails()) {
            return false;
        }

        $rights = [];
        if ($type = $this->getAccessRestrictionsType($language)) {
            $rights['copyright'] = $type['copyright'];
            if (isset($type['link'])) {
                $rights['link'] = $type['link'];
            }
        }

        return isset($rights['copyright']) ? $rights : false;
    }

    /**
     * Return music information
     *
     * @return string
     */
    public function getMusicInfo()
    {
        $result = $this->getProductionEventElement('elokuva_musiikki');
        $result = reset($result);
        if (!$result) {
            return '';
        }
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
     * Get online URLs
     *
     * @param bool $raw Whether to return raw data
     *
     * @return array
     */
    public function getOnlineURLs($raw = false)
    {
        if (!isset($this->fields['online_urls_str_mv'])) {
            return [];
        }
        $urls = $this->fields['online_urls_str_mv'];
        foreach ($urls as &$urlJson) {
            $url = json_decode($urlJson, true);
            if (strpos($url['url'], 'elonet.fi') > 0
                && strpos($url['url'], '/video/') > 0
            ) {
                $url['url'] = str_replace('/video/', '/embed/', $url['url']);
                $url['url'] = str_replace('http://', '//', $url['url']);
                $url['embed'] = 'iframe';
                $urlJson = json_encode($url);
            }
        }
        return $raw ? $urls : $this->mergeURLArray($urls, true);
    }

    /**
     * Return original work information
     *
     * @return string
     */
    public function getOriginalWork()
    {
        return $this->getProductionEventAttribute('elokuva-alkuperaisteos');
    }

    /**
     * Return playing times
     *
     * @return array
     */
    public function getPlayingTimes()
    {
        $str = $this->getProductionEventAttribute('elokuva-alkupkesto');
        return $str ? [$str] : [];
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
     * Return summary
     *
     * @return array
     */
    public function getSummary()
    {
        list($locale) = explode('-', $this->getTranslatorLocale());

        $result = $this->getDescriptionData('Content description', $locale);
        if (empty($result)) {
            $result = $this->getDescriptionData('Content description');
        }
        return $result;
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
     * Get all original records as a SimpleXML object
     *
     * @return SimpleXMLElement The record as SimpleXML
     */
    protected function getAllRecordsXML()
    {
        if ($this->lazyRecordXML === null) {
            $xml = new \SimpleXMLElement($this->fields['fullrecord']);
            $records = (array)$xml->children();
            $records = reset($records);
            $this->lazyRecordXML = is_array($records) ? $records : [$records];
        }
        return $this->lazyRecordXML;
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
                if (!empty($attributes->{'finna-activity-text'})) {
                    $role = (string)$attributes->{'finna-activity-text'};
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
            } elseif (!empty($nameAttrs->{'elokuva-elonayttelija-rooli'})) {
                $roleName = $nameAttrs->{'elokuva-elonayttelija-rooli'};
            } elseif (!empty($nameAttrs->{$uncreditedRole})) {
                $roleName = $nameAttrs->{$uncreditedRole};
                $uncredited = true;
            }

            $name = (string)$agent->AgentName;
            if (empty($name)
                && !empty($nameAttrs->{'elokuva-elokreditoimatontekija-nimi'})
            ) {
                $name = (string)$nameAttrs->{'elokuva-elokreditoimatontekija-nimi'};
            }

            $result[] = [
                'name' => $name,
                'role' => $role,
                'roleName' => $roleName,
                'uncredited' => $uncredited
            ];
        }
        return $result;
    }

    /**
     * Get descriptions, optionally only in given language
     *
     * @param string $type     Description type
     * @param string $language Optional language code
     *
     * @return array
     */
    protected function getDescriptionData($type, $language = null)
    {
        $results = [];
        foreach ($this->getRecordXML()->ContentDescription as $description) {
            if (null !== $language && (string)$description->Language !== $language) {
                continue;
            }
            if ((string)$description->DescriptionType == $type
                && !empty($description->DescriptionText)
            ) {
                $results[] = (string)$description->DescriptionText;
            }
        }
        return $results;
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
     * Return a production event element contents as an array
     *
     * @param string $element Element name
     *
     * @return array
     */
    protected function getProductionEventElement($element)
    {
        $results = [];
        $xml = $this->getRecordXML();
        foreach ($xml->ProductionEvent as $event) {
            if (!empty($event->$element)) {
                foreach ($event->$element as $item) {
                    $results[] = (string)$item;
                }
            }
        }
        return $results;
    }

    /**
     * Get the original main record as a SimpleXML object
     *
     * @return SimpleXMLElement The record as SimpleXML
     */
    protected function getRecordXML()
    {
        $records = $this->getAllRecordsXML();
        return reset($records);
    }
}
