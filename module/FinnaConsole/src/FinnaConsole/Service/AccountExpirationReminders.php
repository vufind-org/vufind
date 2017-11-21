<?php
/**
 * Console service for reminding users x days before account expiration
 *
 * PHP version 5
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
     * @param array $arguments Command line arguments.
     *
     * @return boolean success
     */
    public function run($arguments)
    {
        if (!$this->collectScriptArguments($arguments)) {
            $this->msg($this->getUsage());
            return false;
        }

        $siteConfig = \VuFind\Config\Locator::getLocalConfigPath("config.ini");
        $this->currentSiteConfig = parse_ini_file($siteConfig, true);

        $users = $this->getUsersToRemind(
            $this->expirationDays, $this->remindDaysBefore, $this->reminderFrequency
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
        $expireDate = date('Y-m-d', strtotime(sprintf('-%d days', (int)$days)));

        $users = $this->table->select(
            function (Select $select) use ($expireDate) {
                $select->where->notLike('username', 'deleted:%');
                $select->where->lessThan('finna_last_login', $expireDate);
                $select->where->notEqualTo(
                    'finna_last_login',
                    '2000-01-01 00:00:00'
                );
            }
        );

        $results = [];
        foreach ($users as $user) {
            $secsSinceLast = time()
                - strtotime($user->finna_last_expiration_reminder);
            if ($secsSinceLast >= $frequency * 86400) {
                $results[] = $user;
            }
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
        if (!$user->email || trim($user->email) == '') {
            $this->msg(
                "User {$user->username} (id {$user->id})"
                . ' does not have an email address, bypassing expiration reminders'
            );
            return false;
        }

        if (false !== strpos($user->username, ':')) {
            list($userInstitution, $userName) = explode(':', $user->username, 2);
        } else {
            $userInstitution = 'national';
            $userName = $user->username;
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
                    . " (id {$user->id})"
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

            $siteConfig = $viewPath . '/local/config/vufind/config.ini';
            $this->currentSiteConfig = parse_ini_file($siteConfig, true);
        }

        $expirationDatetime = new DateTime($user->finna_last_login);
        $expirationDatetime->add(new DateInterval('P' . $expirationDays . 'D'));

        $language = isset($this->currentSiteConfig['Site']['language'])
            ? $this->currentSiteConfig['Site']['language'] : 'fi';
        $validLanguages = array_keys($this->currentSiteConfig['Languages']);

        if (!empty($user->finna_language)
            && in_array($user->finna_language, $validLanguages)
        ) {
            $language = $user->finna_language;
        }

        $this->translator
            ->addTranslationFile('ExtendedIni', null, 'default', $language)
            ->setLocale($language);

        if (!$this->currentInstitution || $this->currentInstitution == 'national') {
            $this->currentInstitution = 'www';
        }

        $serviceAddress = $this->currentInstitution . '.finna.fi';
        $serviceName = !empty($this->currentSiteConfig['Site']['title'])
            ? $this->currentSiteConfig['Site']['title'] : $serviceAddress;
        $params = [
            'loginMethod' => strtolower($user->finna_auth_method),
            'username' => $userName,
            'firstname' => $user->firstname ? $user->firstname : $userName,
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
                $this->serviceManager->get('VuFind\Mailer')->send(
                    $to, $from, $subject, $message
                );
                $user->finna_last_expiration_reminder = date('Y-m-d H:i:s');
                $user->save();
            }
        } catch (\Exception $e) {
            $this->err(
                "Failed to send expiration reminder to user {$user->username} "
                . " (id {$user->id})"
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
        $this->baseDir = isset($arguments[0]) ? $arguments[0] : false;

        // Current view local basedir
        $this->viewBaseDir = isset($arguments[1]) ? $arguments[1] : false;

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
