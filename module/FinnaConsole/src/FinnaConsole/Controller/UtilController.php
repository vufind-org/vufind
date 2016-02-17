<?php
/**
 * CLI Controller Module
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace FinnaConsole\Controller;

use Finna\Db\Row\MetaLibSearch;
use Finna\Db\Row\User;
use Finna\Db\Table\Search;
use Finna\Search\Solr\Options;
use Finna\Search\Solr\Params;
use Finna\Search\UrlQueryHelper;
use VuFind\Date\Converter as DateConverter;
use Zend\Config\Config;
use Zend\Config\Reader\Ini as IniReader;
use Zend\Db\Sql\Select;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Zend\Stdlib\Parameters;

/**
 * This controller handles various command-line tools
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class UtilController extends \VuFindConsole\Controller\UtilController
{
    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger = null;

    /**
     * Error logger
     *
     * @var Logger
     */
    protected $errLogger = null;

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
     * Send scheduled alerts for all views.
     *
     * This script works in three phases:
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
     * @return \Zend\Console\Response
     */
    public function scheduledAlertsAction()
    {
        ini_set('display_errors', true);

        $this->initLogging();

        $argv = $this->consoleOpts->getRemainingArgs();
        $this->collectScriptArguments($argv);

        $this->mainConfig
            = $this->getServiceLocator()->get('VuFind\Config')->get('config');

        $this->datasourceConfig
            = $this->getServiceLocator()->get('VuFind\Config')->get('datasources');

        $facets = $this->getServiceLocator()->get('VuFind\Config')->get('facets');
        $this->facets = $facets->Results->toArray();

        $this->searchTable = $this->getTable('Search');

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

        $this->msg('Scheduled alerts execution completed');

        return $this->getSuccessResponse();
    }

    /**
     * Clears expired MetaLib searches from the database.
     *
     * @return \Zend\Console\Response
     */
    public function clearMetalibSearchAction()
    {
        $this->initLogging();
        $arguments = $this->consoleOpts->getRemainingArgs();

        if (!isset($arguments[0]) || (int) $arguments[0] < 1) {
            echo "Usage:\n  php index.php util clear_metalib_search <m>\n\n"
                . "  Removes all metalib searches from the database that are older\n"
                . "  than <m> minutes.\n";
            return $this->getFailureResponse();
        }

        $count = 0;

        foreach ($this->getExpiredMetalibSearches($arguments[0]) as $row) {
            $row->delete();
            $count++;
        }

        if ($count === 0) {
            $this->msg("There were no expired MetaLib searches to remove");
        } else {
            $this->msg("$count expired MetaLib searches were removed");
        }

        return $this->getSuccessResponse();
    }

    /**
     * Returns expired MetaLib searches
     *
     * @param int $minutes Number of minutes the searches are considered live
     *
     * @return MetaLibSearch[] Traversable list of expired MetaLib searches
     */
    protected function getExpiredMetalibSearches($minutes)
    {
        $expires = date(
            'Y-m-d H:i:s',
            strtotime(sprintf('-%d minutes', (int) $minutes))
        );

        return $this->getTable('metalibSearch')->select(
            function (Select $select) use ($expires) {
                $select->where->lessThan('created', $expires);
            }
        );
    }

    /**
     * Anonymizes all the expired user accounts.
     *
     * @return \Zend\Console\Response
     */
    public function expireUsersAction()
    {
        $this->initLogging();
        $arguments = $this->consoleOpts->getRemainingArgs();

        if (!isset($arguments[0]) || (int) $arguments[0] < 180) {
            echo "Usage:\n  php index.php util expire_users <days>\n\n"
                . "  Anonymizes all user accounts that have not been logged into\n"
                . "  for past <days> days. Values below 180 are not accepted.\n";
            return $this->getFailureResponse();
        }

        $users = $this->getExpiredUsers($arguments[0]);
        $count = 0;

        foreach ($users as $user) {
            $this->msg("Anonymizing the user: " . $user->username);
            $user->anonymizeAccount();
            $count++;
        }

        if ($count === 0) {
            $this->msg('No expired users to anonymize.');
        } else {
            $this->msg("$count expired users anonymized.");
        }

        return $this->getSuccessResponse();
    }

    /**
     * Returns all users that have not been active for given amount of days.
     *
     * @param int $days Preserve users active less than provided amount of days ago
     *
     * @return User[] Expired users
     */
    protected function getExpiredUsers($days)
    {
        $expireDate = date('Y-m-d', strtotime(sprintf('-%d days', (int) $days)));

        return $this->getTable('User')->select(
            function (Select $select) use ($expireDate) {
                $select->where->notLike('username', 'deleted:%');
                $select->where->lessThan('finna_last_login', $expireDate);
                $select->where->notEqualTo(
                    'finna_last_login',
                    '0000-00-00 00:00:00'
                );
            }
        );
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

        $configLoader = $this->getServiceLocator()->get('VuFind\Config');

        $this->iniReader = new IniReader();
        $this->iniReader->setNestSeparator(chr(0));
        $hmac = $this->getServiceLocator()->get('VuFind\HMAC');

        $userTable = $this->getTable('User');

        $backend
            = $this->getServiceLocator()
                ->get('VuFind\Search\BackendManager')->get('Solr');
        $viewManager = $this->getServiceLocator()->get('viewmanager');
        $viewModel = $viewManager->getViewModel();
        $renderer = $viewManager->getRenderer();
        $emailer = $this->getServiceLocator()->get('VuFind\Mailer');
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
                if (!$user = $userTable->getById($s->user_id)) {
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

            $this->getServiceLocator()->get('VuFind\Translator')
                ->addTranslationFile(
                    'ExtendedIni', null, $this->defaultPath, $language
                )
                ->setLocale($language);

            $limit = 50;

            // Prepare query
            $searchService = $this->getServiceLocator()->get('VuFind\Search');

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

            $params->setServiceLocator($this->getServiceLocator());
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
                $this->getServiceLocator()->get('VuFind\Mailer')->send(
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
     * Init logging.
     *
     * @return void
     */
    protected function initLogging()
    {
        $writer = new Stream('php://output');
        $this->logger = new Logger();
        $this->logger->addWriter($writer);

        $writer = new Stream('php://stderr');
        $this->errLogger = new Logger();
        $this->errLogger->addWriter($writer);
    }

    /**
     * Output a message with a timestamp
     *
     * @param string  $msg   Message
     * @param boolean $error Log as en error message?
     *
     * @return void
     */
    protected function msg($msg, $error = false)
    {
        $msg = '[' . getmypid() . "] $msg";
        if ($error) {
            $this->errLogger->err($msg);
            $this->logger->err($msg);
        } else {
            $this->logger->info($msg);
        }
    }

    /**
     * Output an error message with a timestamp
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function err($msg)
    {
        $this->msg($msg, true);
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
