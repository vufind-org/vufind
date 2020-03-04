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
    implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\I18n\Translator\LanguageInitializerTrait;

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
     * @var \Zend\View\Renderer\PhpRenderer
     */
    protected $renderer;

    /**
     * URL helper
     *
     * @var \Zend\View\Helper\Url
     */
    protected $urlHelper;

    /**
     * Search results plugin manager
     *
     * @var \VuFind\Search\Results\PluginManager
     */
    protected $resultsManager;

    /**
     * Configured schedule options
     *
     * @var array
     */
    protected $scheduleOptions;

    /**
     * Top-level VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $mainConfig;

    /**
     * Number of results to retrieve when performing searches
     *
     * @var int
     */
    protected $limit = 50;

    /**
     * Mail service
     *
     * @var \VuFind\Mailer\Mailer
     */
    protected $mailer;

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
        $this->resultsManager = $sm->get(
            \VuFind\Search\Results\PluginManager::class
        );
        $this->scheduleOptions = $sm
            ->get(\VuFind\Search\History::class)
            ->getScheduleOptions();
        $this->mainConfig = $sm->get(\VuFind\Config\PluginManager::class)
            ->get('config');
        $this->mailer = $sm->get(\VuFind\Mailer\Mailer::class);
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
     * Display a message.
     *
     * @param string $msg Message to display
     *
     * @return void
     */
    protected function msg($msg)
    {
        Console::writeLine($msg);
    }

    /**
     * Display a warning.
     *
     * @param string $msg Message to display
     *
     * @return void
     */
    protected function warn($msg)
    {
        Console::writeLine('WARNING: ' . $msg);
    }

    /**
     * Display an error.
     *
     * @param string $msg Message to display
     *
     * @return void
     */
    protected function err($msg)
    {
        Console::writeLine('ERROR: ' . $msg);
    }

    /**
     * Validate the schedule (return true if we should send a message).
     *
     * @param \DateTime             $todayTime The time the notification job started.
     * @param \DateTime             $lastTime  Last time notification was sent.
     * @param \VuFind\Db\Row\Search $s         Search row to validate.
     *
     * @return bool
     */
    protected function validateSchedule($todayTime, $lastTime, $s)
    {
        $schedule = $s->notification_frequency;
        if (!isset($this->scheduleOptions[$schedule])) {
            $this->err('Search ' . $s->id . ": unknown schedule: $schedule");
            return false;
        }
        $diff = $todayTime->diff($lastTime);
        if ($diff->days < $schedule) {
            $this->msg(
                '  Bypassing search ' . $s->id
                . ': previous execution too recent ('
                . $this->scheduleOptions[$schedule] . ', '
                . $lastTime->format($this->iso8601) . ')'
            );
            return false;
        }
        return true;
    }

    /**
     * Load and validate a user object associated with the search; return false
     * if there is a problem.
     *
     * @param \VuFind\Db\Row\Search $s Current search row.
     *
     * @return \VuFind\Db\Row\User|bool
     */
    protected function getUserForSearch($s)
    {
        // Use a static variable to hold the last accessed user (to spare duplicate
        // database lookups, since we're loading rows in user order).
        static $user = false;

        if ($user === false || $s->user_id != $user->id) {
            if (!$user = $this->getTable('user')->getById($s->user_id)) {
                $this->warn(
                    'Search ' . $s->id . ': user ' . $s->user_id
                    . ' does not exist '
                );
                return false;
            }
        }
        if (!$user->email || trim($user->email) == '') {
            $this->warn(
                'User ' . $user->username
                . ' does not have an email address, bypassing alert ' . $s->id
            );
            return false;
        }
        return $user;
    }

    /**
     * Set up the translator language.
     *
     * @param string $userLang User language preference from database (may be empty).
     *
     * @return void
     */
    protected function setLanguage($userLang)
    {
        // Start with default language setting; override with user language
        // preference if set and valid.
        $language = $this->mainConfig->Site->language;
        if ($userLang != ''
            && in_array(
                $userLang,
                array_keys($this->mainConfig->Languages->toArray())
            )
        ) {
            $language = $userLang;
        }
        $this->translator->setLocale($language);
        $this->addLanguageToTranslator($this->translator, $language);
    }

    /**
     * Load and validate the results object associated with the search; return false
     * if there is a problem.
     *
     * @param \VuFind\Db\Row\Search $s Current search row.
     *
     * @return \VuFind\Db\Row\User|bool
     */
    protected function getObjectForSearch($s)
    {
        $minSO = $s->getSearchObject();
        $searchObject = $minSO->deminify($this->resultsManager);
        if (!$searchObject->getOptions()->supportsScheduledSearch()) {
            $this->err(
                'Unsupported search backend ' . $searchObject->getBackendId()
                . ' for search ' . $searchObject->getSearchId()
            );
            return false;
        }
        return $searchObject;
    }

    /**
     * Given a search results object, fetch records that have changed since the last
     * search. Return false on error.
     *
     * @param \VuFind\Search\Base\Results $searchObject Search results object
     * @param \DateTime                   $lastTime     Last notification time
     *
     * @return array|bool
     */
    protected function getNewRecords($searchObject, $lastTime)
    {
        // Prepare query
        $params = $searchObject->getParams();
        $params->setLimit($this->limit);
        $params->setSort('first_indexed desc', true);
        $searchId = $searchObject->getSearchId();
        try {
            $records = $searchObject->getResults();
        } catch (\Exception $e) {
            $this->err("Error processing search $searchId: " . $e->getMessage());
            return false;
        }
        if (empty($records)) {
            $this->msg(
                "  No results found for search $searchId"
            );
            return false;
        }
        $newestRecordDate
            = date($this->iso8601, strtotime($records[0]->getFirstIndexed()));
        $lastExecutionDate = $lastTime->format($this->iso8601);
        if ($newestRecordDate < $lastExecutionDate) {
            $this->msg(
                "  No new results for search ($searchId): "
                . "$newestRecordDate < $lastExecutionDate"
            );
            return false;
        }
        $this->msg(
            "  New results for search ($searchId): "
            . "$newestRecordDate >= $lastExecutionDate"
        );
        // Collect records that have been indexed (for the first time)
        // after previous scheduled alert run
        $newRecords = [];
        foreach ($records as $record) {
            $recDate = date($this->iso8601, strtotime($record->getFirstIndexed()));
            if ($recDate < $lastExecutionDate) {
                break;
            }
            $newRecords[] = $record;
        }
        return $newRecords;
    }

    /**
     * Build the email message.
     *
     * @param \VuFind\Db\Row\Search       $s            Search table row
     * @param \VuFind\Db\Row\User         $user         User owning search row
     * @param \VuFind\Search\Base\Results $searchObject Search results object
     * @param array                       $newRecords   New results in search
     *
     * @return string
     */
    protected function buildEmail($s, $user, $searchObject, $newRecords)
    {
        $viewBaseUrl = $searchUrl = $s->notification_base_url;
        $searchUrl .= $this->urlHelper->__invoke(
            $searchObject->getOptions()->getSearchAction()
        ) . $searchObject->getUrlQuery()->getParams(false);
        $secret = $s->getUnsubscribeSecret($this->hmac, $user);
        $unsubscribeUrl = $s->notification_base_url
            . $this->urlHelper->__invoke('myresearch-unsubscribe')
            . "?id={$s->id}&key=$secret";
        $userInstitution = $this->mainConfig->Site->institution;
        $params = $searchObject->getParams();
        // Filter function to only pass along selected checkboxes:
        $selectedCheckboxes = function ($data) {
            return $data['selected'] ?? false;
        };
        $viewParams = [
            'records' => $newRecords,
            'info' => [
                'baseUrl' => $viewBaseUrl,
                'description' => $params->getDisplayQuery(),
                'recordCount' => count($newRecords),
                'url' => $searchUrl,
                'unsubscribeUrl' => $unsubscribeUrl,
                'checkboxFilters' => array_filter(
                    $params->getCheckboxFacets(), $selectedCheckboxes
                ),
                'filters' => $params->getFilterList(true),
                'userInstitution' => $userInstitution
             ]
        ];
        return $this->renderer
            ->render('Email/scheduled-alert.phtml', $viewParams);
    }

    /**
     * Try to send an email message to a user. Return true on success, false on
     * error.
     *
     * @param \VuFind\Db\Row\User $user    User to email
     * @param string              $message Email message body
     *
     * @return bool
     */
    protected function sendEmail($user, $message)
    {
        $subject = $this->mainConfig->Site->title
            . ': ' . $this->translate('Scheduled Alert Results');
        $from = $this->mainConfig->Site->email;
        $to = $user->email;
        try {
            $this->mailer->send($to, $from, $subject, $message);
            return true;
        } catch (\Exception $e) {
            $this->msg(
                'Initial email send failed; resetting connection and retrying...'
            );
        }
        // If we got this far, the first attempt threw an exception; let's reset
        // the connection, then try again....
        $this->mailer->resetConnection();
        try {
            $this->mailer->send($to, $from, $subject, $message);
        } catch (\Exception $e) {
            $this->err(
                "Failed to send message to {$user->email}: " . $e->getMessage()
            );
            return false;
        }
        // If we got here, the retry was a success!
        return true;
    }

    /**
     * Send scheduled alerts for a view.
     *
     * @return void
     */
    protected function processViewAlerts()
    {
        $todayTime = new \DateTime();
        $scheduled = $this->getTable('search')->getScheduledSearches();
        $this->msg(sprintf('Processing %d searches', count($scheduled)));
        foreach ($scheduled as $s) {
            $lastTime = new \DateTime($s->last_notification_sent);
            if (!$this->validateSchedule($todayTime, $lastTime, $s)
                || !($user = $this->getUserForSearch($s))
                || !($searchObject = $this->getObjectForSearch($s))
                || !($newRecords = $this->getNewRecords($searchObject, $lastTime))
            ) {
                continue;
            }
            // Set email language
            $this->setLanguage($user->last_language);

            // Prepare email content
            $message = $this->buildEmail($s, $user, $searchObject, $newRecords);
            if (!$this->sendEmail($user, $message)) {
                // If email send failed, move on to the next user without updating
                // the database table.
                continue;
            }
            $searchTime = date('Y-m-d H:i:s');
            if ($s->setLastExecuted($searchTime) === 0) {
                $this->err("Error updating last_executed date for search $searchId");
            }
        }
        $this->msg('Done processing searches');
    }
}
