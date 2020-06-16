<?php
/**
 * Console service for sending due date reminders.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2013-2020.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Command\Util;

use Laminas\Mvc\I18n\Translator;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\View\Resolver\AggregateResolver;
use Laminas\View\Resolver\TemplatePathStack;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console service for sending due date reminders.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class DueDateReminders extends AbstractUtilCommand
{
    /**
     * The name of the command (the part after "public/index.php")
     *
     * @var string
     */
    protected static $defaultName = 'util/due_date_reminders';

    /**
     * Date format for due dates in database.
     */
    const DUE_DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * ILS connection.
     *
     * @var \Finna\ILS\Connection
     */
    protected $catalog;

    /**
     * Main configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $mainConfig;

    /**
     * Datasource configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $datasourceConfig;

    /**
     * Due date reminders table
     *
     * @var \Finna\Db\Table\DueDateReminder
     */
    protected $dueDateReminderTable;

    /**
     * User account table
     *
     * @var \Finna\Db\Table\User
     */
    protected $userTable;

    /**
     * Record loader
     *
     * @var \VuFind\Record\Loader
     */
    protected $recordLoader;

    /**
     * URL Helper
     *
     * @var \VuFind\View\Helper\Root\Url
     */
    protected $urlHelper;

    /**
     * HMAC
     *
     * @var \VuFind\Crypt\HMAC
     */
    protected $hmac;

    /**
     * View renderer
     *
     * @var PhpRenderer
     */
    protected $viewRenderer;

    /**
     * Mailer
     *
     * @var \VuFind\Mailer\Mailer
     */
    protected $mailer;

    /**
     * Translator
     *
     * We don't use the interface and trait since the trait only defines an interface
     * and we need the actual class for addTranslationFile().
     *
     * @var Translator
     */
    protected $translator;

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
     * Current institution.
     *
     * @var string
     */
    protected $currentInstitution = null;

    /**
     * Current institution configuration.
     *
     * @var array
     */
    protected $currentSiteConfig = null;

    /**
     * Current view path.
     *
     * @var string
     */
    protected $currentViewPath = null;

    /**
     * Constructor
     *
     * @param \Finna\Db\Table\User            $userTable            User table
     * @param \Finna\Db\Table\DueDateReminder $dueDateReminderTable Due date
     * reminder table
     * @param \VuFind\ILS\Connection          $catalog              ILS connection
     * @param \Laminas\Config\Config          $mainConfig           Main config
     * @param \Laminas\Config\Config          $dsConfig             Data source
     * config
     * @param PhpRenderer                     $renderer             View renderer
     * @param \VuFind\Record\Loader           $recordLoader         Record loader
     * @param \VuFind\Crypt\HMAC              $hmac                 HMAC
     * @param \VuFind\Mailer\Mailer           $mailer               Mailer
     * @param Translator                      $translator           Translator
     */
    public function __construct(
        \Finna\Db\Table\User $userTable,
        \Finna\Db\Table\DueDateReminder $dueDateReminderTable,
        \VuFind\ILS\Connection $catalog,
        \Laminas\Config\Config $mainConfig,
        \Laminas\Config\Config $dsConfig,
        PhpRenderer $renderer,
        \VuFind\Record\Loader $recordLoader,
        \VuFind\Crypt\HMAC $hmac,
        \VuFind\Mailer\Mailer $mailer,
        Translator $translator
    ) {
        $this->userTable = $userTable;
        $this->dueDateReminderTable = $dueDateReminderTable;
        $this->catalog = $catalog;
        $this->mainConfig = $mainConfig;

        if (isset($this->mainConfig->Catalog->loadNoILSOnFailure)
            && $this->mainConfig->Catalog->loadNoILSOnFailure
        ) {
            throw new \Exception('Catalog/loadNoILSOnFailure must not be enabled');
        }

        $this->datasourceConfig = $dsConfig;
        $this->viewRenderer = $renderer;
        $this->urlHelper = $renderer->plugin('url');
        $this->recordLoader = $recordLoader;
        $this->hmac = $hmac;
        $this->mailer = $mailer;
        $this->translator = $translator;
        parent::__construct();
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Send due date reminders.')
            ->addArgument(
                'vufind_dir',
                InputArgument::REQUIRED,
                'VuFind base installation directory'
            )
            ->addArgument('view_dir', InputArgument::REQUIRED, 'View directory');
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Current view local configuration directory
        $this->baseDir = $input->getArgument('vufind_dir');

        // Current view local basedir
        $this->viewBaseDir = $input->getArgument('view_dir');

        $this->msg('Sending due date reminders');
        try {
            $users = $this->userTable->getUsersWithDueDateReminders();
            $this->msg('Processing ' . count($users) . ' users');

            foreach ($users as $user) {
                $results = $this->getReminders($user);
                $errors = $results['errors'];
                $remindLoans = $results['remindLoans'];
                $remindCnt = count($remindLoans);
                $errorCnt = count($errors);
                if ($remindCnt || $errorCnt) {
                    $this->msg(
                        "$remindCnt reminders and $errorCnt errors to send for user"
                        . " {$user->username} (id {$user->id})"
                    );
                    $this->sendReminder($user, $remindLoans, $errors);
                } else {
                    $this->msg(
                        "No loans to remind for user {$user->username}"
                        . " (id {$user->id})"
                    );
                }
            }
            $this->msg('Completed processing users');
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
     * Get reminders for a user.
     *
     * @param \Finna\Db\Table\Row\User $user User.
     *
     * @return array Array of loans to be reminded and possible login errors.
     */
    protected function getReminders($user)
    {
        if (!$user->email || trim($user->email) == '') {
            $this->warn(
                "User {$user->username} (id {$user->id})"
                . ' does not have an email address, bypassing due date reminders'
            );
            return ['remindLoans' => [], 'errors' => []];
        }

        $remindLoans = [];
        $errors = [];
        foreach ($user->getLibraryCards() as $card) {
            if (!$card->id || $card->finna_due_date_reminder == 0) {
                continue;
            }
            $ddrConfig = $this->catalog->getConfig(
                'dueDateReminder',
                ['cat_username' => $card->cat_username]
            );
            // Assume ddrConfig['enabled'] may contain also something else than a
            // boolean..
            if (isset($ddrConfig['enabled']) && $ddrConfig['enabled'] !== true) {
                // Due date reminders disabled for the source
                $this->warn(
                    "Due date reminders disabled for source, user {$user->username}"
                    . " (id {$user->id}), card {$card->cat_username}"
                    . " (id {$card->id})"
                );
                continue;
            }

            $patron = null;
            // Retrieve a complete UserCard object
            $card = $user->getLibraryCard($card['id']);
            try {
                $patron = $this->catalog->patronLogin(
                    $card->cat_username,
                    $card->cat_password
                );
            } catch (\Exception $e) {
                $this->err(
                    "Catalog login error for user {$user->username}"
                        . " (id {$user->id}), card {$card->cat_username}"
                        . " (id {$card->id}): " . $e->getMessage(),
                    'Catalog login error for a user'
                );
                continue;
            }

            if (null === $patron) {
                $this->warn(
                    "Catalog login failed for user {$user->username}"
                    . " (id {$user->id}), card {$card->cat_username}"
                    . " (id {$card->id}) -- disabling due date reminders for the"
                    . ' card'
                );
                $errors[] = ['card' => $card['cat_username']];
                // Disable due date reminders for this card
                if ($user->cat_username == $card->cat_username) {
                    // Card is the active one, update via user
                    $user->setFinnaDueDateReminder(0);
                } else {
                    // Update just the card
                    $card->finna_due_date_reminder = 0;
                    $card->save();
                }
                continue;
            }

            $todayTime = new \DateTime();
            try {
                $loans = $this->catalog->getMyTransactions($patron);
                // Support also older driver return value:
                if (!isset($loans['count'])) {
                    $loans = [
                        'count' => count($loans),
                        'records' => $loans
                    ];
                }
            } catch (\Exception $e) {
                $this->err(
                    "Exception trying to get loans for user {$user->username}"
                        . " (id {$user->id}), card {$card->cat_username}"
                        . " (id {$card->id}): "
                        . $e->getMessage(),
                    'Exception trying to get loans for a user'
                );
                continue;
            }
            foreach ($loans['records'] as $loan) {
                $dueDate = new \DateTime($loan['duedate']);
                $dayDiff = $dueDate->diff($todayTime)->days;
                if ($todayTime >= $dueDate
                    || $dayDiff <= $card->finna_due_date_reminder
                ) {
                    $params = [
                       'user_id' => $user->id,
                       'loan_id' => $loan['item_id'],
                       'due_date'
                          => $dueDate->format($this::DUE_DATE_FORMAT)
                    ];

                    $reminder = $this->dueDateReminderTable->select($params);
                    if (count($reminder)) {
                        // Reminder already sent
                        continue;
                    }

                    // Store also title for display in email
                    $title = $loan['title']
                        ?? null;

                    $record = null;
                    if (isset($loan['id'])) {
                        $record = $this->recordLoader->load(
                            $loan['id'],
                            'Solr',
                            true
                        );
                    }

                    $dateFormat = isset(
                        $this->currentSiteConfig['Site']['displayDateFormat']
                    )
                        ? $this->currentSiteConfig['Site']['displayDateFormat']
                        : $this->mainConfig->Site->displayDateFormat;

                    $remindLoans[] = [
                        'loanId' => $loan['item_id'],
                        'dueDate' => $loan['duedate'],
                        'dueDateFormatted' => $dueDate->format($dateFormat),
                        'title' => $title,
                        'record' => $record
                    ];
                }
            }
        }
        return ['remindLoans' => $remindLoans, 'errors' => $errors];
    }

    /**
     * Send reminders for a user.
     *
     * @param \Finna\Db\Table\Row\User $user        User.
     * @param array                    $remindLoans Loans to be reminded.
     * @param array                    $errors      Errors in due date checking.
     *
     * @return boolean success.
     */
    protected function sendReminder($user, $remindLoans, $errors)
    {
        if (!$user->email || trim($user->email) == '') {
            $this->msg(
                "User {$user->username} (id {$user->id})"
                . ' does not have an email address, bypassing due date reminders'
            );
            return false;
        }

        list($userInstitution, ) = explode(':', $user['username'], 2);

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
            $stack = new TemplatePathStack(['script_paths' => $templateDirs]);
            $resolver->attach($stack);
            $this->viewRenderer->setResolver($resolver);

            $siteConfig = $viewPath . '/local/config/vufind/config.ini';
            $this->currentSiteConfig = parse_ini_file($siteConfig, true);
        }

        $language = isset($this->currentSiteConfig['Site']['language'])
            ? $this->currentSiteConfig['Site']['language'] : 'fi';
        $validLanguages = array_keys($this->currentSiteConfig['Languages']);
        if (!empty($user->last_language)
            && in_array($user->last_language, $validLanguages)
        ) {
            $language = $user->last_language;
        }
        $this->translator
            ->addTranslationFile('ExtendedIni', null, 'default', $language)
            ->setLocale($language);

        $key = $this->dueDateReminderTable->getUnsubscribeSecret(
            $this->hmac,
            $user,
            $user->id
        );
        $urlParams = [
            'id' => $user->id,
            'type' => 'reminder',
            'key' => $key
        ];
        $unsubscribeUrl
            = $this->urlHelper->__invoke('myresearch-unsubscribe')
            . '?' . http_build_query($urlParams);

        $urlParts = explode('/', $this->currentViewPath);
        $urlView = array_pop($urlParts);
        $urlInstitution = array_pop($urlParts);
        if ('national' === $urlInstitution) {
            $urlInstitution = 'www';
        }

        $baseUrl = 'https://' . $urlInstitution . '.finna.fi';
        if (!$this->isDefaultViewPath($urlView)) {
            $baseUrl .= "/$urlView";
        }
        $serviceName = $urlInstitution . '.finna.fi';
        $lastLogin = new \DateTime($user->last_login);
        $loginMethod = strtolower($user->auth_method);
        $dateFormat = isset($this->currentSiteConfig['Site']['displayDateFormat'])
            ? $this->currentSiteConfig['Site']['displayDateFormat']
            : $this->mainConfig->Site->displayDateFormat;

        $params = [
            'loans' => $remindLoans,
            'unsubscribeUrl' => $baseUrl . $unsubscribeUrl,
            'baseUrl' => $baseUrl,
            'lastLogin' => $lastLogin->format($dateFormat),
            'loginMethod' => $loginMethod,
            'serviceName' => $serviceName,
            'userInstitution' => $userInstitution
        ];

        $urlHelper = $this->urlHelper;
        if (!empty($errors)) {
            $subject = $this->translator->translate('due_date_email_error');
            $params['url'] = $baseUrl
                . $urlHelper('librarycards-home');
            $params['errors'] = $errors;
        } else {
            $subject = $this->translator->translate('due_date_email_subject');
            $params['url'] = $baseUrl
                . $urlHelper('myresearch-checkedout');
        }
        $message = $this->viewRenderer
            ->render('Email/due-date-reminder.phtml', $params);
        $to = $user->email;
        $from = $this->currentSiteConfig['Site']['email'];
        try {
            try {
                $this->mailer->send($to, $from, $subject, $message);
            } catch (\Exception $e) {
                $this->mailer->resetConnection();
                $this->mailer->send($to, $from, $subject, $message);
            }
        } catch (\Exception $e) {
            $this->err(
                "Failed to send due date reminders to user {$user->username}"
                    . " (id {$user->id})",
                'Failed to send due date reminders to a user'
            );
            $this->err('   ' . $e->getMessage());
            return false;
        }

        foreach ($remindLoans as $loan) {
            $params = ['user_id' => $user->id, 'loan_id' => $loan['loanId']];
            $this->dueDateReminderTable->delete($params);

            $dueDate = new \DateTime($loan['dueDate']);
            $params['due_date'] = $dueDate->format($this::DUE_DATE_FORMAT);
            $params['notification_date'] = date($this::DUE_DATE_FORMAT, time());

            $this->dueDateReminderTable->insert($params);
        }

        return true;
    }
}
