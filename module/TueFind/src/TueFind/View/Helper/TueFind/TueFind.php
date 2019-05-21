<?php

namespace TueFind\View\Helper\TueFind;

use Interop\Container\ContainerInterface;

/**
 * General View Helper for TueFind, containing miscellaneous functions
 */
class TueFind extends \Zend\View\Helper\AbstractHelper
              implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    protected $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
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
     * Get TueFind configuration from INI file.
     *
     * @param string $id Config file id, default 'tuefind'
     *                   use e.g. 'config' for vufind's config.ini instead
     *
     * @return \Zend\Config\Config
     */
    function getConfig($id = 'tuefind') {
        return $this->container->get('VuFind\Config\PluginManager')->get($id);
    }

    /**
     * Get name of the current controller
     * (If no Controller is found in URL, returns default value 'index')
     *
     * @return string
     */
    function getControllerName() {
        $default = 'index';
        $route_match = $this->container->get('application')->getMvcEvent()->getRouteMatch();
        if ($route_match == null)
            return $default;
        else
            return $route_match->getParam('controller', $default);
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
        $config = $this->container->get('VuFind\Config')->get('config');
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
     * Search for specific RSS feed icon, return generic RSS icon if not found
     *
     * @param string $rssFeedId
     *
     * @return string
     */
    function getRssFeedIcon($rssFeedId) {
        $imgSrc = $this->getView()->imageLink('rss/' . $rssFeedId . '.png');
        if ($imgSrc == null)
            $imgSrc = $this->getView()->imageLink('rss/rss.png');

        return $imgSrc;
    }

    /**
     * Parse the RSS feed and return a short overview of the first few entries
     *
     * @param int $max_item_count           Max items to read from file
     *
     * @return array
     */
    function getRssNewsEntries($max_item_count=null) {
        $rss_feed_path = $this->getConfig()->General->rss_feed_path;
        $rss_items = [];

        $dom = new \DOMDocument();
        if (@$dom->load($rss_feed_path)) {
            $items = $dom->getElementsByTagName('item');
            $i = 0;
            foreach ($items as $item) {
                if ($max_item_count !== null && $i >= $max_item_count)
                    break;

                $rss_item = [];
                $child = $item->firstChild;
                while ($child != null) {
                    if ($child instanceof \DOMElement) {
                        $rss_item[$child->tagName] = htmlspecialchars_decode($child->nodeValue);
                    }
                    $child = $child->nextSibling;
                }

                $rss_items[] = $rss_item;
                ++$i;
            }
        }

        return $rss_items;
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

    /**
      * Get the user address from a logged in user
      * @return string
      */
    function getUserEmail() {
        $auth = $this->container->get('ViewHelperManager')->get('Auth');
        $manager = $auth->getManager();
        return  ($user = $manager->isLoggedIn()) ? $user->email : "";
    }

    /**
      * Get the user address from a logged in user
      * @return string
      */
    function getUserLastname() {
        $auth = $this->container()->get('ViewHelperManager')->get('Auth');
        $manager = $auth->getManager();
        return  ($user = $manager->isLoggedIn()) ? $user->lastname : "";
    }

    /**
    * Get the user address from a logged in user
    * @return string
    */
    function getUserFirstName() {
        $auth = $this->container->get('ViewHelperManager')->get('Auth');
        $manager = $auth->getManager();
        return  ($user = $manager->isLoggedIn()) ? $user->firstname : "";
    }

    /**
     * Check if user account deletion is enabled in config file.
     */
    function isUserAccountDeletionEnabled() {
        $config = $this->container->get('VuFind\Config')->get('config');
        return !empty($config->Authentication->account_deletion);
    }
}
