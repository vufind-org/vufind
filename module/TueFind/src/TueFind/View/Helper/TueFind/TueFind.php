<?php

namespace TueFind\View\Helper\TueFind;

/**
 * General View Helper for TueFind, containing miscellaneous functions
 */
class TueFind extends \Zend\View\Helper\AbstractHelper
              implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Check if a facet value is equal to '[Unassigned]' or its translation
     *
     * @param string $value
     * @return bool
     */
    function isUnassigned($value) {
        return ($value == '[Unassigned]') || ($value == $this->translate('[Unassigned]'));
    }

    /**
     * Calculate percentage of a count related to a solr search result
     *
     * @param int $count
     * @param \VuFind\Search\Solr\Results $results
     *
     * @return double
     */
    function getOverallPercentage($count, \VuFind\Search\Solr\Results $results) {
        return ($count * 100) / $results->getResultTotal();
    }

    /**
     * Calculate percentage and get localized string
     *
     * @param \Zend\View\Renderer\PhpRenderer $view
     * @param int $count
     * @param \VuFind\Search\Solr\Results $results
     *
     * @return string
     */
    function getLocalizedOverallPercentage(\Zend\View\Renderer\PhpRenderer $view,
                                           $count, \VuFind\Search\Solr\Results $results) {

        $percentage = $this->getOverallPercentage($count, $results);
        return $percentage > 0.1 ? $view->localizedNumber($percentage, 1) : "&lt; 0.1";
    }
}
