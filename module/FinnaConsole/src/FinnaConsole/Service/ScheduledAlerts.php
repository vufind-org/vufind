<?php
/**
 * Send scheduled alerts.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Service;

use Finna\Db\Row\User;
use Finna\Db\Table\Search;
use Finna\Search\Solr\Options;
use Finna\Search\Solr\Params;
use Finna\Search\UrlQueryHelper;
use VuFind\Date\Converter as DateConverter;
use Zend\Config\Config;
use Zend\Config\Reader\Ini as IniReader;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\Parameters;

/**
 * Send scheduled alerts.
 *
 * This service works in three phases:
 *
 * 1. If VUFIND_LOCAL_DIR environment variable is undefined,
 *    it is set to master VuFind configuration directory
 *    and the script is called again.
 *
 * 2. If no view URL (field 'finna_schedule_base_url' in table search)
 *    to process scheduled alerts for is supplied, all distinct view
 *    URLs are retrieved, and the script is called again for each URL.
 *
 * 3. Scheduled alerts for a given view are processed.
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class ScheduledAlerts extends AbstractService
{
    /**
     * Symbolic link name for institution default view
     *
     * @var string
     */
    protected $defaultPath = 'default';

    /**
     * Local configuration directory name
     *
     * @var string
     */
    protected $confDir = 'local';

    /**
     * Base directory for all views.
     *
     * @var string
     */
    protected $viewBaseDir = null;

    /**
     * VFIND_LOCAL_DIR environment variable.
     *
     * @var string
     */
    protected $localDir = null;

    /**
     * Current view local configuration directory.
     *
     * @var string
     */
    protected $baseDir = null;

    /**
     * View URL to send scheduled alerts for.
     *
     * @var string
     */
    protected $scheduleBaseUrl = null;

    /**
     * Datasource configuration
     *
     * @var Config
     */
    protected $datasourceConfig = null;

    /**
     * Main configuration
     *
     * @var Config
     */
    protected $mainConfig = null;

    /**
     * Facets
     *
     * @var array
     */
    protected $facets = null;

    /**
     * Table for saved searches
     *
     * @var Search
     */
    protected $searchTable = null;

    /**
     * Table for user accounts
     *
     * @var User
     */
    protected $userTable = null;

    /**
     * ServiceManager
     *
     * @var ServiceManager
     */
    protected $searviceManager = null;

    /**
     * Constructor
     *
     * @param ServiceManager $sm ServiceManager
     */
    public function __construct(ServiceManager $sm)
    {
        $this->serviceManager = $sm;

        $this->mainConfig
            = $sm->get('VuFind\Config')->get('config');

        $this->datasourceConfig
            = $sm->get('VuFind\Config')->get('datasources');

        $facets = $sm->get('VuFind\Config')->get('facets');
        $this->facets = $facets->Results->toArray();

        $tableManager = $sm->get('VuFind\DbTablePluginManager');
        $this->searchTable = $tableManager->get('Search');
        $this->userTable = $tableManager->get('User');
    }

    /**
     * Run service.
     *
     * @param array $arguments Command line arguments.
     *
     * @return boolean success
     */
    public function run($arguments)
    {
        $this->collectScriptArguments($arguments);

        if (!$this->localDir = getenv('VUFIND_LOCAL_DIR')) {
            $this->msg('Switching to VuFind configuration');
            $this->switchInstitution($this->baseDir);
        } else if (!$this->scheduleBaseUrl) {
            $this->processAlerts();
            exit(0);
        } else {
            $this->processViewAlerts();
            exit(0);
        }
    }

    /**
     * Collect script parameters and print usage
     * information when all required parameters were not given.
     *
     * @param array $argv Arguments
     *
     * @return void
     */
    protected function collectScriptArguments($argv)
    {
        // Base directory for all views.
        $this->viewBaseDir = isset($argv[0]) ? $argv[0] : '..';
        // Current view local configuration directory
        $this->baseDir = isset($argv[1]) ? $argv[1] : false;
        // Schedule base url for alerts to send
        $this->scheduleBaseUrl = isset($argv[2]) ? $argv[2] : false;

        if (!$this->viewBaseDir || !$this->baseDir) {
            echo $this->usage();
            exit(1);
        }
    }

    /**
     * Process all scheduled alerts grouped by view URLs.
     *
     * @return void
     */
    protected function processAlerts()
    {
        $baseDirs = $this->searchTable->getScheduleBaseUrls();
        $this->msg('Processing alerts for ' . count($baseDirs) . ' views: ');
        $this->msg('  ' . implode(', ', $baseDirs));
        foreach ($baseDirs as $url) {
            $parts = parse_url($url);
            list($institution, $rest) = explode('.', $parts['host']);
            if ($institution == 'www') {
                // Special case for www.finna.fi
                $institution = 'national';
            }
            $view = isset($parts['path']) ? substr($parts['path'], 1) : false;

            if (!$path = $this->resolveViewPath($institution, $view)) {
                $this->err("Skipping alerts for view $url");
                continue;
            }
            $this->switchInstitution($path, $url);
        }
    }

    /**
     * Switch application configuration by calling this script from a
     * view's directory and using local configuration of the view.
     *
     * @param string $localDir        View local configuration directory.
     * @param string $scheduleBaseUrl View URL to send scheduled alerts for.
     *                                (optional)
     *
     * @return void
     */
    protected function switchInstitution($localDir, $scheduleBaseUrl = false)
    {
        $appDir = substr($localDir, 0, strrpos($localDir, "/{$this->confDir}"));
        $script = "$appDir/util/scheduled_alerts.php";

        $args = [];
        $args[] = $this->viewBaseDir;
        $args[] = $localDir;
        if ($scheduleBaseUrl) {
            $args[] = "'$scheduleBaseUrl'";
        }

        $cmd = "VUFIND_LOCAL_DIR='$localDir'";
        $cmd .= " php -d short_open_tag=1 '$script' " . implode(' ', $args);
        $this->msg("  Switching to institution configuration $localDir");
        $this->msg("    $cmd");
        $res = system($cmd, $retval);
        if ($retval !== 0) {
            $this->err("Error calling: $cmd");
        }
    }

    /**
     * Send scheduled alerts for a view.
     *
     * @return void
     */
    protected function processViewAlerts()
    {
        $this->msg(
            "  Sending scheduled alerts for view: {$this->localDir} "
            . "(base: {$this->scheduleBaseUrl})"
        );

        $iso8601 = 'Y-m-d\TH:i:s\Z';

        $configLoader = $this->serviceManager->get('VuFind\Config');

        $this->iniReader = new IniReader();
        $this->iniReader->setNestSeparator(chr(0));
        $hmac = $this->serviceManager->get('VuFind\HMAC');

        $backend
            = $this->serviceManager
                ->get('VuFind\Search\BackendManager')->get('Solr');
        $viewManager = $this->serviceManager->get('viewmanager');
        $viewModel = $viewManager->getViewModel();
        $renderer = $viewManager->getRenderer();
        $emailer = $this->serviceManager->get('VuFind\Mailer');
        $translator = $renderer->plugin('translate');
        $urlHelper = $renderer->plugin('url');

        $todayTime = new \DateTime();
        $user = false;
        $institution = false;
        $institutionConfigs = false;

        $scheduled = $this->searchTable->getScheduledSearches(
            $this->scheduleBaseUrl
        );

        $this->msg(sprintf('    Processing %d searches', count($scheduled)));

        foreach ($scheduled as $s) {
            $lastTime = new \DateTime($s->finna_last_executed);
            $schedule = $s->finna_schedule;
            if ($schedule == 1) {
                // Daily
                if ($todayTime->format('Y-m-d') == $lastTime->format('Y-m-d')) {
                    $this->msg(
                        '      Bypassing search ' . $s->id
                        . ': previous execution too recent (daily, '
                        . $lastTime->format($iso8601) . ')'
                    );
                    continue;
                }
            } else if ($schedule == 2) {
                $diff = $todayTime->diff($lastTime);
                if ($diff->days < 6) {
                    $this->msg(
                        '      Bypassing search ' . $s->id
                        . ': previous execution too recent (weekly, '
                        . $lastTime->format($iso8601) . ')'
                    );
                    continue;
                }

            } else {
                $this->err(
                    'Search ' . $s->id . ': unknown schedule: ' . $s->schedule
                );
                continue;
            }

            if ($user === false || $s->user_id != $user->id) {
                if (!$user = $this->userTable->getById($s->user_id)) {
                    $this->err(
                        'Search ' . $s->id . ': user ' . $s->user_id
                        . ' does not exist '
                    );
                    continue;
                }
            }

            if (!$user->email || trim($user->email) == '') {
                $this->err(
                    'User ' . $user->username
                    . ' does not have an email address, bypassing alert ' . $s->id
                );
                continue;
            }

            $scheduleUrl = parse_url($s->finna_schedule_base_url);
            if (!isset($scheduleUrl['host'])) {
                $this->err(
                    'Could not resolve institution for search ' . $s->id
                    . ' with schedule_base_url: ' . var_export($scheduleUrl, true)
                );
                continue;
            }

            // Set email language
            $language = $this->mainConfig->Site->language;
            if ($user->finna_language != ''
                && in_array(
                    $user->finna_language,
                    array_keys($this->mainConfig->Languages->toArray())
                )) {
                $language = $user->finna_language;
            }

            $this->serviceManager->get('VuFind\Translator')
                ->addTranslationFile(
                    'ExtendedIni', null, $this->defaultPath, $language
                )
                ->setLocale($language);

            $limit = 50;

            // Prepare query
            $searchService = $this->serviceManager->get('VuFind\Search');

            $searchObject = $s->getSearchObject();
            $finnaSearchObject = null;
            if ($searchObject instanceof \Finna\Search\Minified) {
                $finnaSearchObject = $searchObject;
                $searchObject = $finnaSearchObject->getParentSO();
            }

            if ($searchObject->cl != 'Solr') {
                $this->err(
                    'Unsupported search class ' . $s->cl . ' for search ' . $s->id
                );
                continue;
            }

            $options = new Options($configLoader);
            $dateConverter = new DateConverter();
            $params = new Params($options, $configLoader, $dateConverter);
            $params->deminify($searchObject);
            if ($finnaSearchObject) {
                $params->deminifyFinnaSearch($finnaSearchObject);
            }

            // Add daterange filter
            $daterangeField = $params::SPATIAL_DATERANGE_FIELD;
            $filters = $searchObject->f;
            if (isset($filters[$daterangeField])) {
                $req = new Parameters();
                if ($finnaSearchObject && isset($finnaSearchObject->f_dty)) {
                    $req->set("{$daterangeField}_type", $finnaSearchObject->f_dty);
                }
                $req->set(
                    'filter',
                    ["$daterangeField:" . $filters[$daterangeField][0]]
                );
                $params->initSpatialDateRangeFilter($req);
            }

            $params->setLimit($limit);
            $params->setSort('first_indexed+desc');

            $query = $params->getQuery();
            $searchParams = $params->getBackendParameters();
            $searchTime = gmdate($iso8601, time());

            try {
                $collection = $searchService
                    ->search('Solr', $query, 0, $limit, $searchParams);

                $resultsTotal = $collection->getTotal();
                if ($resultsTotal < 1) {
                    $this->msg('No results found for search ' . $s->id);
                    continue;
                }

                $records = $collection->getRecords();
            } catch (\VuFindSearch\Backend\Exception\BackendException $e) {
                $this->err(
                    'Error processing search ' . $s->id . ': ' . $e->getMessage()
                );
            }

            $newestRecordDate
                = date($iso8601, strtotime($records[0]->getFirstIndexed()));
            $lastExecutionDate = $lastTime->format($iso8601);
            if ($newestRecordDate < $lastExecutionDate) {
                $this->msg(
                    'No new results for search ' . $s->id
                    . ": $newestRecordDate < $lastExecutionDate"
                );
                continue;
            }

            // Collect records that have been indexed (for the first time)
            // after previous scheduled alert run
            $newRecords = [];
            foreach ($collection->getRecords() as $rec) {
                $recDate = date($iso8601, strtotime($rec->getFirstIndexed()));
                if ($recDate < $lastExecutionDate) {
                    break;
                }
                $newRecords[] = $rec;
            }

            // Prepare email content
            $viewBaseUrl = $searchUrl = $s->finna_schedule_base_url;
            $searchUrl .= $urlHelper->__invoke($options->getSearchAction());

            $urlQueryHelper = new UrlQueryHelper($params);
            $searchUrl .=
                str_replace('&amp;', '&', $urlQueryHelper->getParams());

            $secret = $s->getUnsubscribeSecret($hmac, $user);

            $unsubscribeUrl = $s->finna_schedule_base_url;
            $unsubscribeUrl .=
                $urlHelper->__invoke('myresearch-unsubscribe')
                . "?id={$s->id}&key=$secret";

            $params->setServiceLocator($this->serviceManager);
            $filters = $this->processFilters($params->getFilterList());
            $params = [
                'records' => $newRecords,
                'info' => [
                    'baseUrl' => $viewBaseUrl,
                    'description' => $params->getDisplayQuery(),
                    'recordCount' => count($records),
                    'url' => $searchUrl,
                    'unsubscribeUrl' => $unsubscribeUrl,
                    'filters' => $filters
                 ]
            ];

            $message = $renderer->render('Email/scheduled-alert.phtml', $params);
            $subject
                = $this->mainConfig->Site->title
                . ': ' . $translator->__invoke('Scheduled Alert Results');
            $from = $this->mainConfig->Site->email;
            $to = $user->email;

            try {
                $this->serviceManager->get('VuFind\Mailer')->send(
                    $to, $from, $subject, $message
                );
            } catch (\Exception $e) {
                $this->err(
                    "Failed to send message to {$user->email}: " . $e->getMessage()
                );
                continue;
            }

            if ($s->setLastExecuted($searchTime) === 0) {
                $this->msg('Error updating last_executed date for search ' . $s->id);
            }
        }
    }

    /**
     * Resolve path to the view directory.
     *
     * @param string $institution Institution
     * @param string $view        View
     *
     * @return string|boolean view path or false on error
     */
    protected function resolveViewPath($institution, $view = false)
    {
        if (!$view) {
            $view = $this->defaultPath;
            if (isset($this->datasourceConfig[$institution]['mainView'])) {
                list($institution, $view)
                    = explode(
                        '/',
                        $this->datasourceConfig[$institution]['mainView'], 2
                    );
            }
        }
        $path = "{$this->viewBaseDir}/$institution/$view";

        // Assume that view is functional if index.php exists.
        if (!is_file("$path/public/index.php")) {
            $this->err("Could not resolve view path for $institution/$view");
            return false;
        }

        return "$path/{$this->confDir}";
    }

    /**
     * Utility function for collecting filter
     * information needed in the email.
     *
     * @param array $filters Filter list
     *
     * @return array Processed filter list
     */
    protected function processFilters($filters)
    {
        $result = [];
        $currentField = null;
        $currentFilters = null;
        foreach ($filters as $key => $filterList) {
            foreach ($filterList as $f) {
                $field = $f['field'];
                if (isset($this->facets[$field])) {
                    $field = $this->facets[$field];
                }
                if ($field != $currentField) {
                    if ($currentField) {
                        $result[ucfirst($currentField)] = $currentFilters;
                    }

                    $currentField = $field;
                    $currentFilters = [];
                }
                $currentFilters[] = [
                    'value' => $f['displayText'],
                    'operator' => $f['operator']
                ];
            }
            $result[$currentField] = $currentFilters;
        }
        return $result;
    }

    /**
     * Get script usage information.
     *
     * @return string
     */
    protected function usage()
    {
        $appPath = APPLICATION_PATH;
        return <<<EOT
Usage:
  VUFIND_LOCAL_MODULES='FinnaTheme,FinnaSearch,Finna,FinnaConsole'
  php $appPath/util/scheduled_alerts.php
    [view base directory]
    [VuFind local configuration directory]

            For example:
  VUFIND_LOCAL_MODULES='FinnaTheme,FinnaSearch,Finna,FinnaConsole'
  php $appPath/util/scheduled_alerts.php /tmp/finna /tmp/NDL-VuFind2/local

EOT;
    }
}
