<?php

namespace TueFind\View\Helper\TueFind;

use \TueFind\RecordDriver\SolrAuthMarc as AuthorityRecordDriver;

/**
 * View Helper for TueFind, containing functions related to authority data + schema.org
 */
class Authority extends \Zend\View\Helper\AbstractHelper
                implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    protected $searchService;

    public function __construct(\VuFindSearch\Service $searchService) {
        $this->searchService = $searchService;
    }

    /**
     * Get authority birth information for display
     *
     * @return string
     */
    public function getBirth(AuthorityRecordDriver &$driver) {
        $display = '';

        $birthDate = $driver->getBirthDateOrYear();
        if ($birthDate != '') {
            $display .= $this->getView()->transEsc('Born: ');
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
    private function getDateTimeProperty($datetimeCandidate, $propertyId) {
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
    public function getDeath(AuthorityRecordDriver &$driver) {
        $display = '';
        $deathDate = $driver->getDeathDateOrYear();
        if ($deathDate != '') {
            $display .= $this->getView()->transEsc('Died: ');
            $display .= $this->getDateTimeProperty($deathDate, 'deathDate');
            $deathPlace = $driver->getDeathPlace();
            if ($deathPlace != null)
                $display .= ', <span property="deathPlace">' . $deathPlace . '</span>';
        }
        return $display;
    }

    public function getName(AuthorityRecordDriver &$driver) {
        $name = $driver->getTitle();
        $name = trim(preg_replace('"\d+\-?\d*"', '', $name));
        return '<span property="name">' . $name . '</span>';
    }

    public function getProfessions(AuthorityRecordDriver &$driver) {
        $professions = $driver->getProfessions();
        $professions_display = '';
        foreach ($professions as $profession) {
            if ($professions_display != '')
                $professions_display .= '/';
            $professions_display .= '<span property="hasOccupation">' . $profession['title'] . '</span>';
        }
        return $professions_display;
    }

    /**
     * Get titles of this authority to show in a preview box
     */
    public function getTitles(AuthorityRecordDriver &$driver, $offset=0, $limit=10) {
        // We use 'Solr' as identifier here, because the RecordDriver's identifier would be "SolrAuth"
        $identifier = 'Solr';
        $response = $this->searchService->search($identifier,
                                                 new \VuFindSearch\Query\Query('author_id:"' . $driver->getUniqueID() . '"', 'AllFields'),
                                                 $offset, $limit);

        return $response;
    }
}
