<?php
/**
 * Model for FORWARD records in Solr.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2017.
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
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
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
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrForward extends \VuFind\RecordDriver\SolrDefault
{
    use SolrFinnaTrait, SolrForwardTrait {
        SolrForwardTrait::getAllImages insteadof SolrFinnaTrait;
    }

    /**
     * Non-presenter author relator codes.
     *
     * @var array
     */
    protected $nonPresenterAuthorRelators = [
        'a00', 'a01', 'a03', 'a06', 'a50', 'a99',
        'd01', 'd02', 'd99',
        'e02', 'e03', 'e04', 'e05', 'e06', 'e08',
        'f01', 'f02', 'f99',
        'cmp', 'cph', 'exp', 'fds', 'fmp', 'rce', 'wst', 'oth', 'prn',
        // These are copied from Marc
        'act', 'anm', 'ann', 'arr', 'acp', 'ar', 'ard', 'aft', 'aud', 'aui', 'aus',
        'bjd', 'bpd', 'cll', 'ctg', 'chr', 'cng', 'clb', 'clr', 'cwt', 'com',
        'cpl', 'cpt', 'cpe', 'ccp', 'cnd', 'cos', 'cot', 'coe', 'cts', 'ctt', 'cte',
        'ctb', 'crp', 'cst', 'cov', 'cur', 'dnc', 'dtc', 'dto', 'dfd', 'dft', 'dfe',
        'dln', 'dpc', 'dsr', 'dis', 'drm', 'edt', 'elt', 'egr', 'etr', 'fac',
        'fld', 'flm', 'frg', 'ilu', 'ill', 'ins', 'itr', 'ivr', 'ldr', 'lsa', 'led',
        'lil', 'lit', 'lie', 'lel', 'let', 'lee', 'lbt', 'lgd', 'ltg', 'lyr', 'mrb',
        'mte', 'msd', 'mus', 'nrt', 'opn', 'org', 'pta', 'pth', 'prf', 'pht', 'ptf',
        'ptt', 'pte', 'prt', 'pop', 'prm', 'pro', 'pmn', 'prd', 'prg', 'pdr', 'pbd',
        'ppt', 'ren', 'rpt', 'rth', 'rtm', 'res', 'rsp', 'rst', 'rse', 'rpy', 'rsg',
        'rev', 'rbr', 'sce', 'sad', 'scr', 'scl', 'spy', 'std', 'sng', 'sds', 'spk',
        'stm', 'str', 'stl', 'sht', 'ths', 'trl', 'tyd', 'tyg', 'vdg', 'voc', 'wde',
        'wdc', 'wam'
    ];

    /**
     * Primary author relator codes (mapped)
     *
     * @var array
     */
    protected $primaryAuthorRelators = ['drt'];

    /**
     * Presenter author relator codes.
     *
     * @var array
     */
    protected $presenterAuthorRelators = [
        'e01', 'e99', 'cmm'
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
        'E04' => 'spk',
        'E10' => 'pro',
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
        'lavastaja' => 'std',
        'puvustus' => 'cst',
        'tuotannon suunnittelu' => 'prs',
        'tuotantopäällikkö' => 'pmn',
        'muusikko' => 'mus',
        'selostaja' => 'spk',
        'valokuvaaja' => 'pht',
        'valonmääritys' => 'lgd',
        'äänitys' => 'rce'
    ];

    /**
     * Record metadata
     *
     * @var array
     */
    protected $lazyRecordXML;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $mainConfig     VuFind main configuration (omit for
     * built-in defaults)
     * @param \Zend\Config\Config $recordConfig   Record-specific configuration file
     * (omit to use $mainConfig as $recordConfig)
     * @param \Zend\Config\Config $searchSettings Search-specific configuration file
     */
    public function __construct($mainConfig = null, $recordConfig = null,
        $searchSettings = null
    ) {
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
        $this->searchSettings = $searchSettings;
    }

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
     * @param bool $extended Whether to return a keyed array with the following
     * keys:
     * - heading: the actual subject heading
     * - type: heading type
     * - source: source vocabulary
     *
     * @return array
     */
    public function getAllSubjectHeadings($extended = false)
    {
        $results = [];
        foreach ($this->getRecordXML()->SubjectTerms as $subjectTerms) {
            foreach ($subjectTerms->Term as $term) {
                if (!$extended) {
                    $results[] = [$term];
                } else {
                    $results[] = [
                        'heading' => [$term],
                        'type' => '',
                        'source' => ''
                    ];
                }
            }
        }
        return $results;
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
        return $this->getVideoUrls();
    }

    /**
     * Get an array of alternative titles for the record.
     *
     * @return array
     */
    public function getAlternativeTitles()
    {
        $xml = $this->getRecordXML();
        $identifyingTitle = (string)$xml->IdentifyingTitle;
        $result = [];
        foreach ($xml->Title as $title) {
            $titleText = (string)$title->TitleText;
            if ($titleText == $identifyingTitle) {
                continue;
            }
            $rel = $title->TitleRelationship;
            if ($rel && $type = $rel->attributes()->{'elokuva-elonimi-tyyppi'}) {
                $titleText .= " ($type)";
            } elseif ((string)$rel === 'working') {
                $titleText .= ' (' . $this->translate('working title') . ')';
            } elseif ($rel && (string)$rel == 'translated') {
                $lang = $title->TitleText->attributes()->lang;
                if ($lang) {
                    $lang = $this->translate($lang);
                    $titleText .= " ($lang)";
                }
            }
            $result[] = $titleText;
        }
        return $result;
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
        $type = $this->getProductionEventElement('elokuva_laji2fin');
        return trim(implode(', ', $type));
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

        $result = $this->getDescriptionData('Content description', $locale);
        if (empty($result)) {
            $result = $this->getDescriptionData('Content description');
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
        return $this->getAgentsWithActivityAttribute(
            'finna-activity-code=fds',
            [
                'date' => 'elokuva-elolevittaja-vuosi',
                'method' => 'elokuva-elolevittaja-levitystapa'
            ]
        );
    }

    /**
     * Get funders
     *
     * @return array
     */
    public function getFunders()
    {
        return $this->getAgentsWithActivityAttribute(
            'finna-activity-code=fnd',
            [
                'amount' => 'elokuva-elorahoitusyhtio-summa',
                'fundingType' => 'elokuva-elorahoitusyhtio-rahoitustapa'
            ]
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
     * Return image rights.
     *
     * @param string $language       Language
     * @param bool   $skipImageCheck Whether to check that images exist
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0') (optional)
     *   'description' Human readable description (array)
     *   'link'        Link to copyright info
     *   or false if the record contains no images
     */
    public function getImageRights($language, $skipImageCheck = false)
    {
        if (!$skipImageCheck && !$this->getAllImages()) {
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
     * Get all primary authors apart from presenters
     *
     * @return array
     */
    public function getNonPresenterPrimaryAuthors()
    {
        return $this->getNonPresenterAuthors(true);
    }

    /**
     * Get all secondary authors apart from presenters
     *
     * @return array
     */
    public function getNonPresenterSecondaryAuthors()
    {
        $authors = $this->getNonPresenterAuthors(false);
        $uncredited = $credited = [];
        foreach ($authors as $author) {
            if ($author['uncredited']) {
                $uncredited[] = $author;
            } else {
                $credited[] = $author;
            }
        }
        if (!empty($credited) || !empty($uncredited)) {
            return ['credited' => $credited, 'uncredited' => $uncredited];
        }
        return [];
    }

    /**
     * Get all authors apart from presenters
     *
     * @param mixed $primary Whether to return only primary or secondary authors or
     * all (null)
     *
     * @return array
     */
    public function getNonPresenterAuthors($primary = null)
    {
        $authors = $this->getAuthorsByRelators($this->nonPresenterAuthorRelators);
        if (null === $primary) {
            return $authors;
        }
        $result = [];
        foreach ($authors as $author) {
            $isPrimary = isset($author['role'])
                && in_array(
                    strtolower($author['role']), $this->primaryAuthorRelators
                );
            if ($isPrimary === $primary) {
                $result[] = $author;
            }
        }
        return $result;
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
        $videoUrls = $this->getVideoUrls();
        $urls = [];
        foreach ($videoUrls as $videoUrl) {
            $urls[] = json_encode($videoUrl);
        }
        if ($videoUrls && !empty($this->fields['online_urls_str_mv'])) {
            // Filter out video URLs
            foreach ($this->fields['online_urls_str_mv'] as $urlJson) {
                $url = json_decode($urlJson, true);
                if ($videoUrls && strpos($url['url'], 'elonet.fi') > 0
                    && strpos($url['url'], '/video/') > 0
                ) {
                    continue;
                }
                $urls[] = $urlJson;
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
     * Get all presenters
     *
     * @return array
     */
    public function getAllPresenters()
    {
        $credited = $this->getPresenters(false);
        $uncredited = $this->getPresenters(true);
        if (!empty($credited['presenters']) || !empty($uncredited['presenters'])) {
            return ['credited' => $credited, 'uncredited' => $uncredited];
        }
        return [];
    }

    /**
     * Get credited presenters
     *
     * @return array
     */
    public function getCreditedPresenters()
    {
        return $this->getPresenters(false);
    }

    /**
     * Get uncredited presenters
     *
     * @return array
     */
    public function getUncreditedPresenters()
    {
        return $this->getPresenters(true);
    }

    /**
     * Get presenters
     *
     * @param mixed $uncredited Whether to return only uncredited (true) or credited
     * authors (false) or all (null).
     *
     * @return array
     */
    public function getPresenters($uncredited = null)
    {
        $presenters = $this->getAuthorsByRelators($this->presenterAuthorRelators);
        if (null !== $uncredited) {
            $result = [];
            foreach ($presenters as $presenter) {
                $isUncredited = isset($presenter['uncredited'])
                    && $presenter['uncredited'];
                if ($isUncredited === $uncredited) {
                    $result[] = $presenter;
                }
            }
            $presenters = $result;
        }
        return [
            'presenters' => $presenters
        ];
    }

    /**
     * Return press review
     *
     * @return string
     */
    public function getPressReview()
    {
        $result = $this->getProductionEventElement('elokuva_lehdistoarvio');
        $result = reset($result);
        if (!$result) {
            return '';
        }
        return $result;
    }

    /**
     * Get producers
     *
     * @return array
     */
    public function getProducers()
    {
        $result = $this->getAgentsWithActivityAttribute('elokuva-elotuotantoyhtio');
        $result = array_merge(
            $result,
            $this->getAgentsWithActivityAttribute('finna-activity-code=E10')
        );
        return $result;
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

        $result = $this->getDescriptionData('Synopsis', $locale);
        if (empty($result)) {
            $result = $this->getDescriptionData('Synopsis');
        }
        return $result;
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
     * Return full record as filtered XML for public APIs.
     *
     * @return string
     */
    public function getFilteredXML()
    {
        $record = clone $this->getRecordXML();
        $remove = [];
        foreach ($record->ProductionEvent as $event) {
            $attributes = $event->attributes();
            if (isset($attributes->{'elonet-tag'})
                && 'lehdistoarvio' === (string)$attributes->{'elonet-tag'}
            ) {
                $remove[] = $event;
            }
        }
        foreach ($remove as $node) {
            unset($node[0]);
        }
        return $record->asXMl();
    }

    /**
     * Get all agents that have the given attribute in Activity element
     *
     * @param string $attribute    Attribute (and option value) to look for
     * @param array  $includeAttrs Attributes to include from AgentName
     *
     * @return array
     */
    protected function getAgentsWithActivityAttribute($attribute, $includeAttrs = [])
    {
        if (strpos($attribute, '=') > 0) {
            list($attribute, $requiredValue) = explode('=', $attribute, 2);
        }
        $result = [];
        $xml = $this->getRecordXML();
        foreach ($xml->HasAgent as $agent) {
            $attributes = $agent->Activity->attributes();
            if (!empty($attributes->{$attribute})
                && (empty($requiredValue)
                || $attributes->{$attribute} == $requiredValue)
            ) {
                $authId = isset($agent->AgentIdentifier)
                    ? (string)$agent->AgentIdentifier->IDTypeName . '_' .
                    (string)$agent->AgentIdentifier->IDValue
                    : null;
                $authType = (string)$agent->AgentIdentifier->IDTypeName ?? null;

                $item = [
                    'name' => (string)$agent->AgentName,
                    'id' => $authId,
                    'type' => $authType
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
     * Get all agents that have the given attribute in AgentName element
     *
     * @param string $attribute    Attribute to look for
     * @param array  $includeAttrs Attributes to include from AgentName
     *
     * @return array
     */
    protected function getAgentsWithNameAttribute($attribute, $includeAttrs = [])
    {
        $result = [];
        $xml = $this->getRecordXML();
        foreach ($xml->HasAgent as $agent) {
            $attributes = $agent->AgentName->attributes();
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
        $idx = 0;
        foreach ($xml->HasAgent as $agent) {
            $relator = (string)$agent->Activity;
            if (!in_array(strtolower($relator), $relators)) {
                continue;
            }

            if (null === ($role = $this->getAuthorRole($agent, $relator))) {
                continue;
            }

            $normalizedRelator = mb_strtoupper($relator, 'UTF-8');
            $primary = $normalizedRelator == 'D02'; // Director
            $nameAttrs = $agent->AgentName->attributes();
            $roleName = '';
            $uncredited = false;
            $uncreditedRole = 'elokuva-elokreditoimatonnayttelija-rooli';
            if (!empty($nameAttrs->{'elokuva-elotekija-rooli'})) {
                $roleName = (string)$nameAttrs->{'elokuva-elotekija-rooli'};
            } elseif (!empty($nameAttrs->{'elokuva-elonayttelija-rooli'})) {
                $roleName = (string)$nameAttrs->{'elokuva-elonayttelija-rooli'};
            } elseif (!empty($nameAttrs->{$uncreditedRole})) {
                $roleName = (string)$nameAttrs->{$uncreditedRole};
                $uncredited = true;
            } elseif (!empty($nameAttrs->{'elokuva-elokreditoimatontekija-nimi'})) {
                $uncredited = true;
            }

            $description = '';
            if (!empty($nameAttrs->{'elokuva-elotekija-selitys'})) {
                $description = (string)$nameAttrs->{'elokuva-elotekija-selitys'};
            }

            $name = (string)$agent->AgentName;
            if (empty($name)
                && !empty($nameAttrs->{'elokuva-elokreditoimatontekija-nimi'})
            ) {
                $name = (string)$nameAttrs->{'elokuva-elokreditoimatontekija-nimi'};
            }

            $authType = (string)$agent->AgentIdentifier->IDTypeName;
            $authId = (string)$agent->AgentIdentifier->IDTypeName . '_' .
                (string)$agent->AgentIdentifier->IDValue;

            ++$idx;
            $result[] = [
                'name' => $name,
                'role' => $role,
                'id' => $authId,
                'type' => $authType,
                'roleName' => $roleName,
                'description' => $description,
                'uncredited' => $uncredited,
                'idx' => $primary ? $idx : 10000 * $idx
            ];
        }

        // Sort the primary authors first using the idx
        usort(
            $result,
            function ($a, $b) {
                return $a['idx'] - $b['idx'];
            }
        );

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

    /**
     * Get video URLs
     *
     * @return array
     */
    protected function getVideoUrls()
    {
        // Get video URLs, if any
        $source = $this->getSource();
        $source = $source[0] ?? '';
        if (empty($this->recordConfig->Record->video_sources)) {
            return [];
        }
        $sourceConfigs = [];
        $sourcePriority = 0;
        foreach ($this->recordConfig->Record->video_sources as $current) {
            $settings = explode('|', $current, 4);
            if (!isset($settings[2]) || $source !== $settings[0]) {
                continue;
            }
            $sourceConfigs[] = [
                'mediaType' => $settings[1],
                'src' => $settings[2],
                'sourceTypes' => explode(',', $settings[3] ?? 'mp4'),
                'priority' => $sourcePriority++
            ];
        }
        if (empty($sourceConfigs)) {
            return [];
        }
        $posterSource = isset($this->recordConfig->Record->poster_sources[$source])
            ? $this->recordConfig->Record->poster_sources[$source] : '';

        $videoUrls = [];
        foreach ($this->getAllRecordsXML() as $xml) {
            foreach ($xml->Title as $title) {
                if (!isset($title->TitleText)) {
                    continue;
                }

                $videoUrl = (string)$title->TitleText;
                $videoSources = [];
                $sourceType = strtolower(pathinfo($videoUrl, PATHINFO_EXTENSION));

                $poster = '';
                $videoType = 'elokuva';
                $description = '';
                if (isset($title->PartDesignation->Value)) {
                    $attributes = $title->PartDesignation->Value->attributes();
                    if (!empty($attributes['video-tyyppi'])) {
                        $videoType = (string)$attributes->{'video-tyyppi'};
                    }
                    // Use video type as the default description since the additional
                    // information may contain long descriptive texts.
                    $description = $videoType ? $videoType
                        : (string)$attributes->{'video-lisatieto'};

                    $posterFilename = (string)$title->PartDesignation->Value;
                    if ($posterFilename) {
                        $poster = str_replace(
                            '{filename}', $posterFilename, $posterSource
                        );
                    }
                }

                //If there is no ProductionEventType set, continue
                if (!isset($xml->ProductionEvent->ProductionEventType)) {
                    continue;
                }
                $eventAttrs = $xml->ProductionEvent->ProductionEventType
                    ->attributes();

                // Lets see if this video has a vimeo-id
                $vimeo = (string)$eventAttrs->{'vimeo-id'};
                $vimeo_url = $this->recordConfig->Record->vimeo_url;
                if (!empty($vimeo) && !empty($vimeo_url)) {
                    $src = str_replace(
                        '{videoid}', $vimeo, $vimeo_url
                    );
                    $videoUrls[] = [
                        'url' => $src,
                        'posterUrl' => $poster,
                        // Include both 'text' and 'desc' for online and normal urls
                        'text' => $description ?: $videoType,
                        'desc' => $description ?: $videoType,
                        'source' => $source,
                        'embed' => 'iframe'
                    ];
                }

                $url = (string)$eventAttrs->{'elokuva-elonet-materiaali-video-url'};

                foreach ($sourceConfigs as $config) {
                    if (!in_array($sourceType, $config['sourceTypes'])) {
                        continue;
                    }
                    $src = str_replace(
                        '{videoname}', $videoUrl, $config['src']
                    );
                    $videoSources[] = [
                        'src' => $src,
                        'type' => $config['mediaType'],
                        'priority' => $config['priority']
                    ];
                }

                if (empty($videoSources)) {
                    continue;
                }

                usort(
                    $videoSources,
                    function ($a, $b) {
                        return $a['priority'] - $b['priority'];
                    }
                );

                if ($this->urlBlacklisted($url, $description)) {
                    continue;
                }

                $videoUrls[] = [
                    'url' => $url,
                    'posterUrl' => $poster,
                    'videoSources' => $videoSources,
                    // Include both 'text' and 'desc' for online and normal urls
                    'text' => $description ? $description : $videoType,
                    'desc' => $description ? $description : $videoType,
                    'source' => $source,
                    'embed' => 'video'
                ];
            }
        }
        return $videoUrls;
    }

    /**
     * Return production cost
     *
     * @return string
     */
    public function getProductionCost()
    {
        return $this->getProductionEventAttribute('elokuva-tuotantokustannukset');
    }

    /**
     * Return premier night theaters and places
     *
     * @return array
     */
    public function getPremiereTheaters()
    {
        $results = [];
        foreach ($this->getAllRecordsXML() as $xml) {
            foreach ($xml->ProductionEvent as $event) {
                if ($event->ProductionEventType == 'PRE') {
                    $theater = (string)$event->Region->RegionName;
                    $results = explode(';', $theater);
                }
            }
        }
        return $results;
    }

    /**
     * Return opening night time
     *
     * @return string
     */
    public function getPremiereTime()
    {
        foreach ($this->getAllRecordsXML() as $xml) {
            foreach ($xml->ProductionEvent as $event) {
                if ($event->ProductionEventType == 'PRE') {
                    $time = (string)$event->DateText;
                    return $time;
                }
            }
        }
    }

    /**
     * Return television broadcasting dates, channels and amount of viewers
     *
     * @return array
     */
    public function getBroadcastingInfo()
    {
        $results = [];
        foreach ($this->getAllRecordsXML() as $xml) {
            foreach ($xml->ProductionEvent as $event) {
                $time = $place = $viewers = '';
                $attributes = $event->ProductionEventType->attributes();
                if (!empty($attributes->{'elokuva-elotelevisioesitys-esitysaika'})) {
                    $time = (string)$attributes->{
                        'elokuva-elotelevisioesitys-esitysaika'
                    };
                }
                if (!empty($attributes->{'elokuva-elotelevisioesitys-paikka'})) {
                    $place = (string)$attributes->{
                        'elokuva-elotelevisioesitys-paikka'
                    };
                }
                if (!empty($attributes->{'elokuva-elotelevisioesitys-katsojamaara'})
                ) {
                    $viewers = (string)$attributes->{
                        'elokuva-elotelevisioesitys-katsojamaara'
                    };
                }
                if (empty($attributes->{'elokuva-elotelevisioesitys-esitysaika'})) {
                    continue;
                }

                $results[] = [
                    'time' => $time,
                    'place' => $place,
                    'viewers' => $viewers
                ];
            }
        }
        $results = array_filter($results);
        return $results;
    }

    /**
     * Return filmfestival attendance information
     *
     * @return array
     */
    public function getFestivalInfo()
    {
        $results = [];
        foreach ($this->getAllRecordsXML() as $xml) {
            foreach ($xml->ProductionEvent as $event) {
                $atr = $event->ProductionEventType->attributes();
                if (!empty($atr->{'elokuva-elofestivaaliosallistuminen-aihe'})) {
                    $name = (string)$atr->{
                        'elokuva-elofestivaaliosallistuminen-aihe'
                    };
                    if (!empty($event->Region->RegionName)) {
                        $region = (string)$event->Region->RegionName;
                    }
                    if (!empty($event->DateText)) {
                        $date = (string)$event->DateText;
                    }
                }
                if (empty($atr->{'elokuva-elofestivaaliosallistuminen-aihe'})) {
                    continue;
                }
                $results[] = [
                    'name' => $name,
                    'region' => $region,
                    'date' => $date
                ];
            }
        }
        return $results;
    }

    /**
     * Return foreign distributors and countries
     *
     * @return array
     */
    public function getForeignDistribution()
    {
        $results = [];
        foreach ($this->getAllRecordsXML() as $xml) {
            foreach ($xml->ProductionEvent as $event) {
                $atr = $event->ProductionEventType->attributes();
                if (!empty($atr->{'elokuva-eloulkomaanmyynti-levittaja'})) {
                    $name = (string)$atr->{
                        'elokuva-eloulkomaanmyynti-levittaja'
                    };
                    if (!empty($event->Region->RegionName)) {
                        $region = (string)$event->Region->RegionName;
                    }
                }
                if (empty($atr->{'elokuva-eloulkomaanmyynti-levittaja'})) {
                    continue;
                }
                $results[] = [
                    'name' => $name,
                    'region' => $region
                ];
            }
        }
        return $results;
    }

    /**
     * Return number of film copies
     *
     * @return string
     */
    public function getNumberOfCopies()
    {
        return $this->getProductionEventAttribute('elokuva-teatterikopioidenlkm');
    }

    /**
     * Return other screening occasions
     *
     * @return array
     */
    public function getOtherScreenings()
    {
        $results = [];
        foreach ($this->getAllRecordsXML() as $xml) {
            foreach ($xml->ProductionEvent as $event) {
                $atr = $event->ProductionEventType->attributes();
                if (!empty($atr->{'elokuva-muuesitys-aihe'})) {
                    $name = (string)$atr->{
                        'elokuva-muuesitys-aihe'
                    };
                    if (!empty($event->Region->RegionName)) {
                        $region = (string)$event->Region->RegionName;
                    }
                    if (!empty($event->DateText)) {
                        $date = (string)$event->DateText;
                    }
                }
                if (empty($atr->{'elokuva-muuesitys-aihe'})) {
                    continue;
                }
                $results[] = [
                    'name' => $name,
                    'region' => $region,
                    'date' => $date
                ];
            }
        }
        return $results;
    }

    /**
     * Return movie inspection details
     *
     * @return array
     */
    public function getInspectionDetails()
    {
        $results = [];
        foreach ($this->getAllRecordsXML() as $xml) {
            foreach ($xml->ProductionEvent as $event) {
                $atr = $event->ProductionEventType->attributes();
                if (!empty($atr->{'elokuva-tarkastus-tarkastusnro'})
                    || !empty($atr->{'elokuva-tarkastus-tarkastuselin'})
                    || !empty($atr->{'elokuva-tarkastus-tarkastusilmoitus'})
                ) {
                    $office = $reason = $length = $subject = $notification = '';
                    $format = $part = $tax = $type  = $date = $inspector = $age = '';
                    $number = $time = '';
                    if (!empty($atr->{'elokuva-tarkastus-tarkastusnro'})) {
                        $number = (string)$atr->{'elokuva-tarkastus-tarkastusnro'};
                    }
                    if (!empty($atr->{'elokuva-tarkastus-tarkastamolaji'})) {
                        $type = (string)$atr->{'elokuva-tarkastus-tarkastamolaji'};
                    }
                    if (!empty($atr->{'elokuva-tarkastus-pituus'})) {
                        $length = (string)$atr->{'elokuva-tarkastus-pituus'};
                    }
                    if (!empty($atr->{'elokuva-tarkastus-veroluokka'})) {
                        $tax = (string)$atr->{'elokuva-tarkastus-veroluokka'};
                    }
                    if (!empty($atr->{'elokuva-tarkastus-ikaraja'})) {
                        $age = (string)$atr->{'elokuva-tarkastus-ikaraja'};
                    }
                    if (!empty($atr->{'elokuva-tarkastus-formaatti'})) {
                        $format = (string)$atr->{'elokuva-tarkastus-formaatti'};
                    }
                    if (!empty($atr->{'elokuva-tarkastus-osalkm'})) {
                        $part = (string)$atr->{'elokuva-tarkastus-osalkm'};
                    }
                    if (!empty($atr->{'elokuva-tarkastus-tarkastuttaja'})) {
                        $office = (string)$atr->{'elokuva-tarkastus-tarkastuttaja'};
                    }
                    if (!empty($atr->{'elokuva-tarkastus-kesto'})) {
                        $time = (string)$atr->{'elokuva-tarkastus-kesto'};
                    }
                    if (!empty($atr->{'elokuva-tarkastus-tarkastusaihe'})) {
                        $subject = (string)$atr->{'elokuva-tarkastus-tarkastusaihe'};
                    }
                    if (!empty($atr->{'elokuva-tarkastus-tarkastusaihe'})) {
                        $reason = (string)$atr->{'elokuva-tarkastus-perustelut'};
                    }
                    if (!empty($atr->{'elokuva-tarkastus-tarkastusilmoitus'})) {
                        $notification = (string)$atr->{
                            'elokuva-tarkastus-tarkastusilmoitus'
                        };
                    }
                    if (!empty($atr->{'elokuva-tarkastus-tarkastuselin'})) {
                        $inspector = (string)$atr->{
                            'elokuva-tarkastus-tarkastuselin'
                        };
                    }
                    if (!empty($event->DateText)
                        && strpos($event->DateText, '0000') == false
                    ) {
                        $date = (string)$event->DateText;
                    }
                    $results[] = [
                        'inspector' => $inspector,
                        'number' => $number,
                        'format' => $format,
                        'length' => $length,
                        'taxclass' => $tax,
                        'agerestriction' => $age,
                        'inspectiontype' => $type,
                        'part' => $part,
                        'office' => $office,
                        'runningtime' => $time,
                        'subject' => $subject,
                        'date' => $date,
                        'reason' => $reason,
                        'notification' => $notification
                    ];
                }
            }
        }
        return $results;
    }

    /**
     * Return movie Age limit'
     * Get Age limit from last inspection's details
     *
     * @return string AgeLimit
     */
    public function getAgeLimit()
    {
        $inspectionDetails = $this->getInspectionDetails();
        foreach ($inspectionDetails as $inspection) {
            if (isset($inspection['agerestriction'])) {
                if (!isset($agerestriction) && $inspection['agerestriction']) {
                    $agerestriction = $inspection['agerestriction'];
                    $date = $inspection['date'] ? $inspection['date'] : '';
                } elseif ($inspection['agerestriction']
                    && isset($date)
                    && strtotime($inspection['date']) > strtotime($date)
                ) {
                    $agerestriction = $inspection['agerestriction'];
                    $date = $inspection['date'];
                }
            }
        }
        return $agerestriction ?? null;
    }

    /**
     * Return an XML representation of the record using the specified format.
     * Return false if the format is unsupported.
     *
     * @param string     $format     Name of format to use (corresponds with OAI-PMH
     * metadataPrefix parameter).
     * @param string     $baseUrl    Base URL of host containing VuFind (optional;
     * may be used to inject record URLs into XML when appropriate).
     * @param RecordLink $recordLink Record link helper (optional; may be used to
     * inject record URLs into XML when appropriate).
     *
     * @return mixed         XML, or false if format unsupported.
     */
    public function getXML($format, $baseUrl = null, $recordLink = null)
    {
        if ('oai_forward' === $format) {
            return $this->fields['fullrecord'];
        }
        return parent::getXML($format, $baseUrl, $recordLink);
    }

    /**
     * Convert author relator to role.
     *
     * @param SimpleXMLNode $agent   Agent
     * @param string        $relator Agent relator
     *
     * @return string
     */
    protected function getAuthorRole($agent, $relator)
    {
        $normalizedRelator = mb_strtoupper($relator, 'UTF-8');
        $role = $this->roleMap[$normalizedRelator] ?? $relator;

        $attributes = $agent->Activity->attributes();
        if (in_array(
            $normalizedRelator,
            ['A00', 'A08', 'A99', 'D99', 'E04', 'E99']
        )
        ) {
            if (!empty($attributes->{'elokuva-elolevittaja'})
            ) {
                return null;
            }
            if (!empty($attributes->{'elokuva-avustajat'})
                || !empty($attributes->{'elokuva-elotuotantoyhtio'})
                || !empty($attributes->{'elokuva-elorahoitusyhtio'})
                || !empty($attributes->{'elokuva-elolaboratorio'})
            ) {
                return null;
            }
            if (!empty($attributes->{'finna-activity-text'})) {
                $role = (string)$attributes->{'finna-activity-text'};
                if (isset($this->elonetRoleMap[$role])) {
                    $role = $this->elonetRoleMap[$role];
                }
            }
        }

        return $role;
    }
}
