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
    function getBirth(AuthorityRecordDriver &$driver) {
        $display = '';

        $birthDate = $driver->getBirthDateOrYear();
        if ($birthDate != '') {
            $display .= $this->getView()->transEsc('Born: ');
            $display .= '<span property="birthDate">' . $birthDate . '</span>';
            $birthPlace = $driver->getBirthPlace();
            if ($birthPlace != null)
                $display .= ', <span property="birthPlace">' . $birthPlace . '</span>';
        }

        return $display;
    }

    /**
     * Get authority death information for display
     *
     * @return string
     */
    function getDeath(AuthorityRecordDriver &$driver) {
        $display = '';
        $deathDate = $driver->getDeathDateOrYear();
        if ($deathDate != '') {
            $display .= $this->getView()->transEsc('Died: ');
            $display .= '<span property="deathDate">' . $deathDate . '</span>';
            $deathPlace = $driver->getDeathPlace();
            if ($deathPlace != null)
                $display .= ', <span property="deathPlace">' . $deathPlace . '</span>';
        }
        return $display;
    }

    function getName(AuthorityRecordDriver &$driver) {
        $name = $driver->getTitle();
        $name = trim(preg_replace('"\d+(-\d+)?"', '', $name));
        return '<span property="name">' . $name . '</span>';
    }

    function getProfessions(AuthorityRecordDriver &$driver) {
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
    function getTitles(AuthorityRecordDriver &$driver, $offset=0, $limit=10) {
        // We use 'Solr' as identifier here, because the RecordDriver's identifier would be "SolrAuth"
        $identifier = 'Solr';
        $response = $this->searchService->search($identifier,
                                                 new \VuFindSearch\Query\Query('author_id:"' . $driver->getUniqueID() . '"', 'AllFields'),
                                                 $offset, $limit);

        return $response;
    }
}
