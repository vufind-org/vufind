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
     * Convert a Date/Time string to ISO 8601 format.
     *
     * If the given datetime consists only of a year, return the plain year.
     * This is supported by ISO 8601 (see RFC 3339: date = datespec-full / datespec-year /...)
     *
     * Else return a full ISO 8601 conform string with month/day/etc.
     *
     * On any error, the original given datetime string is returned.
     *
     * @param string $datetimeRaw
     * @return string
     */
    public function convertDateTimeToIso8601($datetimeRaw) {
        $datetimeCleaned = preg_replace('"[\[\]]"', '', $datetimeRaw);
        if (preg_match('"^\d{4}$"', $datetimeCleaned))
            return $datetimeCleaned;

        $datetime = strtotime($datetimeRaw);
        if ($datetime === false)
            return $datetimeRaw;

        return date('c', $datetime);
    }

    /**
     * Check if a facet value is equal to '[Unassigned]' or its translation
     *
     * @param string $value
     * @return bool
     */
    public function isUnassigned($value) {
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
    public function getConfig($id = 'tuefind') {
        return $this->container->get('VuFind\Config\PluginManager')->get($id);
    }

    /**
     * Get name of the current controller
     * (If no Controller is found in URL, returns default value 'index')
     *
     * @return string
     */
    public function getControllerName() {
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
    public function getOverallPercentage($count, \VuFind\Search\Solr\Results $results) {
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
    public function getLocalizedOverallPercentage(\Zend\View\Renderer\PhpRenderer $view,
                                           $count, \VuFind\Search\Solr\Results $results) {
        $percentage = $this->getOverallPercentage($count, $results);
        return $percentage > 0.1 ? $view->localizedNumber($percentage, 1) : "&lt; 0.1";
    }

    /**
     * Get Team Email Address
     *
     * @return string
     */
    public function getTeamEmail() {
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
    public function formatRoles($roles) {

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
          return implode(', ', $translatedRoles);
        };
        return ' (' . implode(', ', array_unique(array_map($translate, $roles))) . ')';
    }

    /**
     * Analyze a list of facets if at least one of them is chosen
     * @param facet list array
     *
     * @return bool
     */
    public function atLeastOneFacetChosen($list) {
        foreach($list as $i => $thisFacet)
            if ($thisFacet['isApplied'])
                return true;
        return false;
    }

    /**
     * Get metadata for aggregated RSS feeds
     *
     * @return array
     */
    public function getRssFeeds() {
        $rssConfigPath = $this->getConfig()->General->rss_config_path;
        $rssConfig = parse_ini_file($rssConfigPath, true, INI_SCANNER_RAW);

        $rssFeeds = [];
        foreach ($rssConfig as $rssConfigKey => $rssConfigValue) {
            if (is_array($rssConfigValue) && isset($rssConfigValue['feed_url']))
                $rssFeeds[$rssConfigKey] = $rssConfigValue;
        }

        ksort($rssFeeds);
        return $rssFeeds;
    }

    /**
     * Search for specific RSS feed icon, return generic RSS icon if not found
     *
     * @param string $rssFeedId
     *
     * @return string
     */
    public function getRssFeedIcon($rssFeedId='rss') {
        $imgSrc = $this->getView()->imageLink('rss/' . $rssFeedId . '.png');
        if ($imgSrc == null)
            $imgSrc = $this->getView()->imageLink('rss/rss.png');

        return $imgSrc;
    }

    /**
     * Filter unwanted stuff from RSS item description (especially images)
     *
     * @param string $htmlPart
     *
     * @return string
     */
    private function filterRssItemDescription(string $htmlPart): string {
        $html = '<html><meta charset="UTF-8"/><body id="htmlPartWrapper">'.$htmlPart.'</body></html>';

        $dom = new \DOMDocument();
        $dom->recover = true;
        $dom->strictErrorChecking = false;
        if (!@$dom->loadHTML($html))
            return $htmlPart;

        $wrapper = $dom->getElementById('htmlPartWrapper');

        // Elements need to be copied before removing to avoid iterator problem
        $images = $wrapper->getElementsByTagName('img');
        $imageReferences = [];
        foreach ($images as $image)
            $imageReferences[] = $image;
        foreach ($imageReferences as $imageReference)
            $imageReference->parentNode->removeChild($imageReference);

        return $dom->saveHTML($wrapper);
    }

    /**
     * Get URL to redirect page which also saves the redirect with timestamp for later analysis
     *
     * @param string $targetUrl
     * @param string $group
     *
     * @return string
     */
    public function getRedirectUrl(string $targetUrl, string $group=null): string {
        $urlHelper = $this->container->get('ViewHelperManager')->get('url');
        return $urlHelper('redirect', ['url' => base64_encode($targetUrl), 'group' => $group]);
    }

    /**
     * Parse the RSS feed and return a short overview of the first few entries
     *
     * @param int  $max_item_count            Max items to read from file
     * @param bool $only_newest_item_per_feed Only the newest item per feed will be returned.
     *
     * @return array
     */
    public function getRssNewsEntries($max_item_count=null, $only_newest_item_per_feed=false) {
        $rss_feed_path = $this->getConfig()->General->rss_feed_path;
        $rss_items = [];

        $dom = new \DOMDocument();
        if (@$dom->load($rss_feed_path)) {
            $items = $dom->getElementsByTagName('item');
            $i = 0;
            $processed_feeds = [];
            foreach ($items as $item) {
                if ($max_item_count !== null && $i >= $max_item_count)
                    break;

                $rss_item = [];
                $child = $item->firstChild;
                while ($child != null) {
                    if ($child instanceof \DOMElement) {
                        $value = htmlspecialchars_decode($child->nodeValue);
                        if ($child->tagName == 'description')
                            $value = $this->filterRssItemDescription($value);
                        $rss_item[$child->tagName] = $value;
                    }
                    $child = $child->nextSibling;
                }

                if ($only_newest_item_per_feed === false || !in_array($rss_item['tuefind:rss_title'], $processed_feeds)) {
                    $rss_items[] = $rss_item;
                    ++$i;
                }
                $processed_feeds[] = $rss_item['tuefind:rss_title'];
            }
        }

        return $rss_items;
    }

    /**
     * Get URL of our own generated RSS feed (from rss_aggregator)
     *
     * @return string
     */
    public function getRssNewsUrl() {
        $rssFeedPath = $this->getConfig()->General->rss_feed_path;
        if (!is_file($rssFeedPath))
            return false;

        return str_replace(getenv('VUFIND_HOME') . '/public', '', $rssFeedPath);
    }

    /**
      * Get TueFind Instance as defined by VUFIND_LOCAL_DIR variable
      * @return string
      */
    public function getTueFindInstance() {
        return basename(getenv('VUFIND_LOCAL_DIR'));
    }

    /**
      * Derive textual description of TueFind (Subsystems of IxTheo return IxTheo)
      * @return string or false of no matching value could be found
      */
    public function getTueFindType() {
        $instance = $this->getTueFindInstance();
        $instance = preg_replace('/\d+$/', "", $instance);
        switch ($instance) {
            case 'ixtheo':
            case 'bibstudies':
            case 'churchlaw':
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
    public function getTueFindFID() {
        $instance = $this->getTueFindInstance();
        $instance = preg_replace('/\d+$/', "", $instance);
        switch($instance) {
            case 'ixtheo':
            case 'bibstudies':
            case 'churchlaw':
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
    public function getUserEmail() {
        $auth = $this->container->get('ViewHelperManager')->get('auth');
        $manager = $auth->getManager();
        return ($user = $manager->isLoggedIn()) ? $user->email : "";
    }

    /**
    * Get the first name of the logged in user
    * @return string
    */
    public function getUserFirstName() {
        $auth = $this->container->get('ViewHelperManager')->get('auth');
        $manager = $auth->getManager();
        return ($user = $manager->isLoggedIn()) ? $user->firstname : "";
    }

    /**
     * Get the full name of the logged in user
     * @return string
     */
    public function getUserFullName() {
        $auth = $this->container->get('ViewHelperManager')->get('auth');
        $manager = $auth->getManager();
        return ($user = $manager->isLoggedIn()) ? $user->firstname . ' ' . $user->lastname : "";
    }

    /**
      * Get the last name of the logged in user
      * @return string
      */
    public function getUserLastName() {
        $auth = $this->container->get('ViewHelperManager')->get('auth');
        $manager = $auth->getManager();
        return ($user = $manager->isLoggedIn()) ? $user->lastname : "";
    }

    /**
     * Check if user account deletion is enabled in config file.
     */
    public function isUserAccountDeletionEnabled() {
        $config = $this->container->get('VuFind\Config')->get('config');
        return !empty($config->Authentication->account_deletion);
    }

    public function printSuperiorSeries($superior_record) {
        $superior_series = $superior_record->tryMethod('getSeries');
        if (is_array($superior_series)) {
            foreach ($superior_series as $current) {
                echo 'T3 - ' . (is_array($current) ? $current['name'] : $current) . "\r\n";
                $volume =  $current['number'];
                if (!empty($volume))
                    echo 'SV - ' . "$volume\r\n";
            }
            return true;
        }
        return false;
    }

    public function printPublicationInformation($pubPlaces, $pubDates, $pubNames) {
        if (is_array($pubPlaces) && is_array($pubDates) && is_array($pubNames) &&
            !(empty($pubPlaces) && empty($pubDates) && empty($pubNames))) {
             $total = min(count($pubPlaces), count($pubDates), count($pubNames));
             // if we have pub dates but no other details, we still want to export the year:
             if ($total == 0 && count($pubDates) > 0) {
                 $total = 1;
             }
             for ($i = 0; $i < $total; $i++) {
                 if (isset($pubPlaces[$i])) {
                     echo "CY  - " . rtrim(str_replace(array('[', ']'), '', $pubPlaces[$i]), ': '). "\r\n";
                 }
                 if (isset($pubNames[$i])) {
                     echo "PB  - " . rtrim($pubNames[$i], ", ") . "\r\n";
                 }
                 $date = trim($pubDates[$i], '[]. ');
                 if (strlen($date) > 4) {
                     $date = $this->dateTime()->extractYear($date);
                 }
                 if ($date) {
                     echo 'PY  - ' . "$date\r\n";
                 }
             }
             return true;
         }
         return false;
    }
}
