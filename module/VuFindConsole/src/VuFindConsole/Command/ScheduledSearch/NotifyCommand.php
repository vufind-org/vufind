<?php

/**
 * Console command: notify users of scheduled searches.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\ScheduledSearch;

use DateTime;
use Exception;
use Laminas\Config\Config;
use Laminas\View\Renderer\PhpRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Crypt\SecretCalculator;
use VuFind\Db\Entity\SearchEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\SearchServiceInterface;
use VuFind\I18n\Locale\LocaleSettings;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Mailer\Mailer;
use VuFind\Search\Results\PluginManager as ResultsManager;

use function count;
use function in_array;
use function sprintf;

/**
 * Console command: notify users of scheduled searches.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'scheduledsearch/notify',
    description: 'Scheduled Search Notifier'
)]
class NotifyCommand extends Command implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\I18n\Translator\LanguageInitializerTrait;

    /**
     * Output interface
     *
     * @var OutputInterface
     */
    protected $output = null;

    /**
     * Useful date format value
     *
     * @var string
     */
    protected $iso8601 = 'Y-m-d\TH:i:s\Z';

    /**
     * URL helper
     *
     * @var \Laminas\View\Helper\Url
     */
    protected $urlHelper;

    /**
     * Number of results to retrieve when performing searches
     *
     * @var int
     */
    protected $limit = 50;

    /**
     * Constructor
     *
     * @param SecretCalculator       $secretCalculator Secret calculator
     * @param PhpRenderer            $renderer         View renderer
     * @param ResultsManager         $resultsManager   Search results plugin manager
     * @param array                  $scheduleOptions  Configured schedule options
     * @param Config                 $mainConfig       Top-level VuFind configuration
     * @param Mailer                 $mailer           Mail service
     * @param SearchServiceInterface $searchService    Search table
     * @param LocaleSettings         $localeSettings   Locale settings object
     * @param string|null            $name             The name of the command; passing
     * null means it must be set in configure()
     */
    public function __construct(
        protected SecretCalculator $secretCalculator,
        protected PhpRenderer $renderer,
        protected ResultsManager $resultsManager,
        protected array $scheduleOptions,
        protected Config $mainConfig,
        protected Mailer $mailer,
        protected SearchServiceInterface $searchService,
        protected LocaleSettings $localeSettings,
        $name = null
    ) {
        $this->urlHelper = $renderer->plugin('url');
        parent::__construct($name);
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setHelp('Sends scheduled search email notifications.');
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
        if (null !== $this->output) {
            $this->output->writeln($msg);
        }
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
        $this->msg('WARNING: ' . $msg);
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
        $this->msg('ERROR: ' . $msg);
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->processViewAlerts();
        // Disconnect mailer to prevent exceptions from an attempt to gracefully
        // close the connection on teardown
        $this->mailer->resetConnection();
        return 0;
    }

    /**
     * Validate the schedule (return true if we should send a message).
     *
     * @param \DateTime             $todayTime The time the notification job started.
     * @param \DateTime             $lastTime  Last time notification was sent.
     * @param SearchEntityInterface $s         Search row to validate.
     *
     * @return bool
     */
    protected function validateSchedule($todayTime, $lastTime, $s)
    {
        $schedule = $s->getNotificationFrequency();
        if (!isset($this->scheduleOptions[$schedule])) {
            $this->err('Search ' . $s->getId() . ": unknown schedule: $schedule");
            return false;
        }
        $diff = $todayTime->diff($lastTime);
        if ($diff->days < $schedule) {
            $this->msg(
                '  Bypassing search ' . $s->getId() . ': previous execution too recent ('
                . $this->scheduleOptions[$schedule] . ', '
                . $lastTime->format($this->iso8601) . ')'
            );
            return false;
        }
        return true;
    }

    /**
     * Load and validate a user object associated with the search; return null
     * if there is a problem.
     *
     * @param SearchEntityInterface $s Current search row.
     *
     * @return ?UserEntityInterface
     */
    protected function getUserForSearch($s)
    {
        if (!$user = $s->getUser()) {
            $this->warn('Search ' . $s->getId() . ': is missing user data.');
            return null;
        }
        if (!$user->getEmail()) {
            $this->warn(
                'User ' . $user->getUsername() . ' does not have an email address, bypassing alert ' . $s->getId()
            );
            return null;
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
        // preference if set and valid. Default to English if configuration
        // is missing.
        $language = $this->localeSettings->getDefaultLocale();
        $allLanguages = array_keys($this->localeSettings->getEnabledLocales());
        if ($userLang != '' && in_array($userLang, $allLanguages)) {
            $language = $userLang;
        }
        $this->translator->setLocale($language);
        $this->addLanguageToTranslator(
            $this->translator,
            $this->localeSettings,
            $language
        );
    }

    /**
     * Load and validate the results object associated with the search; return false
     * if there is a problem.
     *
     * @param SearchEntityInterface $s Current search row.
     *
     * @return \VuFind\Search\Base\Results|bool
     */
    protected function getObjectForSearch($s)
    {
        $minSO = $s->getSearchObject();
        if (!$minSO) {
            $this->err("Problem getting search object from search {$s->getId()}.");
            return false;
        }
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
            = date($this->iso8601, strtotime($records[0]->getFirstIndexed() ?? ''));
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
     * @param SearchEntityInterface       $s            Search table row
     * @param UserEntityInterface         $user         User owning search row
     * @param \VuFind\Search\Base\Results $searchObject Search results object
     * @param array                       $newRecords   New results in search
     *
     * @return string
     */
    protected function buildEmail($s, $user, $searchObject, $newRecords)
    {
        $viewBaseUrl = $searchUrl = $s->getNotificationBaseUrl();
        $searchUrl .= ($this->urlHelper)(
            $searchObject->getOptions()->getSearchAction()
        ) . $searchObject->getUrlQuery()->getParams(false);
        $secret = $this->secretCalculator->getSearchUnsubscribeSecret($s);
        $unsubscribeUrl = $s->getNotificationBaseUrl()
            . ($this->urlHelper)('myresearch-unsubscribe')
            . "?id={$s->getId()}&key=$secret";
        $userInstitution = $this->mainConfig->Site->institution;
        $params = $searchObject->getParams();
        // Filter function to only pass along selected checkboxes:
        $selectedCheckboxes = function ($data) {
            return $data['selected'] ?? false;
        };
        $viewParams = [
            'user' => $user,
            'records' => $newRecords,
            'info' => [
                'baseUrl' => $viewBaseUrl,
                'description' => $params->getDisplayQuery(),
                'recordCount' => count($newRecords),
                'url' => $searchUrl,
                'unsubscribeUrl' => $unsubscribeUrl,
                'checkboxFilters' => array_filter(
                    $params->getCheckboxFacets(),
                    $selectedCheckboxes
                ),
                'filters' => $params->getFilterList(true),
                'userInstitution' => $userInstitution,
             ],
        ];
        return $this->renderer
            ->render('Email/scheduled-alert.phtml', $viewParams);
    }

    /**
     * Try to send an email message to a user. Return true on success, false on
     * error.
     *
     * @param UserEntityInterface $user    User to email
     * @param string              $message Email message body
     *
     * @return bool
     */
    protected function sendEmail($user, $message)
    {
        $subject = $this->mainConfig->Site->title
            . ': ' . $this->translate('Scheduled Alert Results');
        $from = $this->mainConfig->Site->email;
        $to = $user->getEmail();
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
                "Failed to send message to {$user->getEmail()}: " . $e->getMessage()
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
        $scheduled = $this->searchService->getScheduledSearches();
        $this->msg(sprintf('Processing %d searches', count($scheduled)));
        foreach ($scheduled as $s) {
            $lastTime = $s->getLastNotificationSent();
            if (
                !$this->validateSchedule($todayTime, $lastTime, $s)
                || !($user = $this->getUserForSearch($s))
                || !($searchObject = $this->getObjectForSearch($s))
                || !($newRecords = $this->getNewRecords($searchObject, $lastTime))
            ) {
                continue;
            }
            // Set email language
            $this->setLanguage($user->getLastLanguage());

            // Prepare email content
            $message = $this->buildEmail($s, $user, $searchObject, $newRecords);
            if (!$this->sendEmail($user, $message)) {
                // If email send failed, move on to the next user without updating
                // the database table.
                continue;
            }
            try {
                $s->setLastNotificationSent(new DateTime());
                $this->searchService->persistEntity($s);
            } catch (Exception) {
                $this->err("Error updating last_executed date for search {$s->getId()}");
            }
        }
        $this->msg('Done processing searches');
    }
}
