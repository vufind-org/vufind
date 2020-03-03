<?php
/**
 * Console service for reminding users x days before account expiration
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2017.
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
 * @author   Jyrki Messo <jyrki.messo@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Service;

use DateInterval;
use DateTime;
use Zend\Db\Sql\Select;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\RequestInterface as Request;

use Zend\View\Resolver\AggregateResolver;
use Zend\View\Resolver\TemplatePathStack;

/**
 * Console service for reminding users x days before account expiration
 *
 * @category VuFind
 * @package  Service
 * @author   Jyrki Messo <jyrki.messo@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class AccountExpirationReminders extends AbstractService
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Current view local configuration directory.
     *
     * @var string
     */
    protected $baseDir = null;

    /**
     * Base directory for all views.
     *
     * @var string
     */
    protected $viewBaseDir = null;

    /**
     * View renderer
     *
     * @var Zend\View\Renderer\PhpRenderer
     */
    protected $renderer = null;

    /**
     * Table for user accounts
     *
     * @var \VuFind\Db\Table\User
     */
    protected $table = null;

    /**
     * ServiceManager
     *
     * ServiceManager is used for creating VuFind\Mailer objects as needed
     * (mailer is not shared as its connection might time out otherwise).
     *
     * @var ServiceManager
     */
    protected $serviceManager = null;

    /**
     * Translator
     *
     * @var Zend\I18n\Translator\Translator
     */
    protected $translator = null;

    /**
     * UrllHelper
     *
     * @var urlHelper
     */
    protected $urlHelper = null;

    /**
     * Current institution.
     *
     * @var string
     */
    protected $currentInstitution = null;

    /**
     * Current site config
     *
     * @var object
     */
    protected $currentSiteConfig = null;

    /**
     * Current MultiBackend config
     *
     * @var object
     */
    protected $currentMultiBackendConfig = null;

    /**
     * Datasource configuration
     *
     * @var \Zend\Config\Config
     */
    protected $datasourceConfig = null;

    /**
     * ConfigReader
     *
     * @var \VuFind\Config
     */
    protected $configReader = null;

    /**
     * Expiration time in days
     *
     * @var int
     */
    protected $expirationDays;

    /**
     * Days before expiration to send reminders
     *
     * @var int
     */
    protected $remindDaysBefore;

    /**
     * Days between reminders
     *
     * @var int
     */
    protected $reminderFrequency;

    /**
     * Whether to just display a report of messages to be sent.
     *
     * @var bool
     */
    protected $reportOnly;

    /**
     * Constructor
     *
     * @param Finna\Db\Table\User            $table          User table.
     * @param Zend\View\Renderer\PhpRenderer $renderer       View renderer.
     * @param VuFind\Config                  $configReader   Config reader.
     * @param VuFind\Translator              $translator     Translator.
     * @param ServiceManager                 $serviceManager Service manager.
     */
    public function __construct(
        $table, $renderer, $configReader, $translator, $serviceManager
    ) {
        $this->table = $table;
        $this->renderer = $renderer;
        $this->datasourceConfig = $configReader->get('datasources');
        $this->translator = $translator;
        $this->serviceManager = $serviceManager;
        $this->urlHelper = $renderer->plugin('url');
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
        if (!$this->collectScriptArguments($arguments)) {
            $this->msg($this->getUsage());
            return false;
        }

        try {
            $users = $this->getUsersToRemind(
                $this->expirationDays,
                $this->remindDaysBefore,
                $this->reminderFrequency
            );
            $count = 0;

            foreach ($users as $user) {
                $this->msg(
                    "Sending expiration reminder for user {$user->username}"
                    . " (id {$user->id})"
                );
                $this->sendAccountExpirationReminder($user, $this->expirationDays);
                $count++;
            }

            if ($count === 0) {
                $this->msg('No user accounts to remind.');
            } else {
                $this->msg("$count reminders processed.");
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

        return true;
    }

    /**
     * Returns all users that have not been active for given amount of days.
     *
     * @param int $days             Expiration limit (in days) for user accounts
     * @param int $remindDaysBefore How many days before expiration reminder starts
     * @param int $frequency        The freqency in days for reminding the user
     *
     * @return User[] users to remind on expiration
     */
    protected function getUsersToRemind($days, $remindDaysBefore, $frequency)
    {
        if ($remindDaysBefore >= $days) {
            throw new \Exception(
                'remind_days_before must be less than expiration_days'
            );
        }
        if ($frequency > $remindDaysBefore) {
            throw new \Exception(
                'frequency must be less than or equal to remind_days_before'
            );
        }

        $limitDate = date(
            'Y-m-d',
            strtotime(sprintf('-%d days', (int)$days - (int)$remindDaysBefore))
        );

        $initialReminderThreshold = time() + $frequency * 86400;

        $users = $this->table->select(
            function (Select $select) use ($limitDate) {
                $select->where->lessThan('last_login', $limitDate);
                $select->where->notEqualTo(
                    'last_login',
                    '2000-01-01 00:00:00'
                );
            }
        );

        $tableManager
            = $this->serviceManager->get(\VuFind\Db\Table\PluginManager::class);
        $searchTable = $tableManager->get('Search');
        $resourceTable = $tableManager->get('Resource');

        $results = [];
        foreach ($users as $user) {
            $secsSinceLast = time()
                - strtotime($user->finna_last_expiration_reminder);
            if ($secsSinceLast < $frequency * 86400) {
                continue;
            }

            if (!$user->email || trim($user->email) == '') {
                $this->msg(
                    "User {$user->username} (id {$user->id}) does not have an"
                    . ' email address, bypassing expiration reminder'
                );
                continue;
            }

            // Avoid sending a reminder if it comes too late (i.e. no reminders have
            // been sent before and there's less than $frequency days before
            // expiration)
            $expirationDatetime = new DateTime($user->last_login);
            $expirationDatetime->add(new DateInterval('P' . $days . 'D'));

            if (($user->finna_last_expiration_reminder < $user->last_login
                && $expirationDatetime->getTimestamp() < $initialReminderThreshold)
                || $expirationDatetime->getTimestamp() < time()
            ) {
                $expires = $expirationDatetime->format('Y-m-d');
                $this->msg(
                    "User {$user->username} (id {$user->id}) expires already on"
                    . " $expires without previous reminders, bypassing expiration"
                    . ' reminder'
                );
                continue;
            }

            // Check that the user has some saved content so that no reminder is sent
            // if there is none.
            if ($user->finna_due_date_reminder === 0
                && $user->getTags()->count() === 0
                && $searchTable->getSearches('', $user->id)->count() === 0
                && $resourceTable->getFavorites($user->id)->count() === 0
            ) {
                $this->msg(
                    "User {$user->username} (id {$user->id}) has no saved content"
                    . ', bypassing expiration'
                );
                continue;
            }

            $results[] = $user;
        }

        return $results;
    }

    /**
     * Send account expiration reminder for a user.
     *
     * @param \Finna\Db\Table\Row\User $user           User.
     * @param int                      $expirationDays Number of days after
     * the account expires.
     *
     * @return boolean
     */
    protected function sendAccountExpirationReminder($user, $expirationDays)
    {
        if (false !== strpos($user->username, ':')) {
            list($userInstitution, $userName) = explode(':', $user->username, 2);
        } else {
            $userInstitution = 'national';
            $userName = $user->username;
        }

        $dsConfig = isset($this->datasourceConfig[$userInstitution])
            ? $this->datasourceConfig[$userInstitution] : [];
        if (!empty($dsConfig['disableAccountExpirationReminders'])) {
            $this->msg(
                "User {$user->username} (id {$user->id}) institution"
                . " $userInstitution has reminders disabled, bypassing expiration"
                . ' reminder'
            );
            return false;
        }

        if (!$this->currentInstitution
            || $userInstitution != $this->currentInstitution
        ) {
            $templateDirs = [
                "{$this->baseDir}/themes/finna2/templates",
            ];
            if (!$viewPath = $this->resolveViewPath($userInstitution)) {
                $this->err(
                    "Could not resolve view path for user {$user->username}"
                    . " (id {$user->id})",
                    'Could not resolve view path for a user'
                );
                return false;
            } else {
                $templateDirs[] = "$viewPath/themes/custom/templates";
            }

            $this->currentInstitution = $userInstitution;
            $this->currentViewPath = $viewPath;

            $resolver = new AggregateResolver();
            $this->renderer->setResolver($resolver);
            $stack = new TemplatePathStack(['script_paths' => $templateDirs]);
            $resolver->attach($stack);

            $configLoader
                = $this->serviceManager->get(\VuFind\Config\PluginManager::class);
            // Build the config path as a relative path from LOCAL_OVERRIDE_DIR.
            // This is a bit of a hack, but the configuration plugin manager doesn't
            // currently support specifying an absolute path alone.
            $parts = explode('/', LOCAL_OVERRIDE_DIR);
            $configPath = str_repeat('../', count($parts))
                . ".$viewPath/local/config/vufind";
            $this->currentSiteConfig = $configLoader->get(
                'config.ini',
                compact('configPath')
            );
            $this->currentMultiBackendConfig = $configLoader->get(
                'MultiBackend.ini',
                compact('configPath')
            );
        }

        if (isset($this->currentSiteConfig['System']['available'])
            && !$this->currentSiteConfig['System']['available']
        ) {
            $this->msg(
                "User {$user->username} (id {$user->id}) institution"
                . " $userInstitution: site is marked unavailable,"
                . ' bypassing expiration reminder'
            );
            return false;
        }

        if (!empty($this->currentSiteConfig['Authentication']['hideLogin'])) {
            $this->msg(
                "User {$user->username} (id {$user->id}) institution"
                . " $userInstitution: site has login disabled,"
                . ' bypassing expiration reminder'
            );
            return false;
        }

        $authMethod = $this->currentSiteConfig['Authentication']['method'] ?? '';
        if ('ChoiceAuth' === $authMethod) {
            $choiceAuthOptions = explode(
                ',',
                $this->currentSiteConfig['ChoiceAuth']['choice_order'] ?? ''
            );
            $match = false;
            foreach ($choiceAuthOptions as $option) {
                if (strcasecmp($user->auth_method, $option) === 0) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                $this->msg(
                    "User {$user->username} (id {$user->id}) institution"
                    . " $userInstitution: user's authentication method "
                    . " '{$user->auth_method}' is not in available authentication"
                    . ' methods (' . implode(',', $choiceAuthOptions)
                    . '), bypassing expiration reminder'
                );
                return false;
            }
        } elseif (strcasecmp($user->auth_method, $authMethod) !== 0) {
            $this->msg(
                "User {$user->username} (id {$user->id}) institution"
                . " $userInstitution: user's authentication method ,"
                . " '{$user->auth_method}' does not match the current method"
                . " '$authMethod', bypassing expiration reminder"
            );
            return false;
        }

        if (strcasecmp($user->auth_method, 'multiils') === 0) {
            list($target) = explode('.', $userName);
            if (empty($this->currentMultiBackendConfig['Drivers'][$target])) {
                $this->msg(
                    "User {$user->username} (id {$user->id}) institution"
                    . " $userInstitution: unknown MultiILS login target,"
                    . ' bypassing expiration reminder'
                );
                return false;
            }
            $loginTargets = $this->currentMultiBackendConfig['Login']['drivers']
                ? $this->currentMultiBackendConfig['Login']['drivers']->toArray()
                : [];
            if (!in_array($target, (array)$loginTargets)) {
                $this->msg(
                    "User {$user->username} (id {$user->id}) institution"
                    . " $userInstitution: MultiILS target '$target' not available"
                    . ' for login, bypassing expiration reminder'
                );
                return false;
            }
        }

        $expirationDatetime = new DateTime($user->last_login);
        $expirationDatetime->add(new DateInterval('P' . $expirationDays . 'D'));

        $language = isset($this->currentSiteConfig['Site']['language'])
            ? $this->currentSiteConfig['Site']['language'] : 'fi';
        $validLanguages = array_keys((array)$this->currentSiteConfig['Languages']);

        if (!empty($user->last_language)
            && in_array($user->last_language, $validLanguages)
        ) {
            $language = $user->last_language;
        }

        $this->translator
            ->addTranslationFile('ExtendedIni', null, 'default', $language)
            ->setLocale($language);

        if (!$this->currentInstitution || $this->currentInstitution == 'national') {
            $this->currentInstitution = 'www';
        }

        $urlParts = explode('/', $this->currentViewPath);
        $urlView = array_pop($urlParts);
        $urlInstitution = array_pop($urlParts);
        if ('national' === $urlInstitution) {
            $urlInstitution = 'www';
        }
        $serviceAddress = $urlInstitution . '.finna.fi';
        if (!$this->isDefaultViewPath($urlView)) {
            $serviceAddress .= "/$urlView";
        }

        $serviceName = !empty($this->currentSiteConfig['Site']['title'])
            ? $this->currentSiteConfig['Site']['title'] : $serviceAddress;
        $firstName = $user->firstname;
        if (!$firstName) {
            $firstName = $user->lastname;
        }
        if (!$firstName) {
            $firstName = $userName;
        }
        $params = [
            'loginMethod' => strtolower($user->auth_method),
            'username' => $userName,
            'firstname' => $firstName,
            'expirationDate' =>  $expirationDatetime->format('d.m.Y'),
            'serviceName' => $serviceName,
            'serviceAddress' => $serviceAddress
        ];

        $subject = $this->translate(
            'account_expiration_subject',
            [
                '%%expirationDate%%' => $params['expirationDate'],
                '%%serviceName%%' => $serviceName,
                '%%serviceAddress%%' => $serviceAddress
            ]
        );

        $message = $this->renderer->render(
            'Email/account-expiration-reminder.phtml', $params
        );

        try {
            $to = $user->email;
            $from = $this->currentSiteConfig['Site']['email'];

            if ($this->reportOnly) {
                echo <<<EOT
----------
From: $from
To: $to
Subject: $subject

$message
----------

EOT;
            } else {
                $this->serviceManager->build(\VuFind\Mailer\Mailer::class)->send(
                    $to, $from, $subject, $message
                );
                $user->finna_last_expiration_reminder = date('Y-m-d H:i:s');
                $user->save();
            }
        } catch (\Exception $e) {
            $this->err(
                "Failed to send an expiration reminder to user {$user->username} "
                . " (id {$user->id})",
                'Failed to send an expiration reminder to a user'
            );
            $this->err('   ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Collect command line arguments.
     *
     * @param array $arguments Arguments
     *
     * @return void
     */
    protected function collectScriptArguments($arguments)
    {
        // Current view local configuration directory
        $this->baseDir = $arguments[0] ?? false;

        // Current view local basedir
        $this->viewBaseDir = $arguments[1] ?? false;

        // Inactive user account will expire in expirationDays days
        $this->expirationDays = (isset($arguments[2]) && $arguments[2] >= 180)
            ? $arguments[2] : false;

        // Start reminding remindDaysBefore before expiration
        $this->remindDaysBefore = (isset($arguments[3]) && $arguments[3] > 0)
            ? $arguments[3] : false;

        // Remind every reminderFrequency days when reminding period has started
        $this->reminderFrequency = (isset($arguments[4]) && $arguments[4] > 0)
            ? $arguments[4] : false;

        $this->reportOnly = isset($arguments[5]);

        if (!$this->baseDir
            || !$this->viewBaseDir
            || !$this->expirationDays
            || !$this->remindDaysBefore
            || !$this->reminderFrequency
        ) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Get usage information.
     *
     * @return string
     */
    protected function getUsage()
    {
        // @codingStandardsIgnoreStart
        return <<<EOT
Usage:
  php index.php util expiration_reminders <vufind_dir> <view_dir> <expiration_days> <remind_days_before> <frequency> [report]

  Sends a reminder for those users whose account will expire in <remind_days_before> days.
    vufind_dir          VuFind base installation directory
    view_dir            View directory
    expiration_days     After how many inactive days a user account will expire.
                        Values less than 180 are not valid.
    remind_days_before  Begin reminding the user x days before the actual expiration
    frequency           How often (in days) the user will be reminded
    report              If set, only a report of messages to be sent is generated

EOT;
        // @codingStandardsIgnoreEnd
    }
}
