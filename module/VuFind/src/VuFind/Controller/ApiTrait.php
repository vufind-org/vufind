<?php
/**
 * Additional functionality for API controllers.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library 2015-2016.
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
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFind\Controller;

/**
 * Additional functionality for API controllers.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait ApiTrait
{
    /**
     * Callback function in JSONP mode
     *
     * @var string
     */
    protected $jsonpCallback = null;

    /**
     * Whether to pretty-print JSON
     *
     * @var bool
     */
    protected $jsonPrettyPrint = false;

    /**
     * Type of output to use
     *
     * @var string
     */
    protected $outputMode = 'json';

    /**
     * Available record fields.
     *
     * Key is the field name that can be requested. Value is an array containing the
     * method name to call (either in this class or the record driver) and Swagger
     * specification fields describing the returned data.
     *
     * @var array
     * @see http://swagger.io/specification/
     */
    protected $recordFields = [
        'accessRestrictions' => [
            'method' => 'getAccessRestrictions',
            'description' => 'Access restriction notes',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'authors' => [
            'method' => 'getDeduplicatedAuthors',
            'description' => 'Deduplicated author information including main'
                . ', corporate and secondary authors',
            'type' => 'array',
            'items' => [
                '$ref' => '#/definitions/Authors'
            ]
        ],
        'awards' => [
            'method' => 'getAwards',
            'description' => 'Award notes',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'bibliographicLevel' => [
            'method' => 'getBibliographicLevel',
            'description' => 'Bibliographic level',
            'type' => 'string',
            'enum' => [
                'Monograph', 'Serial', 'MonographPart', 'SerialPart', 'Collection',
                'CollectionPart', 'Unknown'
            ]
        ],
        'bibliographyNotes' => [
            'method' => 'getBibliographyNotes',
            'description' => 'Bibliography notes',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'callNumbers' => [
            'method' => 'getCallNumbers',
            'description' => 'Call numbers',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'childRecordCount' => [
            'method' => 'getChildRecordCount',
            'description' => 'Number of child records',
            'type' => 'integer'
        ],
        'cleanDoi' => [
            'method' => 'getCleanDOI',
            'description' => 'First valid DOI',
            'type' => 'string'
        ],
        'cleanIsbn' => [
            'method' => 'getCleanISBN',
            'description'
                => 'First valid ISBN favoring ISBN-10 over ISBN-13 when possible',
            'type' => 'string'
        ],
        'cleanIssn' => [
            'method' => 'getCleanISSN',
            'description' => 'Base portion of the first listed ISSN',
            'type' => 'string'
        ],
        'cleanOclcNumber' => [
            'method' => 'getCleanOCLCNum',
            'description' => 'First OCLC number',
            'type' => 'string'
        ],
        'containerEndPage' => [
            'method' => 'getContainerEndPage',
            'description' => 'End page in the containing item',
            'type' => 'string'
        ],
        'containerIssue' => [
            'method' => 'getContainerIssue',
            'description' => 'Issue number of the containing item',
            'type' => 'string'
        ],
        'containerReference' => [
            'method' => 'getContainerReference',
            'description' => 'Reference to the containing item',
            'type' => 'string'
        ],
        'containerStartPage' => [
            'method' => 'getContainerStartPage',
            'description' => 'Start page in the containing item',
            'type' => 'string'
        ],
        'containerTitle' => [
            'method' => 'getContainerTitle',
            'description' => 'Title of the containing item',
            'type' => 'string'
        ],
        'containerVolume' => [
            'method' => 'getContainerVolume',
            'description' => 'Volume of the containing item',
            'type' => 'string'
        ],
        'corporateAuthors' => [
            'method' => 'getCorporateAuthors',
            'description' => 'Main corporate authors',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'dedupIds' => [
            'method' => 'getRecordDedupIds',
            'description'
                => 'IDs of all records deduplicated with the current record',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'edition' => [
            'method' => 'getEdition',
            'description' => 'Edition',
            'type' => 'string'
        ],
        'findingAids' => [
            'method' => 'getFindingAids',
            'description' => 'Finding aids',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'formats' => [
            'method' => 'getFormats',
            'description' => 'Formats',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'fullRecord' => [
            'method' => 'getRecordFullRecord',
            'description' => 'Full metadata record (typically XML)',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'generalNotes' => [
            'method' => 'getGeneralNotes',
            'description' => 'General notes',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'geoLocations' => [
            'method' => 'getGeoLocation',
            'description' => 'Geographic locations (e.g. points, bounding boxes)',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'hierarchicalPlaceNames' => [
            'method' => 'getHierarchicalPlaceNames',
            'description' => 'Hierarchical place names concatenated for display',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'hierarchyParentId' => [
            'method' => 'getHierarchyParentId',
            'description' => 'Parent record IDs for hierarchical records',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'hierarchyParentTitle' => [
            'method' => 'getHierarchyParentTitle',
            'description' => 'Parent record titles for hierarchical records',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'hierarchyTopId' => [
            'method' => 'getHierarchyTopId',
            'description' => 'Hierarchy top record IDs for hierarchical records',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'hierarchyTopTitle' => [
            'method' => 'getHierarchyTopTitle',
            'description' => 'Hierarchy top record titles for hierarchical records',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'humanReadablePublicationDates' => [
            'method' => 'getHumanReadablePublicationDates',
            'description' => 'Publication dates in human-readable format',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'id' => [
            'method' => 'getUniqueID',
            'description' => 'Record unique ID (can be used in the record endpoint)',
            'type' => 'string'
        ],
        'institutions' => [
            'method' => 'getInstitutions',
            'description' => 'Institutions the record belongs to',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'isbns' => [
            'method' => 'getISBNs',
            'description' => 'ISBNs',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'isCollection' => [
            'method' => 'isCollection',
            'description'
                => 'Whether the record is a collection node in a hierarchy',
            'type' => 'boolean'
        ],
        'issns' => [
            'method' => 'getISSNs',
            'description' => 'ISSNs',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'languages' => [
            'method' => 'getLanguages',
            'description' => 'Languages',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'lccn' => [
            'method' => 'getLCCN',
            'description' => 'LCCNs',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'newerTitles' => [
            'method' => 'getNewerTitles',
            'description' => 'Successor titles',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'oclc' => [
            'method' => 'getOCLC',
            'description' => 'OCLC numbers',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'openUrl' => [
            'method' => 'getOpenUrl',
            'description' => 'OpenURL',
            'type' => 'string'
        ],
        'physicalDescriptions' => [
            'method' => 'getPhysicalDescriptions',
            'description' => 'Physical dimensions etc.',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'placesOfPublication' => [
            'method' => 'getPlacesOfPublication',
            'description' => 'Places of publication',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'playingTimes' => [
            'method' => 'getPlayingTimes',
            'description' => 'Playing times (durations)',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'previousTitles' => [
            'method' => 'getPreviousTitles',
            'description' => 'Predecessor titles',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'primaryAuthors' => [
            'method' => 'getPrimaryAuthors',
            'description' => 'Primary authors',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'productionCredits' => [
            'method' => 'getProductionCredits',
            'description' => 'Production credits',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'publicationDates' => [
            'method' => 'getPublicationDates',
            'description' => 'Publication dates',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'publishers' => [
            'method' => 'getPublishers',
            'description' => 'Publishers',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'rawData' => [
            'method' => 'getRecordRawData',
            'description' => 'All data in the index fields',
            'type' => 'string'
        ],
        'recordLinks' => [
            'method' => 'getAllRecordLinks',
            'description' => 'Links to other related records',
            'type' => 'array',
            'items' => [
                '$ref' => '#/definitions/RecordLink'
            ]
        ],
        'recordPage' => [
            'method' => 'getRecordPage',
            'description' => 'Link to the record page in the UI',
            'type' => 'string'
        ],
        'relationshipNotes' => [
            'method' => 'getRelationshipNotes',
            'description' => 'Notes describing relationships to other items',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'secondaryAuthors' => [
            'method' => 'getSecondaryAuthors',
            'description' => 'Secondary authors',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'series' => [
            'method' => 'getSeries',
            'description' => 'Series',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'shortTitle' => [
            'method' => 'getShortTitle',
            'description' => 'Short title (title excluding any subtitle)',
            'type' => 'string'
        ],
        'source' => [
            'method' => 'getRecordSource',
            'description' => 'Record source identifier',
            'type' => 'string'
        ],
        'subjects' => [
            'method' => 'getAllSubjectHeadings',
            'description' => 'Subject headings',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'subTitle' => [
            'method' => 'getSubTitle',
            'description' => 'Subtitle',
            'type' => 'string'
        ],
        'summary' => [
            'method' => 'getSummary',
            'description' => 'Summary',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'systemDetails' => [
            'method' => 'getSystemDetails',
            'description' => 'Technical details on the represented item',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'targetAudienceNotes' => [
            'method' => 'getTargetAudienceNotes',
            'description' => 'Notes about the target audience',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'title' => [
            'method' => 'getTitle',
            'description' => 'Title including any subtitle',
            'type' => 'string'
        ],
        'titleSection' => [
            'method' => 'getTitleSection',
            'description' => 'Part/section portion of the title',
            'type' => 'string'
        ],
        'titleStatement' => [
            'method' => 'getTitleStatement',
            'description' => 'Statement of responsibility that goes with the title',
            'type' => 'string'
        ],
        'toc' => [
            'method' => 'getTOC',
            'description' => 'Table of contents',
            'type' => 'array',
            'items' => [
                'type' => 'string'
            ]
        ],
        'urls' => [
            'method' => 'getRecordURLs',
            'description' => 'URLs contained in the record',
            'type' => 'array',
            'items' => [
                '$ref' => '#/definitions/Url'
            ]
        ]
    ];

    /**
     * Default record fields to return if a request does not define the fields
     *
     * @var array
     */
    protected $defaultRecordFields = [
        'authors',
        'formats',
        'id',
        'languages',
        'rating',
        'series',
        'subjects',
        'title',
        'urls'
    ];

    /**
     * Execute the request
     *
     * @param \Zend\Mvc\MvcEvent $e Event
     *
     * @return mixed
     * @throws Exception\DomainException
     */
    public function onDispatch(\Zend\Mvc\MvcEvent $e)
    {
        // Add CORS headers and handle OPTIONS requests. This is a simplistic
        // approach since we allow any origin. For more complete CORS handling
        // a module like zfr-cors could be used.
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Access-Control-Allow-Origin: *');
        $request = $this->getRequest();
        if ($request->getMethod() == 'OPTIONS') {
            // Disable session writes
            $this->disableSessionWrites();
            $headers->addHeaderLine(
                'Access-Control-Allow-Methods', 'GET, POST, OPTIONS'
            );
            $headers->addHeaderLine('Access-Control-Max-Age', '86400');

            return $this->output(null, 204);
        }
        return parent::onDispatch($e);
    }

    /**
     * Index action
     *
     * @return \Zend\Http\Response
     */
    public function indexAction()
    {
        // Disable session writes
        $this->disableSessionWrites();

        if (null === $this->getRequest()->getQuery('swagger')) {
            $urlHelper = $this->getViewRenderer()->plugin('url');
            $base = rtrim($urlHelper('home'), '/');
            $url = "$base/swagger-ui/index.html?url="
                . urlencode("$base/api?swagger");
            return $this->redirect()->toUrl($url);
        }
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'application/json');
        $config = $this->getConfig();
        $results = $this->getResultsManager()->get($this->searchClassId);
        $options = $results->getOptions();
        $searchConfig = $this->getConfig($options->getSearchIni());
        $facetConfig = $this->getConfig($options->getFacetsIni());
        $params = [
            'title' => $config->Site->title,
            'version' => \VuFind\Config\Version::getBuildVersion(),
            'recordFields' => $this->getRecordFieldSpec(),
            'defaultRecordFields' => $this->defaultRecordFields,
            'facetFields' => isset($facetConfig->Results)
                ? $facetConfig->Results->toArray() : [],
            'sortFields' => isset($searchConfig->Sorting)
                ? $searchConfig->Sorting->toArray() : [],
            'defaultSort' => $searchConfig->General->default_sort
        ];
        $json = $this->getViewRenderer()->render(
            'api/swagger', $params
        );
        $response->setContent($json);
        return $response;
    }

    /**
     * Determine the correct output mode based on content negotiation or the
     * view parameter
     *
     * @return void
     */
    protected function determineOutputMode()
    {
        $request = $this->getRequest();
        $this->jsonpCallback
            = $request->getQuery('callback', $request->getPost('callback', null));
        $this->jsonPrettyPrint = $request->getQuery(
            'prettyPrint', $request->getPost('prettyPrint', false)
        );
        $this->outputMode = empty($this->jsonpCallback) ? 'json' : 'jsonp';
    }

    /**
     * Check whether access is denied and return the appropriate message or false.
     *
     * @param string $permission Permission to check
     *
     * @return \Zend\Http\Response|boolean
     */
    protected function isAccessDenied($permission)
    {
        $auth = $this->serviceLocator->get('ZfcRbac\Service\AuthorizationService');
        if (!$auth->isGranted($permission)) {
            return $this->output([], self::STATUS_ERROR, 403, 'Permission denied');
        }
        return false;
    }

    /**
     * Send output data and exit.
     *
     * @param mixed  $data     The response data
     * @param string $status   Status of the request
     * @param int    $httpCode A custom HTTP Status Code
     * @param string $message  Status message
     *
     * @return \Zend\Http\Response
     * @throws \Exception
     */
    protected function output($data, $status, $httpCode = null, $message = '')
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        if ($httpCode !== null) {
            $response->setStatusCode($httpCode);
        }
        if (null === $data) {
            return $response;
        }
        $output = $data;
        if (!isset($output['status'])) {
            $output['status'] = $status;
        }
        if ($message && !isset($output['statusMessage'])) {
            $output['statusMessage'] = $message;
        }
        $jsonOptions = $this->jsonPrettyPrint ? JSON_PRETTY_PRINT : 0;
        if ($this->outputMode == 'json') {
            $headers->addHeaderLine('Content-type', 'application/json');
            $response->setContent(json_encode($output, $jsonOptions));
            return $response;
        } elseif ($this->outputMode == 'jsonp') {
            $headers->addHeaderLine('Content-type', 'application/javascript');
            $response->setContent(
                $this->jsonpCallback . '(' . json_encode($output, $jsonOptions) . ')'
            );
            return $response;
        } else {
            throw new \Exception('Invalid output mode');
        }
    }
}
