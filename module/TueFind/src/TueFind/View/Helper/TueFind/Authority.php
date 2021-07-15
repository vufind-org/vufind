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

    public function getExternalReferences(AuthorityRecordDriver &$driver): string
    {
        $references = $driver->getExternalReferences();
        if (count($references) == 0)
            return '';

        usort($references, function($a, $b) { return strcmp($a['title'], $b['title']); });

        $display = '';
        foreach ($references as $reference)
            $display .= '<a href="' . $reference['url'] . '" target="_blank" property="sameAs">' . htmlspecialchars($reference['title']) . '</a><br>';

        return $display;
    }

    public function getName(AuthorityRecordDriver &$driver): string
    {
        $name = $driver->getTitle();
        $name = trim(preg_replace('"\d+\-?\d*"', '', $name));
        return '<span property="name">' . $name . '</span>';
    }

    public function getOccupations(AuthorityRecordDriver &$driver): string
    {
        $occupations = $driver->getOccupations();
        $occupationsDisplay = '';
        foreach ($occupations as $occupation) {
            if ($occupationsDisplay != '')
                $occupationsDisplay .= ' / ';
            $occupationsDisplay .= '<span property="hasOccupation">' . $occupation . '</span>';
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

            $type = $relation['type'] == 'Veranstalter' ? 'organizer' : 'contributor';
            $relationsDisplay .= '<span property="' . $type . '" typeof="Organization">';

            $recordExists = isset($relation['id']) && $this->recordExists($relation['id']);
            if ($recordExists) {
                $url = $urlHelper('solrauthrecord', ['id' => $relation['id']]);
                $relationsDisplay .= '<a property="sameAs" href="' . $url . '">';
            }

            $relationsDisplay .= '<span property="name">' . $relation['name'] . '</span>';

            $additionalValuesConfig = ['type', 'timespan'];
            $additionalValuesString = '';
            foreach ($additionalValuesConfig as $additionalValue) {
                if (isset($relation[$additionalValue])) {
                    if ($additionalValuesString != '')
                        $additionalValuesString .= ', ';
                    $additionalValuesString .= htmlspecialchars($relation[$additionalValue]);
                }
            }
            if ($additionalValuesString != '')
                $relationsDisplay .= ' (' . $additionalValuesString . ')';

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
                $relationsDisplay .= ' (' . htmlspecialchars($relation['type']) . ')';

            if ($recordExists)
                $relationsDisplay .= '</a>';

            $relationsDisplay .= '</span>';
        }
        return $relationsDisplay;
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
                                                 new \VuFindSearch\Query\Query($this->getTitlesAboutQueryParams($driver) . '"', 'AllFields'),
                                                 $offset, $limit, new \VuFindSearch\ParamBag(['sort' => 'publishDate DESC']));

        return $response;
    }

    public function getNewestTitlesFrom(AuthorityRecordDriver &$driver, $offset=0, $limit=10)
    {
        // We use 'Solr' as identifier here, because the RecordDriver's identifier would be "SolrAuth"
        $identifier = 'Solr';
        $response = $this->searchService->search($identifier,
                                                 new \VuFindSearch\Query\Query($this->getTitlesFromQueryParams($driver), 'AllFields'),
                                                 $offset, $limit, new \VuFindSearch\ParamBag(['sort' => 'publishDate DESC']));

        return $response;
    }

    public function getRelatedAuthors(AuthorityRecordDriver &$driver): array
    {
        $titleRecords = $this->searchService->search('Solr',
                                                     new \VuFindSearch\Query\Query($this->getTitlesFromQueryParams($driver), 'AllFields'),
                                                     0, 9999);

        $referenceAuthorName = $driver->getTitle();

        $relatedAuthors = [];
        foreach ($titleRecords as $titleRecord) {
            $titleAuthors = $titleRecord->getDeduplicatedAuthors();
            foreach ($titleAuthors as $category => $categoryAuthors) {
                foreach ($categoryAuthors as $titleAuthorName => $titleAuthorDetails) {
                    if ($titleAuthorName == $referenceAuthorName)
                        continue;

                    $titleAuthorId = $titleAuthorDetails['id'][0] ?? null;
                    if ($titleAuthorId && $titleAuthorId == $driver->getUniqueID())
                        continue;

                    if (!isset($relatedAuthors[$titleAuthorName]))
                        $relatedAuthors[$titleAuthorName] = ['count' => 1];
                    else
                        ++$relatedAuthors[$titleAuthorName]['count'];
                    if (isset($titleAuthorId))
                        $relatedAuthors[$titleAuthorName]['id'] = $titleAuthorId;
                }
            }
        }

        uasort($relatedAuthors, function($a, $b) {
            return $b['count'] - $a['count'];
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
            $parts[] = '(' . $this->getTitlesFromQueryParams($author) . ')';
        }
        return implode(' AND ', $parts);
    }

    protected function getTitlesAboutQueryParams(&$author): string
    {
        if ($author instanceof AuthorityRecordDriver) {
            $queryString = 'topic_all:"' . $author->getTitle() . '"';
        } else {
            $queryString = 'topic_all:"' . $author . '"';
        }
        return $queryString;
    }

    protected function getTitlesFromQueryParams(&$author): string
    {
        if ($author instanceof AuthorityRecordDriver) {
            $queryString = 'author_id:"' . $author->getUniqueId() . '"';
            $queryString .= ' OR author2_id:"' . $author->getUniqueId() . '"';
            $queryString .= ' OR author_corporate_id:"' . $author->getUniqueId() . '"';
            $queryString .= ' OR author:"' . $author->getTitle() . '"';
            $queryString .= ' OR author2:"' . $author->getTitle() . '"';
            $queryString .= ' OR author_corporate:"' . $author->getTitle() . '"';
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
    public function getTitlesFromUrl(AuthorityRecordDriver &$driver): string
    {
        $urlHelper = $this->viewHelperManager->get('url');
        return $urlHelper('search-results', [], ['query' => ['lookfor' => $this->getTitlesFromQueryParams($driver)]]);
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
