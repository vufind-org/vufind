<?php

namespace TueFind\View\Helper\TueFind;

use \TueFind\RecordDriver\SolrAuthMarc as AuthorityRecordDriver;
use \TueFind\RecordDriver\SolrMarc as TitleRecordDriver;

/**
 * View Helper for TueFind, containing functions related to authority data + schema.org
 */
class Authority extends \Laminas\View\Helper\AbstractHelper
                implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    protected $dbTableManager;

    protected $recordLoader;

    protected $searchService;

    protected $viewHelperManager;

    public function __construct(\VuFindSearch\Service $searchService,
                                \Laminas\View\HelperPluginManager $viewHelperManager,
                                \VuFind\Record\Loader $recordLoader,
                                \VuFind\Db\Table\PluginManager $dbTableManager)
    {
        $this->dbTableManager = $dbTableManager;
        $this->recordLoader = $recordLoader;
        $this->searchService = $searchService;
        $this->viewHelperManager = $viewHelperManager;
    }

    /**
     * Get authority birth information for display
     *
     * @return string
     */
    public function getBirth(AuthorityRecordDriver &$driver): string
    {
        $display = '';

        $birthDate = $driver->getBirthDateOrYear();
        if ($birthDate != '') {
            $display .= $this->getDateTimeProperty($birthDate, 'birthDate');
            $birthPlace = $driver->getBirthPlace();
            if ($birthPlace != null)
                $display .= ', <span property="birthPlace">' . $birthPlace . '</span>';
        }

        return $display;
    }

    /**
     * Get rendered html for datetime property (for view + schema.org)
     *
     * schema.org timestamps must be provided as a ISO8601 timestamp,
     * so if the timestamp differs, we create an additional element
     * which is hidden + marked-up for schema.org.
     *
     * @param string $datetimeCandidate
     * @param string $propertyId
     * @return string
     */
    private function getDateTimeProperty($datetimeCandidate, $propertyId): string
    {
        $iso8601DateTime = $this->getView()->tuefind()->convertDateTimeToIso8601($datetimeCandidate);
        if ($iso8601DateTime == $datetimeCandidate)
            return '<span property="' . htmlspecialchars($propertyId) . '">' . $datetimeCandidate . '</span>';

        $html = '<span>' . htmlspecialchars($datetimeCandidate) . '</span>';
        $html .= '<span class="tf-schema-org-only" property="' . htmlspecialchars($propertyId) . '">' . htmlspecialchars($iso8601DateTime) . '</span>';
        return $html;
    }

    /**
     * Get authority death information for display
     *
     * @return string
     */
    public function getDeath(AuthorityRecordDriver &$driver): string
    {
        $display = '';
        $deathDate = $driver->getDeathDateOrYear();
        if ($deathDate != '') {
            $display .= $this->getDateTimeProperty($deathDate, 'deathDate');
            $deathPlace = $driver->getDeathPlace();
            if ($deathPlace != null)
                $display .= ', <span property="deathPlace">' . $deathPlace . '</span>';
        }
        return $display;
    }

    public function getBibliographicalReferences(AuthorityRecordDriver &$driver): string
    {
        $references = $driver->getBibliographicalReferences();
        if (count($references) == 0)
            return '';

        usort($references, function($a, $b) { return strcmp($a['title'], $b['title']); });

        $display = '';
        foreach ($references as $reference)
            $display .= '<a href="' . $reference['url'] . '" target="_blank" property="sameAs"><i class="fa fa-external-link"></i> ' . htmlspecialchars($reference['title']) . '</a><br>';

        return $display;
    }

    public function getArchivedMaterial(AuthorityRecordDriver &$driver): string
    {
        $references = $driver->getArchivedMaterial();
        if (count($references) == 0)
            return '';

        usort($references, function($a, $b) { return strcmp($a['title'], $b['title']); });

        $display = '';
        foreach ($references as $reference) {
            $title = $reference['title'];
            if (preg_match('"Kalliope"', $title))
                $title = 'Kalliope';
            elseif (preg_match('"Archivportal-D"', $title))
                $title = 'Archivportal-D';

            $display .= '<a href="' . $reference['url'] . '" target="_blank" property="sameAs"><i class="fa fa-external-link"></i> ' . htmlspecialchars($title) . '</a><br>';
        }

        return $display;
    }

    public function getExternalSubsystems(AuthorityRecordDriver &$driver, $currentSubsystem): string
    {
        $externalSubsystems = $driver->getExternalSubsystems();
        usort($externalSubsystems, function($a, $b) { return strcmp($a['title'], $b['title']); });

        $subSystemHTML = '';
        if(!empty($externalSubsystems) && !empty($currentSubsystem)) {
            foreach ($externalSubsystems as $system) {
                if ($system['label'] != $currentSubsystem) {
                    $subSystemHTML .= '<a href="'.$system['url'].'" target="_blank" property="sameAs"><i class="fa fa-external-link"></i> '.htmlspecialchars($system['title']).'</a><br />';
                }
            }
        }

        return $subSystemHTML;
    }

    public function getName(AuthorityRecordDriver &$driver): string
    {
        if ($driver->isMeeting()) {
            return '<span property="name">' . htmlspecialchars($driver->getMeetingName()) . '</span>';
        } else {
            $name = $driver->getHeadingShort();
            $timespan = $driver->getHeadingTimespan();

            $heading = '<span property="name">' . htmlspecialchars($name) . '</span>';
            if ($timespan != null)
                $heading .= ' ' . htmlspecialchars($timespan);
            return $heading;
        }
    }

    public function getOccupations(AuthorityRecordDriver &$driver): string
    {
        $occupations = $driver->getOccupations($this->getTranslatorLocale());
        $occupationsDisplay = '';
        foreach ($occupations as $occupation) {
            if ($occupationsDisplay != '')
                $occupationsDisplay .= ' / ';
            $occupationsDisplay .= '<span property="hasOccupation">' . htmlspecialchars($occupation) . '</span>';
        }
        return $occupationsDisplay;
    }

    public function getCorporateRelations(AuthorityRecordDriver &$driver): string
    {
        $relations = $driver->getCorporateRelations();
        $relationsDisplay = '';

        $urlHelper = $this->viewHelperManager->get('url');
        foreach ($relations as $relation) {
            if ($relationsDisplay != '')
                $relationsDisplay .= '<br>';

            $relationsDisplay .= '<span property="affiliation" typeof="Organization">';

            $recordExists = isset($relation['id']) && $this->recordExists($relation['id']);
            if ($recordExists) {
                $url = $urlHelper('solrauthrecord', ['id' => $relation['id']]);
                $relationsDisplay .= '<a property="sameAs" href="' . $url . '">';
            }

            $relationsDisplay .= '<span property="name">' . htmlspecialchars($relation['name']) . '</span>';
            if (isset($relation['location'])) {
                $relationsDisplay .= ' (<span property="location">' . htmlspecialchars($relation['location']) . '</span>)';
            } else if (isset($relation['institution'])) {
                $relationsDisplay .= '. <span property="department">' . htmlspecialchars($relation['institution']) . '</span>';
            }

            if (isset($relation['type']) || isset($relation['timespan'])) {
                $relationsDisplay .= ':';
                if (isset($relation['type']))
                    $relationsDisplay .= ' ' . $relation['type'];
                if (isset($relation['timespan']))
                    $relationsDisplay .= ' ' . $relation['timespan'];
            }

            if ($recordExists)
                $relationsDisplay .= '</a>';

            $relationsDisplay .= '</span>';
        }
        return $relationsDisplay;
    }

    public function getPersonalRelations(AuthorityRecordDriver &$driver): string
    {
        $relations = $driver->getPersonalRelations();
        $relationsDisplay = '';

        $urlHelper = $this->viewHelperManager->get('url');
        foreach ($relations as $relation) {
            if ($relationsDisplay != '')
                $relationsDisplay .= '<br>';

            $relationsDisplay .= '<span property="relatedTo" typeof="Person">';

            $recordExists = isset($relation['id']) && $this->recordExists($relation['id']);
            if ($recordExists) {
                $url = $urlHelper('solrauthrecord', ['id' => $relation['id']]);
                $relationsDisplay .= '<a property="sameAs" href="' . $url . '">';
            }

            $relationsDisplay .= '<span property="name">' . $relation['name'] . '</span>';

            if (isset($relation['type']))
                $relationsDisplay .= ' (' . htmlspecialchars($this->translate($relation['type'])) . ')';

            if ($recordExists)
                $relationsDisplay .= '</a>';

            $relationsDisplay .= '</span>';
        }
        return $relationsDisplay;
    }

    public function getGeographicalRelations(AuthorityRecordDriver &$driver): string
    {
        $placesString = '';

        $places = $driver->getGeographicalRelations();
        foreach ($places as $place) {
            if ($place['type'] == 'DIN-ISO-3166') {
                $place['type'] = 'Country';
                $place['name'] = \Locale::getDisplayRegion($place['name'], $this->getTranslatorLocale()) . ' (' . $place['name'] . ')';
            }

            $placesString .= htmlentities($this->translate($place['type'])) . ': ' . htmlentities($place['name']) . '<br>';
        }

        return $placesString;
    }

    public function getSchemaOrgType(AuthorityRecordDriver &$driver): string
    {
        switch ($driver->getType()) {
        case 'person':
            return 'Person';
        case 'corporate':
            return 'Organization';
        case 'meeting':
            return 'Event';
        default:
            return 'Thing';
        }
    }

    public function getNewestTitlesAbout(AuthorityRecordDriver &$driver, $offset=0, $limit=10)
    {
        // We use 'Solr' as identifier here, because the RecordDriver's identifier would be "SolrAuth"
        $identifier = 'Solr';
        $response = $this->searchService->search($identifier,
                                                 new \VuFindSearch\Query\Query($this->getTitlesAboutQueryParams($driver), 'AllFields'),
                                                 $offset, $limit, new \VuFindSearch\ParamBag(['sort' => 'publishDate DESC']));

        return $response;
    }

    public function getNewestTitlesBy(AuthorityRecordDriver &$driver, $offset=0, $limit=10)
    {
        // We use 'Solr' as identifier here, because the RecordDriver's identifier would be "SolrAuth"
        $identifier = 'Solr';
        $response = $this->searchService->search($identifier,
                                                 new \VuFindSearch\Query\Query($this->getTitlesByQueryParams($driver), 'AllFields'),
                                                 $offset, $limit, new \VuFindSearch\ParamBag(['sort' => 'publishDate DESC']));

        return $response;
    }

    public function getRelatedAuthors(AuthorityRecordDriver &$driver)
    {
        $params = new \VuFindSearch\ParamBag();
        $params->set('fl', 'facet_counts');
        $params->set('facet', 'true');
        $params->set('facet.pivot', 'author_and_id_facet');
        $params->set('facet.limit', 9999);

        // Make sure we set offset+limit to 0, because we only want the facet counts
        // and not the rows itself for performance reasons.
        // (This could get very slow, e.g. Martin Luther where we have thousands of related datasets.)
        $titleRecords = $this->searchService->search('Solr',
                                                     new \VuFindSearch\Query\Query($this->getTitlesByQueryParams($driver), 'AllFields'),
                                                     0, 0, $params);

        $relatedAuthors = $titleRecords->getFacets()->getPivotFacets();
        $referenceAuthorKey = $driver->getUniqueID() . ':' . $driver->getTitle();

        // This is not an array but an ArrayObject, so unset() will cause an error
        // if the index does not exist => we need to check it with isset first.
        if (isset($relatedAuthors[$referenceAuthorKey]))
            unset($relatedAuthors[$referenceAuthorKey]);

        // custom sort, since solr can only sort by count but not alphabetically,
        // since the value starts with an id instead of a name.
        $relatedAuthors->uasort(function($a, $b) {
            $diff = $b['count'] - $a['count'];
            if ($diff != 0)
                return $diff;
            else {
                list($aId, $aTitle) = explode(':', $a['value']);
                list($aId, $bTitle) = explode(':', $b['value']);
                return strcmp($aTitle, $bTitle);
            }
        });

        return $relatedAuthors;
    }

    /**
     * Call this number with a variable number of arguments,
     * each containing either an author name/heading or an authority record driver.
     * ("..." == PHP splat operator)
     */
    public function getRelatedJointQueryParams(...$authors): string
    {
        $parts = [];
        foreach ($authors as $author) {
            $parts[] = '(' . $this->getTitlesByQueryParams($author) . ')';
        }
        return implode(' AND ', $parts);
    }

    public function getTimespans(AuthorityRecordDriver &$driver): string
    {
        return implode('<br>', $driver->getTimespans());
    }

    protected function getTitlesAboutQueryParams(&$author, $fuzzy=false): string
    {
        if ($author instanceof AuthorityRecordDriver) {
            $queryString = 'topic_id:"' . $author->getUniqueId() . '"';
            if ($fuzzy) {
                $queryString = 'OR topic_all:"' . $author->getTitle() . '"';
            }
        } else {
            $queryString = 'topic_all:"' . $author . '"';
        }
        return $queryString;
    }


    protected function getTitlesAboutQueryParamsChartDate(&$author): string
    {
        if ($author instanceof AuthorityRecordDriver) {
            $queryString = 'topic_id:"' . $author->getUniqueId() . '"';
        } else {
            $queryString = 'topic_all:"' . $author . '"';
        }
        return $queryString;
    }

    protected function getTitlesByQueryParams(&$author, $fuzzy=false): string
    {
        if ($author instanceof AuthorityRecordDriver) {
            $queryString = 'author_id:"' . $author->getUniqueId() . '"';
            $queryString .= ' OR author2_id:"' . $author->getUniqueId() . '"';
            $queryString .= ' OR author_corporate_id:"' . $author->getUniqueId() . '"';
            if ($fuzzy) {
                $queryString .= ' OR author:"' . $author->getTitle() . '"';
                $queryString .= ' OR author2:"' . $author->getTitle() . '"';
                $queryString .= ' OR author_corporate:"' . $author->getTitle() . '"';
            }
        } else {
            $queryString = 'author:"' . $author . '"';
            $queryString .= ' OR author2:"' . $author . '"';
            $queryString .= ' OR author_corporate:"' . $author . '"';
        }
        return $queryString;
    }

    public function getTitlesAboutUrl(AuthorityRecordDriver &$driver): string
    {
        $urlHelper = $this->viewHelperManager->get('url');
        return $urlHelper('search-results', [], ['query' => ['lookfor' => $this->getTitlesAboutQueryParams($driver)]]);
    }

    /**
     * Get URL to search result with all titles for this authority record.
     * Moved here because it needs to be the same in several locations, e.g.:
     * - authority page
     * - biblio result-list
     * - biblio core (data-authors)
     */
    public function getTitlesByUrl(AuthorityRecordDriver &$driver): string
    {
        $urlHelper = $this->viewHelperManager->get('url');
        return $urlHelper('search-results', [], ['query' => ['lookfor' => $this->getTitlesByQueryParams($driver)]]);
    }

    public function getTitlesByUrlNameOrID($authorName, $authorId = null): string
    {
        $urlHelper = $this->viewHelperManager->get('url');
        return $urlHelper('search-results', [], ['query' => ['lookfor' => $this->getTitlesByQueryParamsNameOrID($authorName, $authorId)]]);
    }

    protected function getTitlesByQueryParamsNameOrID($authorName, $authorId = null): string
    {
        if ($authorId != null) {
            $queryString = 'author_id:"' . $authorId . '"';
            $queryString .= ' OR author2_id:"' . $authorId . '"';
            $queryString .= ' OR author_corporate_id:"' . $authorId . '"';
            $queryString .= ' OR author:"' . $authorName . '"';
            $queryString .= ' OR author2:"' . $authorName . '"';
            $queryString .= ' OR author_corporate:"' . $authorName . '"';
        } else {
            $queryString = 'author:"' . $authorName . '"';
            $queryString .= ' OR author2:"' . $authorName . '"';
            $queryString .= ' OR author_corporate:"' . $authorName . '"';
        }
        return $queryString;
    }

    public function getChartData(AuthorityRecordDriver &$driver): array
    {
        $params = ["facet.field"=>"publishDate",
                   "facet.mincount"=>"1",
                   "facet"=>"on",
                   "facet.sort"=>"count"];

        $identifier = 'Solr';
        $publishingData = $this->searchService->search($identifier,
                                                 new \VuFindSearch\Query\Query($this->getTitlesByQueryParams($driver), 'AllFields'),
                                                 0, 0, new \VuFindSearch\ParamBag($params));
        $allFacets = $publishingData->getFacets();
        $publishFacet = $allFacets->getFieldFacets();
        $publishArray = $publishFacet['publishDate']->toArray();
        $publishDates = array_keys($publishArray);

        $aboutData = $this->searchService->search($identifier,
                                                 new \VuFindSearch\Query\Query($this->getTitlesAboutQueryParamsChartDate($driver), 'AllFields'),
                                                 0, 0, new \VuFindSearch\ParamBag($params));

        $allFacetsAbout = $aboutData->getFacets();
        $aboutFacet = $allFacetsAbout->getFieldFacets();
        $aboutArray = $aboutFacet['publishDate']->toArray();

        $aboutDates = array_keys($aboutArray);

        $allDates = array_merge($publishDates, $aboutDates);
        $allDates = array_unique($allDates);

        $allDatesKeys = array_values($allDates);
        asort($allDatesKeys);

        $chartData = [];
        foreach($allDatesKeys as $oneDate) {
            if(!empty($oneDate)){
                $by = '';
                $about = '';
                if (array_key_exists($oneDate, $publishArray)) {
                    $by = $publishArray[$oneDate];
                }
                if (array_key_exists($oneDate, $aboutArray)) {
                    $about = $aboutArray[$oneDate];
                }
                $chartData[] = array($oneDate,$by,$about);
            }
        }

        return $chartData;
    }

    public function getTopicsData(AuthorityRecordDriver &$driver): array
    {
        $translatorLocale = $this->getTranslatorLocale();

        $settings = [
            'maxNumber' => 10,
            'minNumber' => 2,
            'firstTopicLength' => 10,
            'firstTopicWidth' => 10,
            'maxTopicRows' => 20,
            'minWeight' => 0,
            'filter' => 'topic_cloud',
            'paramBag' => [
                'sort' => 'publishDate DESC',
                'fl' => 'id,topic_cloud_'.$translatorLocale
             ],
            'searchType' => 'AllFields'
        ];

        $identifier = 'Solr';

        // Note: This query might be critical to peformance. Also set 'fl' parameter
        //       to reduce the result size and avoid out of memory problems.
        //       Example: Martin Luther, 133813363
        $titleRecords = $this->searchService->search($identifier,
                                                 new \VuFindSearch\Query\Query($this->getTitlesByQueryParams($driver), $settings['searchType']),
                                                 0, 9999, new \VuFindSearch\ParamBag($settings['paramBag']));

        $countedTopics = [];
        foreach ($titleRecords as $titleRecord) {

            $keywords = $titleRecord->getTopics($translatorLocale);
            foreach ($keywords as $keyword) {
                if(strpos($keyword, "\\") !== false) {
                    $keyword = str_replace("\\", "", $keyword);
                }
                if (isset($countedTopics[$keyword])) {
                    ++$countedTopics[$keyword];
                } else {
                    $countedTopics[$keyword] = 1;
                }
            }
        }

        arsort($countedTopics);

        $urlHelper = $this->viewHelperManager->get('url');
        $tuefindHelper = $this->viewHelperManager->get('tuefind');

        $lookfor = $this->getTitlesByQueryParams($driver);

        if ($tuefindHelper->getTueFindFlavour() == 'ixtheo') {
            $settings['filter'] = $settings['filter'];
        }

        $topicLink = $urlHelper('search-results').'?lookfor='.$lookfor.'&type='.$settings['searchType'].'&filter[]='.$settings['filter'].':';

        $topicsArray = [];
        foreach($countedTopics as $topic => $topicCount) {
            $topicsArray[] = ['topicTitle'=>$topic, 'topicCount'=>$topicCount, 'topicLink'=>$topicLink.$topic];
        }
        $mainTopicsArray = [];
        if(!empty($topicsArray)){
            $topWeight = $settings['maxNumber'];
            $firstWeight = $topicsArray[0]['topicCount'];
            for($i=0;$i<count($topicsArray);$i++) {
                if($i == 0) {
                    if(mb_strlen($topicsArray[$i]['topicTitle']) > $settings['firstTopicLength']) {
                        $topicsArray[$i]['topicTitle'] = mb_strimwidth($topicsArray[$i]['topicTitle'], 0, $settings['firstTopicWidth'] + 3, '...');
                    }
                }
                $one = $topicsArray[$i];
                if($firstWeight != $topicsArray[$i]['topicCount']) {
                    $firstWeight = $topicsArray[$i]['topicCount'];
                    if($topWeight != $settings['minWeight']) {
                        $topWeight--;
                    }else{
                        $topWeight = $settings['minWeight'];
                    }
                }
                $one['topicNumber'] = $topWeight;
                $mainTopicsArray[] = $one;
            }
        }

        return [$mainTopicsArray, $settings];
    }

    public function userHasRightsOnRecord(\VuFind\Db\Row\User $user, TitleRecordDriver &$titleRecord): bool
    {
        $userAuthorities = $this->dbTableManager->get('user_authority')->getByUserId($user->id);
        $userAuthorityIds = [];
        foreach ($userAuthorities as $userAuthority) {
            $userAuthorityIds[] = $userAuthority->authority_id;
        }

        $recordAuthorIds = array_merge($titleRecord->getPrimaryAuthorsIds(), $titleRecord->getSecondaryAuthorsIds(), $titleRecord->getCorporateAuthorsIds());
        $matchingAuthorIds = array_intersect($userAuthorityIds, $recordAuthorIds);
        return count($matchingAuthorIds) > 0;
    }

    public function recordExists($authorityId)
    {
        $loadResult = $this->recordLoader->load($authorityId, 'SolrAuth', /* $tolerate_missing=*/ true);
        if ($loadResult instanceof \VuFind\RecordDriver\Missing)
            return false;

        return $loadResult;
    }

}
