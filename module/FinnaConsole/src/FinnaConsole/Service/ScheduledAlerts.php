<?php
/**
 * Send scheduled alerts.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Service;

use Finna\Db\Row\User;
use Finna\Db\Table\Search;
use Zend\Config\Config;
use Zend\Config\Reader\Ini as IniReader;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\Parameters;

use Zend\Stdlib\RequestInterface as Request;

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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class ScheduledAlerts extends AbstractService
{
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
    protected $serviceManager = null;

    /**
     * Constructor
     *
     * @param ServiceManager $sm ServiceManager
     */
    public function __construct(ServiceManager $sm)
    {
        $this->serviceManager = $sm;

        $this->mainConfig
            = $sm->get(\VuFind\Config\PluginManager::class)->get('config');

        $this->datasourceConfig
            = $sm->get(\VuFind\Config\PluginManager::class)->get('datasources');

        $facets = $sm->get(\VuFind\Config\PluginManager::class)->get('facets');
        $this->facets = $facets->Results->toArray();

        $tableManager = $sm->get(\VuFind\Db\Table\PluginManager::class);
        $this->searchTable = $tableManager->get('Search');
        $this->userTable = $tableManager->get('User');
    }

    /**
     * Run service.
     *
     * @param array   $arguments Command line arguments.
     * @param Request $request   Full request
     *
     * @return boolean success
     */
    public function run($arguments, Request $request)
    {
        $this->collectScriptArguments($arguments);

        try {
            if (!$this->localDir = getenv('VUFIND_LOCAL_DIR')) {
                $this->msg('Switching to VuFind configuration');
                $this->switchInstitution($this->baseDir);
            } elseif (!$this->scheduleBaseUrl) {
                $this->processAlerts();
                exit(0);
            } else {
                $this->processViewAlerts();
                exit(0);
            }
        } catch (\Exception $e) {
            $this->err(
                "Exception: " . $e->getMessage(),
                'Exception occurred'
            );
            while ($e = $e->getPrevious()) {
                $this->err("  Previous exception: " . $e->getMessage());
            }
            exit(1);
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
        $this->viewBaseDir = $argv[0] ?? '..';
        // Current view local configuration directory
        $this->baseDir = $argv[1] ?? false;
        // Schedule base url for alerts to send
        $this->scheduleBaseUrl = $argv[2] ?? false;

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
            $host = explode('.', $parts['host']);
            $hostCnt = count($host);

            if ($hostCnt < 2 || $hostCnt > 4) {
                $this->err("Invalid base URL $url", '=');
                continue;
            }

            $institution = $host[0];

            if ($hostCnt == 4 && $institution == 'www') {
                // www.[organisation].finna.fi
                $institution = $host[1];
            } elseif ($hostCnt == 2 || ($hostCnt == 3 && $institution == 'www')) {
                // finna.fi and www.finna.fi
                $institution = 'national';
            }
            $view = isset($parts['path']) ? substr($parts['path'], 1) : false;

            if (!$path = $this->resolveViewPath($institution, $view)) {
                $this->err("Skipping alerts for view $url", '=');
                continue;
            }
            $this->switchInstitution("$path/{$this->confDir}", $url);
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
        $script = "$appDir/public/index.php";

        $args = ['util', 'scheduled_alerts', $this->viewBaseDir, $localDir];
        if ($scheduleBaseUrl) {
            $args[] = "'$scheduleBaseUrl'";
        }

        $cmd = "VUFIND_LOCAL_DIR='$localDir'";
        $cmd .= " php -d short_open_tag=1 -d display_errors=1 '$script' "
            . implode(' ', $args);
        $this->msg("  Switching to institution configuration $localDir");
        $this->msg("    $cmd");
        $res = system($cmd, $retval);
        if ($retval !== 0) {
            $this->err("Error calling: $cmd", '=');
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

        $this->iniReader = new IniReader();
        $this->iniReader->setNestSeparator(chr(0));
        $hmac = $this->serviceManager->get(\VuFind\Crypt\HMAC::class);

        $renderer = $this->serviceManager->get('ViewRenderer');
        $translator = $renderer->plugin('translate');
        $urlHelper = $renderer->plugin('url');
        $resultsManager = $this->serviceManager->get(
            'VuFind\SearchResultsPluginManager'
        );

        $todayTime = new \DateTime();
        $user = false;

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
            } elseif ($schedule == 2) {
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
                    'Search ' . $s->id . ': unknown schedule: ' . $s->schedule, '='
                );
                continue;
            }

            if ($user === false || $s->user_id != $user->id) {
                if (!$user = $this->userTable->getById($s->user_id)) {
                    $this->warn(
                        'Search ' . $s->id . ': user ' . $s->user_id
                        . ' does not exist '
                    );
                    continue;
                }
            }

            if (!$user->email || trim($user->email) == '') {
                $this->warn(
                    'User ' . $user->username
                    . ' does not have an email address, bypassing alert ' . $s->id
                );
                continue;
            }

            $scheduleUrl = parse_url($s->finna_schedule_base_url);
            if (!isset($scheduleUrl['host'])) {
                $this->err(
                    'Could not resolve institution for search ' . $s->id
                    . ' with schedule_base_url: ' . var_export($scheduleUrl, true),
                    '='
                );
                continue;
            }

            // Set email language
            $language = $this->mainConfig->Site->language;
            if (!empty($user->last_language)
                && in_array(
                    $user->last_language,
                    array_keys($this->mainConfig->Languages->toArray())
                )
            ) {
                $language = $user->last_language;
            }

            $this->serviceManager->get(\Zend\Mvc\I18n\Translator::class)
                ->addTranslationFile(
                    'ExtendedIni', null, $this::DEFAULT_PATH, $language
                )
                ->setLocale($language);

            $limit = 50;

            // Prepare query
            $minSO = $s->getSearchObject();

            $searchObject = $minSO->deminify($resultsManager);

            if ($searchObject->getBackendId() !== 'Solr') {
                $this->err(
                    'Unsupported search backend ' . $searchObject->getBackendId()
                        . ' for search ' . $searchObject->getSearchId(),
                    '='
                );
                continue;
            }

            $params = $searchObject->getParams();
            $params->setLimit($limit);
            $params->setSort('first_indexed desc', true);

            $searchTime = date('Y-m-d H:i:s');
            $searchId = $searchObject->getSearchId();

            try {
                $records = $searchObject->getResults();
            } catch (\Exception $e) {
                $this->err(
                    "Error processing search $searchId: " . $e->getMessage(),
                    '='
                );
            }
            if (empty($records)) {
                $this->msg(
                    "      No results found for search $searchId"
                );
                continue;
            }

            $newestRecordDate
                = date($iso8601, strtotime($records[0]->getFirstIndexed()));
            $lastExecutionDate = $lastTime->format($iso8601);
            if ($newestRecordDate < $lastExecutionDate) {
                $this->msg(
                    "      No new results for search {$s->id} ($searchId): "
                    . "$newestRecordDate < $lastExecutionDate"
                );
                continue;
            }

            $this->msg(
                "      New results for search {$s->id} ($searchId): "
                . "$newestRecordDate >= $lastExecutionDate"
            );

            // Collect records that have been indexed (for the first time)
            // after previous scheduled alert run
            $newRecords = [];
            foreach ($records as $record) {
                $recDate = date($iso8601, strtotime($record->getFirstIndexed()));
                if ($recDate < $lastExecutionDate) {
                    break;
                }
                $newRecords[] = $record;
            }

            // Prepare email content
            $viewBaseUrl = $searchUrl = $s->finna_schedule_base_url;
            $searchUrl .= $urlHelper($searchObject->getOptions()->getSearchAction())
                . $searchObject->getUrlQuery()->getParams(false);

            $secret = $s->getUnsubscribeSecret($hmac, $user);

            $unsubscribeUrl = $s->finna_schedule_base_url;
            $unsubscribeUrl .=
                $urlHelper->__invoke('myresearch-unsubscribe')
                . "?id={$s->id}&key=$secret";
            $userInstitution = $this->mainConfig->Site->institution;
            $filters = $this->processFilters($params->getFilterList());
            $params = [
                'records' => $newRecords,
                'info' => [
                    'baseUrl' => $viewBaseUrl,
                    'description' => $params->getDisplayQuery(),
                    'recordCount' => count($newRecords),
                    'url' => $searchUrl,
                    'unsubscribeUrl' => $unsubscribeUrl,
                    'filters' => $filters,
                    'userInstitution' => $userInstitution
                 ]
            ];

            $message = $renderer->render('Email/scheduled-alert.phtml', $params);
            $subject
                = $this->mainConfig->Site->title
                . ': ' . $translator->__invoke('Scheduled Alert Results');
            $from = $this->mainConfig->Site->email;
            $to = $user->email;

            try {
                $this->serviceManager->build(\VuFind\Mailer\Mailer::class)->send(
                    $to, $from, $subject, $message
                );
            } catch (\Exception $e) {
                $this->err(
                    "Failed to send message to {$user->email}: " . $e->getMessage(),
                    'Failed to send a message to a user'
                );
                continue;
            }

            if ($s->setLastExecuted($searchTime) === 0) {
                $this->err(
                    "Error updating last_executed date for search $searchId", '='
                );
            }
        }
        $this->msg('    Done processing searches');
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
  php $appPath/util/scheduled_alerts.php <view_base> <local_conf>

  Sends scheduled alerts.
    view_base  View base directory
    local_conf VuFind local configuration directory

For example:
  php $appPath/util/scheduled_alerts.php /tmp/finna /tmp/NDL-VuFind2/local

EOT;
    }
}
