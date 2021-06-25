<?php

namespace TueFind\View\Helper\TueFind;

use \TueFind\RecordDriver\SolrAuthMarc as AuthorityRecordDriver;

/**
 * View Helper for TueFind, containing functions related to authority data + schema.org
 */
class Authority extends \Laminas\View\Helper\AbstractHelper
                implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    protected $recordLoader;

    protected $searchService;

    protected $viewHelperManager;

    public function __construct(\VuFindSearch\Service $searchService,
                                \Laminas\View\HelperPluginManager $viewHelperManager,
                                \VuFind\Record\Loader $recordLoader)
    {
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

            if (isset($relation['type']))
                $relationsDisplay .= ' (' . htmlspecialchars($relation['type']) . ')';

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

    /**
     * Get titles of this authority to show in a preview box
     */
    public function getTitles(AuthorityRecordDriver &$driver, $offset=0, $limit=10)
    {
        // We use 'Solr' as identifier here, because the RecordDriver's identifier would be "SolrAuth"
        $identifier = 'Solr';
        $response = $this->searchService->search($identifier,
                                                 new \VuFindSearch\Query\Query('author_id:"' . $driver->getUniqueId() . '"', 'AllFields'),
                                                 $offset, $limit, new \VuFindSearch\ParamBag(['sort' => 'publishDate DESC']));

        return $response;
    }

    public function recordExists($authorityId)
    {
        $loadResult = $this->recordLoader->load($authorityId, 'SolrAuth', /* $tolerate_missing=*/ true);
        return !($loadResult instanceof \VuFind\RecordDriver\Missing);
    }
}
