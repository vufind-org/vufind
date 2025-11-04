<?php

/**
 * Model for FORWARD records in Solr.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2022.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */

namespace Finna\RecordDriver;

use function in_array;
use function is_array;

/**
 * Model for FORWARD records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrForward extends \VuFind\RecordDriver\SolrDefault implements \Psr\Log\LoggerAwareInterface
{
    use Feature\SolrFinnaTrait;
    use Feature\SolrForwardTrait {
        Feature\SolrForwardTrait::getAllImages insteadof Feature\SolrFinnaTrait;
    }
    use Feature\FinnaUrlCheckTrait;
    use \VuFind\Log\LoggerAwareTrait;

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
        'B13' => 'Sound editor',
        'D01' => 'fmp',
        'D02' => 'drt',
        'E01' => 'act',
        'E04' => 'cmm',
        'E10' => 'pro',
        'F01' => 'cng',
        'F02' => 'flm',
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
        'selostaja' => 'cmm',
        'valokuvaaja' => 'pht',
        'valonmääritys' => 'lgd',
        'äänitys' => 'rce',
        'dokumentti-esiintyjä' => 'prf',
        'kreditoimaton-dokumentti-esiintyjä' => 'prf',
        'dokumentti-muutesiintyjät' => 'oth',
    ];

    /**
     * Content descriptors
     *
     * @var array
     */
    protected $contentDescriptors = [
        'väkivalta' => 'content_descriptor_violence',
        'seksi' => 'content_descriptor_sexual_content',
        'päihde' => 'content_descriptor_drug_use',
        'ahdistus' => 'content_descriptor_anxiety',
    ];

    /**
     * Age restrictions
     *
     * @var array
     */
    protected $ageRestrictions = [
        'S' => 'age_rating_for_all_ages',
        'T' => 'age_rating_for_all_ages',
        '7' => 'age_rating_7',
        '12' => 'age_rating_12',
        '16' => 'age_rating_16',
        '18' => 'age_rating_18',
    ];

    /**
     * Array of roles to convert
     *
     * @var array
     */
    protected $roleConversion = [
        'esiintyjä' => 'no_role',
        'prf' => 'no_role',
        'oth' => 'no_role',
    ];

    /**
     * Roles to filter
     *
     * @var array
     */
    protected $rolesToFilter = [
        'no_role',
        'act',
    ];

    /**
     * Mappings for saving author name attributes into proper keys.
     * - credited Credited authors
     * - uncredited Uncredited authors
     *
     * @var array
     */
    protected $authorNameConfig = [
        'credited' => [
            'elokuva-elotekija-selitys' => 'description',
            'elokuva-elonayttelija-selitys' => 'description',
            'elokuva-elotekija-rooli' => 'roleName',
            'elokuva-elonayttelija-rooli' => 'roleName',
            'elokuva-eloesiintyja-maare' => 'roleName',
            'elokuva-elonayttelijakokoonpano-tehtava' => 'roleName',
        ],
        'uncredited' => [
            'elokuva-elokreditoimatonnayttelija-rooli' => 'roleName',
            'elokuva-elokreditoimatonesiintyja-maare' => 'roleName',
            'elokuva-elokreditoimatontekija-nimi' => 'name',
            'elokuva-elokreditoimatonnayttelija-nimi' => 'name',
            'elokuva-elokreditoimatonnayttelija-selitys' => 'description',
            'elokuva-elokreditoimatontekija-selitys' => 'description',
        ],
    ];

    /**
     * Mappings from FORWARD author type and role to the results
     * - Result storage root key
     *     - storageKey => additional key to store authors into.
     *       if omitted, save authors under root key.
     *     $results[$rootkey]<[$storageKey]>[] = $author
     *     - relators Which relator codes are taken into account.
     *     - mappings In which key is the author saved.
     *     elonet_henkilo => act => credited => example
     *     $results[$rootKey]<[$storageKey]>[example][] = $author
     *     - all Storage key for saving all the authors.
     *     all => 'allAuthors'
     *     $results[$rootKey]<[$storageKey]>[allAuthors]
     *     - preservedValues Mappings to ease out identifying which
     *     authors should go into which storage key, see mappings.
     *     Affects authors role and type. If role or type is not
     *     found in the preservedValues, will be default.
     *
     * @var array
     */
    protected $authorConfig = [
        'presenters' => [
            'storageKey' => 'presenters',
            'preservedValues' => [
                'no_type',
                'no_role',
                'act',
                'elonet_henkilo',
                'elonet_kokoonpano',
                'muutesiintyjät',
                'avustajat',
            ],
            'relators' => [
                'e01', 'e99', 'cmm', 'a99', 'oth',
            ],
            'mappings' => [
                'elonet_henkilo' => [
                    'act' => [
                        'credited' => 'credited',
                        'uncredited' => 'uncredited',
                    ],
                    'no_role' => [
                        'credited' => 'performer',
                        'uncredited' => 'uncreditedPerformer',
                    ],
                    'avustajat' => [
                        'credited' => 'assistant',
                    ],
                ],
                'elonet_kokoonpano' => [
                    'default' => [
                        'credited' => 'actingEnsemble',
                    ],
                    'no_role' => [
                        'credited' => 'performingEnsemble',
                    ],
                ],
                'default' => [
                    'no_role' => [
                        'credited' => 'other',
                    ],
                ],
                'no_type' => [
                    'muutesiintyjät' => [
                        'credited' => 'other',
                    ],
                    'avustajat' => [
                        'credited' => 'assistant',
                    ],
                    'no_role' => [
                        'credited' => 'other',
                    ],
                ],
            ],
            'skipTags' => [
                'elotekijakokoonpano' => true,
                'muuttekijat' => true,
            ],
        ],
        'nonPresenterSecondaryAuthors' => [
            'preservedValues' => [
                'no_type',
                'no_role',
                'act',
                'elonet_henkilo',
                'elonet_kokoonpano',
            ],
            'relators' => [
                'a00', 'a01', 'a03', 'a06', 'a50', 'a99',
                'b13',
                'd01', 'd02', 'd99',
                'e02', 'e03', 'e04', 'e05', 'e06', 'e08',
                'f01', 'f02', 'f99',
                'cmp', 'cph', 'exp', 'fds', 'fmp', 'rce', 'wst', 'oth', 'prn',
                // These are copied from Marc
                'act', 'anm', 'ann', 'arr', 'acp', 'ar', 'ard', 'aft', 'aud', 'aui',
                'aus', 'bjd', 'bpd', 'cll', 'ctg', 'chr', 'cng', 'clb', 'clr',
                'cwt', 'cmm', 'com', 'cpl', 'cpt', 'cpe', 'ccp', 'cnd', 'cos',
                'cot', 'coe', 'cts', 'ctt', 'cte', 'ctb', 'crp', 'cst', 'cov',
                'cur', 'dnc', 'dtc', 'dto', 'dfd', 'dft', 'dfe', 'dln', 'dpc',
                'dsr', 'dis', 'drm', 'edt', 'elt', 'egr', 'etr', 'fac', 'fld',
                'flm', 'frg', 'ilu', 'ill', 'ins', 'itr', 'ivr', 'ldr', 'lsa',
                'led', 'lil', 'lit', 'lie', 'lel', 'let', 'lee', 'lbt', 'lgd',
                'ltg', 'lyr', 'mrb', 'mte', 'msd', 'mus', 'nrt', 'opn', 'org',
                'pta', 'pth', 'prf', 'pht', 'ptf', 'ptt', 'pte', 'prt', 'pop',
                'prm', 'pro', 'pmn', 'prd', 'prg', 'pdr', 'pbd', 'ppt', 'ren',
                'rpt', 'rth', 'rtm', 'res', 'rsp', 'rst', 'rse', 'rpy', 'rsg',
                'rev', 'rbr', 'sce', 'sad', 'scr', 'scl', 'spy', 'std', 'sng',
                'sds', 'spk', 'stm', 'str', 'stl', 'sht', 'ths', 'trl', 'tyd',
                'tyg', 'vdg', 'voc', 'wde', 'wdc', 'wam',
            ],
            'mappings' => [
                'elonet_kokoonpano' => [
                    'default' => [
                        'credited' => 'ensembles',
                        'uncredited' => 'ensembles',
                    ],
                ],
                'elonet_henkilo' => [
                    'default' => [
                        'credited' => 'credited',
                        'uncredited' => 'uncredited',
                    ],
                ],
                'no_type' => [
                    'no_role' => [
                        'credited' => 'credited',
                    ],
                ],
                'default' => [
                    'default' => [
                        'credited' => 'credited',
                        'uncredited' => 'uncredited',
                    ],
                ],
            ],
            'skipTags' => [
                'elonayttelijakokoonpano' => true,
            ],
            'all' => 'nonPresenters',
        ],
        'primaryAuthors' => [
            'relators' => [
                'd02',
            ],
            'all' => 'primaryAuthors',
        ],
    ];

    protected $productionConfig = [
        'productionAttributeMappings' => [
            'elokuva-kuvasuhde' => 'aspectRatio',
            'elokuva-alkupvari' => 'color',
            'elokuva-alkupvarijarjestelma' => 'colorSystem',
            'elokuva-alkuperaisteos' => 'originalWork',
            'elokuva-alkupkesto' => 'playingTimes',
            'elokuva-alkupaani' => 'sound',
            'elokuva-alkupaanijarjestelma' => 'soundSystem',
            'elokuva-tuotantokustannukset' => 'productionCost',
            'elokuva-teatterikopioidenlkm' => 'numberOfCopies',
            'elokuva-katsojaluku' => 'amountOfViewers',
            'elokuva-kuvausaika' => 'filmingDate',
            'elokuva-arkistoaineisto' => 'archiveFilms',
        ],
        'productionEventMappings' => [
            'elokuva_laji2fin' => 'type',
            'elokuva_huomautukset' => 'generalNotes',
            'elokuva_musiikki' => 'musicInfo',
            'elokuva_lehdistoarvio' => 'pressReview',
            'elokuva_ulkokuvat' => 'exteriors',
            'elokuva_sisakuvat' => 'interiors',
            'elokuva_studiot' => 'studios',
            'elokuva_kuvauspaikkahuomautus' => 'locationNotes',
            'elokuva_kiitokset' => 'thanks',
        ],
        'broadcastingInfoMappings' => [
            'elokuva-elotelevisioesitys-esitysaika' => 'time',
            'elokuva-elotelevisioesitys-paikka' => 'place',
            'elokuva-elotelevisioesitys-katsojamaara' => 'viewers',
        ],
        'festivalSubjectMappings' => [
            'elokuva-elofestivaaliosallistuminen-aihe' => 'festivalInfo',
        ],
        'otherScreeningMappings' => [
            'elokuva-muuesitys-aihe' => 'otherScreenings',
        ],
        'inspectionAttributes' => [
            'elokuva-tarkastus-tarkastusnro' => 'number',
            'elokuva-tarkastus-tarkastamolaji' => 'inspectiontype',
            'elokuva-tarkastus-pituus' => 'length',
            'elokuva-tarkastus-veroluokka' => 'taxclass',
            'elokuva-tarkastus-ikaraja' => 'agerestriction',
            'elokuva-tarkastus-formaatti' => 'format',
            'elokuva-tarkastus-osalkm' => 'part',
            'elokuva-tarkastus-tarkastuttaja' => 'office',
            'elokuva-tarkastus-kesto' => 'runningtime',
            'elokuva-tarkastus-tarkastusaihe' => 'subject',
            'elokuva-tarkastus-perustelut' => 'reason',
            'elokuva-tarkastus-muuttiedot' => 'additional',
            'elokuva-tarkastus-tarkastusilmoitus' => 'notification',
            'elokuva-tarkastus-tarkastuselin' => 'inspector',
            'elokuva-tarkastus-kopiolkm' => 'copy_count',
        ],
        'accessRestrictionMappings' => [
            'finna-kayttooikeus' => 'accessRestrictions',
        ],
    ];

    /**
     * Mappings for description types
     *
     * @var array
     */
    protected $descriptionTypeMappings = [
        'Content description' => 'contentDescription',
        'Synopsis' => 'synopsis',
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
     * @param \VuFind\Config\Config $mainConfig     VuFind main configuration (omit
     * for built-in defaults)
     * @param \VuFind\Config\Config $recordConfig   Record-specific configuration
     * file (omit to use $mainConfig as $recordConfig)
     * @param \VuFind\Config\Config $searchSettings Search-specific configuration
     * file
     */
    public function __construct(
        $mainConfig = null,
        $recordConfig = null,
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
        $events = $this->getProductionEvents();
        return $events['accessRestrictions'] ?? [];
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
        $events = $this->getProductionEvents();
        foreach ($events['accessRestrictions'] ?? [] as $type) {
            $result = ['copyright' => $type];
            if ($link = $this->getRightsLink($type, $language)) {
                $result['link'] = $link;
            }
            return $result;
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
     * - id: first authority id (if defined)
     * - ids: multiple authority ids (if defined)
     * - authType: authority type (if id is defined)
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
                        'source' => '',
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
            $titleText = $title->TitleText;
            $titleTextStr = (string)$title->TitleText;
            if ($titleTextStr == $identifyingTitle) {
                continue;
            }
            if ($rel = $title->TitleRelationship) {
                if ($type = $rel->attributes()->{'elokuva-elonimi-tyyppi'}) {
                    $titleTextStr .= " ($type)";
                } else {
                    switch ((string)$rel) {
                        case 'working':
                            $titleTextStr
                                .= " ({$this->translate('working title')})";
                            break;
                        case 'translated':
                            if ($lang = $titleText->attributes()->lang) {
                                $titleTextStr .= " ({$this->translate($lang)})";
                            }
                            break;
                    }
                }
            }
            $result[] = $titleTextStr;
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
        $events = $this->getProductionEvents();
        return $events['aspectRatio'] ?? '';
    }

    /**
     * Return type
     *
     * @return string
     */
    public function getType()
    {
        $events = $this->getProductionEvents();
        return trim(implode(', ', $events['type'] ?? []));
    }

    /**
     * Return color
     *
     * @return string
     */
    public function getColor()
    {
        $events = $this->getProductionEvents();
        return $events['color'] ?? '';
    }

    /**
     * Return color system
     *
     * @return string
     */
    public function getColorSystem()
    {
        $events = $this->getProductionEvents();
        return $events['colorSystem'] ?? '';
    }

    /**
     * Get country
     *
     * @return string
     */
    public function getCountry()
    {
        $xml = $this->getRecordXML();
        return (string)($xml->CountryOfReference->Country->RegionName ?? '');
    }

    /**
     * Return descriptions
     *
     * @return array
     */
    public function getDescription()
    {
        $locale = $this->getLocale();
        $results = $this->getDescriptionData();
        return $results['contentDescription'][$locale]
            ?? $results['contentDescription']['all']
            ?? [];
    }

    /**
     * Get distributors
     *
     * @return array
     */
    public function getDistributors()
    {
        $authors = $this->getAuthors();
        return $authors['distributors'] ?? [];
    }

    /**
     * Get funders
     *
     * @return array
     */
    public function getFunders()
    {
        $authors = $this->getAuthors();
        return $authors['funders'] ?? [];
    }

    /**
     * Get general notes on the record.
     *
     * @return array
     */
    public function getGeneralNotes()
    {
        $events = $this->getProductionEvents();
        return $events['generalNotes'] ?? '';
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
        return $rights['copyright'] ?? false;
    }

    /**
     * Return music information
     *
     * @return string
     */
    public function getMusicInfo()
    {
        $events = $this->getProductionEvents();
        $results = $events['musicInfo'] ?? [];
        if ($result = reset($results) ?? '') {
            $result = preg_replace('/(\d+\. )/', '<br/>\1', $result);
            if (strncmp($result, '<br/>', 5) == 0) {
                $result = substr($result, 5);
            }
        }
        return $result;
    }

    /**
     * Get presenters as an assoc array
     *
     * @return array
     */
    public function getPresenters(): array
    {
        $authors = $this->getAuthors();
        return $authors['presenters'] ?? [];
    }

    /**
     * Get all primary authors apart from presenters
     *
     * @return array
     */
    public function getNonPresenterPrimaryAuthors()
    {
        $authors = $this->getAuthors();
        return $authors['primaryAuthors'] ?? [];
    }

    /**
     * Get all authors apart from presenters
     *
     * @return array
     */
    public function getNonPresenterAuthors(): array
    {
        $authors = $this->getAuthors();
        return $authors['nonPresenters'] ?? [];
    }

    /**
     * Get all secondary authors apart from presenters
     *
     * @return array
     */
    public function getNonPresenterSecondaryAuthors()
    {
        $authors = $this->getAuthors();
        return $authors['nonPresenterSecondaryAuthors'] ?? [];
    }

    /**
     * Loop through all the authors and return them in an associative array
     *
     * @return array
     */
    public function getAuthors(): array
    {
        $cacheKey = __FUNCTION__;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        $xml = $this->getRecordXML();
        $idx = 0;
        $results = [
            'primaryAuthors' => [],
            'producers' => [],
            'nonPresenters' => [],
        ];

        foreach ($xml->HasAgent as $agent) {
            $result = [
                'tag' => ((string)$agent['elonet-tag'] ?? ''),
                'name' => '',
                'role' => '',
                'id' => '',
                'type' => '',
                'roleName' => '',
                'description' => '',
                'uncredited' => '',
                'idx' => '',
            ];

            $primary = false;
            if (!empty($agent->Activity)) {
                $activity = $agent->Activity;
                $relator = (string)$activity;
                $primary = $relator === 'D02';
                if (null === ($role = $this->getAuthorRole($agent, $relator))) {
                    continue;
                }
                $result['role'] = $this->roleConversion[$role] ?? $role;
                foreach ($activity->attributes() as $key => $value) {
                    $result[$key] = (string)$value;
                }
                $result['relator'] = (string)$activity;
            }
            if ($agentName = $agent->AgentName ?? false) {
                $result['name'] = (string)$agentName;
                foreach ($agentName->attributes() as $key => $value) {
                    $result[$key] = $valueString = (string)$value;
                    foreach ($this->authorNameConfig as $credited => $attrs) {
                        if ($fieldType = $attrs[$key] ?? false) {
                            if ('uncredited' === $credited) {
                                $result['uncredited'] = true;
                            }
                            if ('name' === $fieldType && !empty($result['name'])) {
                                break;
                            }
                            $result[$fieldType] = $valueString;
                            break;
                        }
                    }
                }
            }
            if (!empty($agent->AgentIdentifier)) {
                $authType = (string)$agent->AgentIdentifier->IDTypeName;
                $idValue = (string)$agent->AgentIdentifier->IDValue;
                $result['id'] = "{$authType}_{$idValue}";
                $result['type'] = $authType;
            }
            $idx++;
            $result['idx'] = $primary ? $idx : 10000 * $idx;

            $type = $result['type'] ?: 'no_type';
            $role = $result['role'];
            $credited = $result['uncredited'] === true ? 'uncredited' : 'credited';
            $lcRelator = mb_strtolower($result['relator'] ?? '', 'UTF-8');
            foreach ($this->authorConfig as $storage => $data) {
                if ($data['skipTags'][$result['tag']] ?? false) {
                    continue;
                }
                if (in_array($lcRelator, $data['relators'])) {
                    $valuesToPreserve = $data['preservedValues'] ?? [];
                    if (in_array($role, $this->rolesToFilter)) {
                        $result['role'] = '';
                    }
                    $type = in_array($type, $valuesToPreserve) ? $type : 'default';
                    $role = in_array($role, $valuesToPreserve) ? $role : 'default';
                    if ($res = $data['mappings'][$type][$role][$credited] ?? '') {
                        if ($k = $data['storageKey'] ?? '') {
                            $results[$storage][$res][$k][] = $result;
                        } else {
                            $results[$storage][$res][] = $result;
                        }
                    }
                    if ($additional = $data['all'] ?? '') {
                        if (!isset($results[$additional])) {
                            $results[$additional] = [];
                        }
                        $results[$additional][] = $result;
                    }
                }
            }

            switch ($result['finna-activity-code'] ?? '') {
                case 'E10':
                    $results['producers'][] = $result;
                    break;
                case 'fds':
                    $result['date'] = $result['elokuva-elolevittaja-vuosi'] ?? '';
                    $result['method']
                        = $result['elokuva-elolevittaja-levitystapa'] ?? '';
                    $results['distributors'][] = $result;
                    break;
                case 'fnd':
                    $result['amount']
                        = $result['elokuva-elorahoitusyhtio-summa'] ?? '';
                    $result['fundingType']
                        = $result['elokuva-elorahoitusyhtio-rahoitustapa'] ?? '';
                    $results['funders'][] = $result;
                    break;
                default:
                    if (isset($result['elokuva-elotuotantoyhtio'])) {
                        $results['producers'][] = $result;
                    }
                    break;
            }
        }
        return $this->cache[$cacheKey] = $results;
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
                if (
                    $videoUrls && strpos($url['url'], 'elonet.fi') > 0
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
        $events = $this->getProductionEvents();
        return $events['originalWork'] ?? '';
    }

    /**
     * Return playing times
     *
     * @return array
     */
    public function getPlayingTimes()
    {
        $events = $this->getProductionEvents();
        $str = $events['playingTimes'] ?? '';
        return $str ? [$str] : [];
    }

    /**
     * Return press review
     *
     * @return string
     */
    public function getPressReview()
    {
        $events = $this->getProductionEvents();
        $results = $events['pressReview'] ?? [];
        if ($result = reset($results)) {
            return $result;
        }
        return '';
    }

    /**
     * Get producers
     *
     * @return array
     */
    public function getProducers()
    {
        $authors = $this->getAuthors();
        return $authors['producers'] ?? [];
    }

    /**
     * Return sound
     *
     * @return string
     */
    public function getSound()
    {
        $events = $this->getProductionEvents();
        return $events['sound'] ?? '';
    }

    /**
     * Return sound system
     *
     * @return string
     */
    public function getSoundSystem()
    {
        $events = $this->getProductionEvents();
        return $events['soundSystem'] ?? '';
    }

    /**
     * Return summary
     *
     * @return array
     */
    public function getSummary()
    {
        $locale = $this->getLocale();
        $results = $this->getDescriptionData();
        return $results['synopsis'][$locale]
            ?? $results['synopsis']['all']
            ?? [];
    }

    /**
     * Set raw data to initialize the object.
     *
     * @param mixed $data Raw data representing the record; Record Model
     * objects are normally constructed by Record Driver objects using data
     * passed in from a Search Results object. The exact nature of the data may
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
     * Return full record as a filtered SimpleXMLElement for public APIs.
     *
     * @return \SimpleXMLElement
     */
    public function getFilteredXMLElement(): \SimpleXMLElement
    {
        $record = clone $this->getRecordXML();
        $remove = [];
        foreach ($record->ProductionEvent as $event) {
            $attributes = $event->attributes();
            if (
                isset($attributes->{'elonet-tag'})
                && 'lehdistoarvio' === (string)$attributes->{'elonet-tag'}
            ) {
                $remove[] = $event;
            }
        }
        foreach ($remove as $node) {
            unset($node[0]);
        }
        return $record;
    }

    /**
     * Return full record as filtered XML for public APIs.
     *
     * @return string
     */
    public function getFilteredXML()
    {
        return $this->getFilteredXMLElement()->asXML();
    }

    /**
     * Get all original records as a SimpleXML object
     *
     * @return \SimpleXMLElement The record as SimpleXML
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
     * Loop through all the descriptions and return them in an associative array
     *
     * @return array
     */
    protected function getDescriptionData()
    {
        $cacheKey = __FUNCTION__;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        $results = [];
        foreach ($this->getRecordXML()->ContentDescription as $description) {
            if (!($text = (string)($description->DescriptionText ?? ''))) {
                continue;
            }
            $type = (string)($description->DescriptionType ?? '');
            $lang = (string)($description->Language ?? 'no_lang');
            if ($storage = $this->descriptionTypeMappings[$type] ?? false) {
                $results[$storage][$lang][] = $text;
                $results[$storage]['all'][] = $text;
            }
        }
        return $this->cache[$cacheKey] = $results;
    }

    /**
     * Loop through all production events and return them in an associative array
     *
     * @return array
     */
    protected function getProductionEvents(): array
    {
        $cacheKey = __FUNCTION__;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $results = [
            'broadcastingInfo' => [],
            'festivalInfo' => [],
            'foreignDistribution' => [],
            'otherScreenings' => [],
            'inspectionDetails' => [],
            'accessRestrictions' => [],
        ];
        $xml = $this->getRecordXML();
        $config = $this->productionConfig;
        foreach ($xml->ProductionEvent as $event) {
            $type = (string)($event->ProductionEventType ?? '');
            $regionName = (string)($event->Region->RegionName ?? '');
            $dateText = (string)($event->DateText ?? '');

            $attributes = $event->ProductionEventType->attributes();
            $broadcastingResult = [];
            $inspectionResult = [];

            switch ($type) {
                case 'PRL':
                    $distributorName = trim((string)$attributes->{'elokuva-eloulkomaanmyynti-levittaja'});
                    $results['foreignDistribution'][] = [
                        'name' => $distributorName ?: $regionName,
                        'region' => $distributorName ? $regionName : '',
                    ];
                    break;
                case 'PRE':
                    if (!empty($regionName)) {
                        $results['premiereTheater'] = explode(';', $regionName);
                    }
                    if (!empty($dateText)) {
                        $results['premiereTime'] = $dateText;
                    }
                    break;
            }
            foreach ($attributes as $key => $value) {
                $stringValue = (string)$value;
                // Get production attribute
                if ($storage = $config['productionAttributeMappings'][$key] ?? '') {
                    $results[$storage] = $stringValue;
                }
                // Get broadcasting information
                if ($info = $config['broadcastingInfoMappings'][$key] ?? '') {
                    $broadcastingResult[$info] = $stringValue;
                }
                // Get festival info
                if ($festival = $config['festivalSubjectMappings'][$key] ?? '') {
                    $results[$festival][] = [
                        'name' => $stringValue,
                        'region' => $regionName,
                        'date' => $dateText,
                    ];
                }

                // Get other screening info
                if ($screening = $config['otherScreeningMappings'][$key] ?? '') {
                    $results[$screening][] = [
                        'name' => $stringValue,
                        'region' => $regionName,
                        'date' => $dateText,
                    ];
                }
                // Get inspection detail
                if ($inspection = $config['inspectionAttributes'][$key] ?? '') {
                    $inspectionResult[$inspection] = $stringValue;
                }
                // Get access restriction details
                if (
                    $restriction = $config['accessRestrictionMappings'][$key] ?? ''
                ) {
                    $results[$restriction][] = $stringValue;
                }
            }
            // Check if we have found something to save
            if ($broadcastingResult) {
                $results['broadcastingInfo'][] = $broadcastingResult;
            }
            if ($inspectionResult) {
                if (!str_contains($dateText, '0000')) {
                    $inspectionResult['date'] = $dateText;
                }
                $results['inspectionDetails'][] = $inspectionResult;
            }
            $children = $event->children();
            foreach ($children as $childKey => $childValue) {
                if (
                    $storage = $config['productionEventMappings'][$childKey] ?? []
                ) {
                    $results[$storage][] = (string)$childValue;
                }
            }
        }
        return $this->cache[$cacheKey] = $results;
    }

    /**
     * Get the original main record as a SimpleXML object
     *
     * @return \SimpleXMLElement The record as SimpleXML
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
        $cacheKey = __FUNCTION__;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        $source = $this->getSource();
        if (
            null === $this->videoHandler
            || !($handler = $this->videoHandler->getHandler($source))
        ) {
            return $this->cache[$cacheKey] = [];
        }
        $videos = [];
        foreach ($this->getAllRecordsXML() as $xml) {
            if (empty($xml->ProductionEvent->ProductionEventType)) {
                continue;
            }
            foreach ($xml->Title as $title) {
                if (!isset($title->TitleText)) {
                    continue;
                }
                $videoID = trim((string)$title->TitleText);
                $titleValue = $title->PartDesignation->Value ?? '';
                if (!$titleValue) {
                    continue;
                }
                $attributes = $titleValue->attributes();
                $videoType = (string)($attributes->{'video-tyyppi'} ?? '');
                if (!$videoType) {
                    continue;
                }
                $warnings = [];
                // Check for warnings
                if (!empty($attributes->{'video-rating'})) {
                    $tmpWarnings = explode(', ', (string)$attributes->{'video-rating'});
                    foreach ($tmpWarnings as $warning) {
                        if ($warn = $this->contentDescriptors[$warning] ?? '') {
                            $warnings[] = $warn;
                        }
                        if ($warn = $this->ageRestrictions[$warning] ?? '') {
                            $warnings[] = $warn;
                        }
                    }
                }
                if (!$this->maxAmountOfURLs()) {
                    $videos[] = [
                        'id' => $videoID,
                        'url' => '',
                        'posterName' => (string)$titleValue,
                        'type' => $videoType,
                        'description' => $videoType,
                        'text' => $videoType,
                        'source' => $source,
                        'warnings' => $warnings,
                    ];
                }
                $this->urlsCount++;
            }
        }
        return $this->cache[$cacheKey] = $handler->getData($videos);
    }

    /**
     * Return production cost
     *
     * @return string
     */
    public function getProductionCost()
    {
        $events = $this->getProductionEvents();
        return $events['productionCost'] ?? '';
    }

    /**
     * Return premier night theaters and places
     *
     * @return array
     */
    public function getPremiereTheaters()
    {
        $events = $this->getProductionEvents();
        return $events['premiereTheater'] ?? [];
    }

    /**
     * Return opening night time
     *
     * @return string
     */
    public function getPremiereTime()
    {
        $events = $this->getProductionEvents();
        return $events['premiereTime'] ?? [];
    }

    /**
     * Return television broadcasting dates, channels and amount of viewers
     *
     * @return array
     */
    public function getBroadcastingInfo()
    {
        $events = $this->getProductionEvents();
        return $events['broadcastingInfo'] ?? [];
    }

    /**
     * Return filmfestival attendance information
     *
     * @return array
     */
    public function getFestivalInfo()
    {
        $events = $this->getProductionEvents();
        return $events['festivalInfo'] ?? [];
    }

    /**
     * Return foreign distributors and countries
     *
     * @return array
     */
    public function getForeignDistribution()
    {
        $events = $this->getProductionEvents();
        return $events['foreignDistribution'] ?? [];
    }

    /**
     * Return number of film copies
     *
     * @return string
     */
    public function getNumberOfCopies()
    {
        $events = $this->getProductionEvents();
        return $events['numberOfCopies'] ?? '';
    }

    /**
     * Return number of viewer
     *
     * @return string
     */
    public function getAmountOfViewers(): string
    {
        $events = $this->getProductionEvents();
        return $events['amountOfViewers'] ?? '';
    }

    /**
     * Return other screening occasions
     *
     * @return array
     */
    public function getOtherScreenings()
    {
        $events = $this->getProductionEvents();
        return $events['otherScreenings'] ?? [];
    }

    /**
     * Return movie inspection details
     *
     * @return array
     */
    public function getInspectionDetails()
    {
        $events = $this->getProductionEvents();
        return $events['inspectionDetails'] ?? [];
    }

    /**
     * Return Movie Thanks
     *
     * @return array
     */
    public function getMovieThanks(): array
    {
        $events = $this->getProductionEvents();
        return $events['thanks'] ?? [];
    }

    /**
     * Return movie Age limit
     *
     * Get Age limit from last inspection's details
     *
     * @return string AgeLimit
     */
    public function getAgeLimit()
    {
        $inspectionDetails = $this->getInspectionDetails();
        $currentDate = 0;
        $currentLimit = null;
        foreach ($inspectionDetails as $inspection) {
            if (empty($inspection['agerestriction'])) {
                continue;
            }

            // Use this age restriction if we don't have an earlier one or the
            // inspection is at least as new as the earlier one.
            $inspectionDate = isset($inspection['date'])
                ? strtotime($inspection['date']) : 0;
            if (null === $currentLimit || $inspectionDate >= $currentDate) {
                $currentLimit = $inspection['agerestriction'];
                $currentDate = $inspectionDate;
            }
        }
        return $currentLimit;
    }

    /**
     * Return exteriors
     *
     * @return array
     */
    public function getExteriors()
    {
        $events = $this->getProductionEvents();
        return $events['exteriors'] ?? '';
    }

    /**
     * Return interiors
     *
     * @return array
     */
    public function getInteriors()
    {
        $events = $this->getProductionEvents();
        return $events['interiors'] ?? '';
    }

    /**
     * Return studios
     *
     * @return array
     */
    public function getStudios()
    {
        $events = $this->getProductionEvents();
        return $events['studios'] ?? '';
    }

    /**
     * Return location notes
     *
     * @return array
     */
    public function getLocationNotes()
    {
        $events = $this->getProductionEvents();
        return $events['locationNotes'] ?? [];
    }

    /**
     * Return filming date
     *
     * @return string
     */
    public function getFilmingDate()
    {
        $events = $this->getProductionEvents();
        return $events['filmingDate'] ?? '';
    }

    /**
     * Return archive films
     *
     * @return string
     */
    public function getArchiveFilms()
    {
        $events = $this->getProductionEvents();
        return $events['archiveFilms'] ?? '';
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
        if (
            in_array(
                $normalizedRelator,
                ['A00', 'A08', 'A99', 'D99', 'E04', 'E99']
            )
        ) {
            if (
                !empty($attributes->{'elokuva-elolevittaja'})
            ) {
                return null;
            }
            if (
                !empty($attributes->{'elokuva-elotuotantoyhtio'})
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
