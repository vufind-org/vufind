<?php
/**
 * CLI Controller Module (scheduled search tools)
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFindConsole\Controller;

use Zend\Console\Console;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * CLI Controller Module (scheduled search tools)
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class ScheduledSearchController extends AbstractBase
{
    /**
     * Useful date format value
     *
     * @var string
     */
    protected $iso8601 = 'Y-m-d\TH:i:s\Z';

    /**
     * HMAC generator
     *
     * @var \VuFind\Crypt\HMAC
     */
    protected $hmac;

    /**
     * View renderer
     *
     * @var object
     */
    protected $renderer;

     /**
      * URL helper
      *
      * @var object
      */
    protected $urlHelper;

    /**
     * Search results plugin manager
     *
     * @var \VuFind\Search\Results\PluginManager
     */
    protected $resultsManager;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        parent::__construct($sm);

        $this->hmac = $sm->get(\VuFind\Crypt\HMAC::class);
        $this->renderer = $sm->get('ViewRenderer');
        $this->urlHelper = $this->renderer->plugin('url');
        var_dump(get_class($this->renderer), get_class($this->urlHelper));
        die();
        $this->resultsManager = $sm->get(
            \VuFind\Search\Results\PluginManager::class
        );
    }

    /**
     * Send notifications.
     *
     * @return \Zend\Console\Response
     */
    public function notifyAction()
    {
        $this->processViewAlerts();
        return $this->getSuccessResponse();
    }

    /**
     * Send scheduled alerts for a view.
     *
     * @return void
     */
    protected function processViewAlerts()
    {
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
            if ($user->finna_language != ''
                && in_array(
                    $user->finna_language,
                    array_keys($this->mainConfig->Languages->toArray())
                )
            ) {
                $language = $user->finna_language;
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
                . ': ' . $this->translate('Scheduled Alert Results');
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
}
