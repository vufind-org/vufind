<?php

namespace TueFind\View\Helper\TueFind;

use Zend\ServiceManager\ServiceManager;

/**
 * General View Helper for TueFind, containing miscellaneous functions
 */
class TueFind extends \Zend\View\Helper\AbstractHelper
              implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    protected $sm;

    public function __construct(ServiceManager $sm) {
        $this->sm = $sm;
    }

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
     * Get name of the current controller
     * (If no Controller is found in URL, returns default value 'index')
     *
     * @return string
     */
    function getControllerName() {
        return $this->sm->getServiceLocator()->get('application')->getMvcEvent()->getRouteMatch()->getParam('controller', 'index');
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

    /**
     * Get Team Email Address
     *
     * @return string
     */
    function getTeamEmail() {
        $config = $this->sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $team_email = isset($config->Site->email_team) ? $config->Site->email_team : '';
        return $team_email;
    }

   /**
    * Appropriately format the roles for authors
    * @param array roles
    *
    * @return string
    */
   function formatRoles($roles) {

       if (!isset($roles['role'])) {
           return '';
       }
       $translate = function ($arr) {
         $translatedRoles = array();
         foreach ($arr as $element) {
             if (!is_array($element)) {
               $translatedRoles[] = $this->translate('CreatorRoles::' . $element);
             } else {
               foreach ($element as $str) {
                   $translatedRoles[] = $this->translate('CreatorRoles::' . $str);
               }
             }
         }
         return implode(',', $translatedRoles);
       };
       return ' (' . implode(', ', array_unique(array_map($translate, $roles))) . ')';
   }

   /**
    * Analyze a list of facets if at least one of them is chosen
    * @param facet list array
    *
    * @return bool
    */
  function atLeastOneFacetChosen($list) {
      foreach($list as $i => $thisFacet)
          if ($thisFacet['isApplied'])
              return true;
      return false;

  }

  /**
    * Get TueFind Instance as defined by VUFIND_LOCAL_DIR variable
    * @return string
    */
  function getTueFindInstance() {
      return basename(getenv('VUFIND_LOCAL_DIR'));
  }


  /**
    * Derive textual description of TueFind (Subsystems of IxTheo return IxTheo)
    * @return string or false of no matching value could be found
    */
  function getTueFindType() {
      $instance = $this->getTueFindInstance();
      $instance = preg_replace('/\d+$/', "", $instance);
      switch ($instance) {
          case 'ixtheo':
          case 'bibstudies';
              return 'IxTheo';
          case 'relbib':
              return 'RelBib';
          case 'krimdok':
             return 'Krimdok';
      }
      return false;
  }


  /**
    * Derive the German FID denomination
    * @return string or false of no matching value could be found
    */
  function getTueFindFID() {
      $instance = $this->getTueFindInstance();
      $instance = preg_replace('/\d+$/', "", $instance);
      switch($instance) {
          case 'ixtheo':
          case 'bibstudies':
              return 'FID Theologie';
          case 'relbib':
              return 'FID Religionswissenschaften';
          case 'krimdok':
              return 'FID Kriminologie';
       }
       return false;
  }


}
