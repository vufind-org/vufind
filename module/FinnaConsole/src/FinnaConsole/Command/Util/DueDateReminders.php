<?php

/**
 * Console service for sending due date reminders.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2013-2024.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace FinnaConsole\Command\Util;

use Finna\Crypt\SecretCalculator;
use Finna\Db\Entity\FinnaUserCardEntityInterface;
use Finna\Db\Service\FinnaDueDateReminderServiceInterface;
use Finna\Db\Service\UserServiceInterface;
use Laminas\Mvc\I18n\Translator;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\View\Resolver\AggregateResolver;
use Laminas\View\Resolver\TemplatePathStack;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Mailer\Mailer;

use function assert;
use function count;
use function in_array;

/**
 * Console service for sending due date reminders.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/due_date_reminders'
)]
class DueDateReminders extends AbstractUtilCommand
{
    use EmailWithRetryTrait;

    /**
     * Date format for due dates in database.
     */
    public const DUE_DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * URL Helper
     *
     * @var \VuFind\View\Helper\Root\Url
     */
    protected $urlHelper;

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
     * @param UserServiceInterface                 $userService            User database service
     * @param UserCardServiceInterface             $userCardService        User card database service
     * @param FinnaDueDateReminderServiceInterface $dueDateReminderService Due date reminder database service
     * @param \VuFind\ILS\Connection               $catalog                ILS connection
     * @param \VuFind\Auth\ILSAuthenticator        $ilsAuthenticator       ILS authenticator
     * @param \VuFind\Config\Config                $mainConfig             Main config
     * @param \VuFind\Config\Config                $datasourceConfig       Data source config
     * @param PhpRenderer                          $viewRenderer           View renderer
     * @param \VuFind\Record\Loader                $recordLoader           Record loader
     * @param Mailer                               $mailer                 Mailer
     * @param Translator                           $translator             Translator
     * @param SecretCalculator                     $secretCalculator       Secret calculator
     */
    public function __construct(
        protected UserServiceInterface $userService,
        protected UserCardServiceInterface $userCardService,
        protected FinnaDueDateReminderServiceInterface $dueDateReminderService,
        protected \VuFind\ILS\Connection $catalog,
        protected \VuFind\Auth\ILSAuthenticator $ilsAuthenticator,
        protected \VuFind\Config\Config $mainConfig,
        protected \VuFind\Config\Config $datasourceConfig,
        protected PhpRenderer $viewRenderer,
        protected \VuFind\Record\Loader $recordLoader,
        Mailer $mailer,
        protected Translator $translator,
        protected SecretCalculator $secretCalculator
    ) {
        if (
            isset($this->mainConfig->Catalog->loadNoILSOnFailure)
            && $this->mainConfig->Catalog->loadNoILSOnFailure
        ) {
            throw new \Exception('Catalog/loadNoILSOnFailure must not be enabled');
        }

        $this->urlHelper = $viewRenderer->plugin('url');
        $this->mailer = $mailer;

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
            $users = $this->userService->getUsersWithDueDateReminders();
            $this->msg('Processing ' . count($users) . ' users');

            foreach ($users as $user) {
                try {
                    $results = $this->getReminders($user);
                    $errors = $results['errors'];
                    $remindLoans = $results['remindLoans'];
                    $remindCnt = count($remindLoans);
                    $errorCnt = count($errors);
                    if ($remindCnt || $errorCnt) {
                        $this->msg(
                            "$remindCnt reminders and $errorCnt errors to send for"
                            . " user {$user->getUsername()} (id {$user->getId()})"
                        );
                        $this->sendReminder($user, $remindLoans, $errors);
                    } else {
                        $this->msg(
                            "No loans to remind for user {$user->getUsername()}"
                            . " (id {$user->getId()})"
                        );
                    }
                } catch (\Exception $e) {
                    $this->err(
                        "Exception while processing user {$user->getId()}: "
                            . $e->getMessage(),
                        'Exception occurred while processing a user'
                    );
                    while ($e = $e->getPrevious()) {
                        $this->err('  Previous exception: ' . $e->getMessage());
                    }
                }
            }
            $this->msg('Completed processing users');
        } catch (\Exception $e) {
            $this->err(
                'Exception: ' . $e->getMessage(),
                'Exception occurred'
            );
            while ($e = $e->getPrevious()) {
                $this->err('  Previous exception: ' . $e->getMessage());
            }
            return 1;
        }

        $this->mailer->resetConnection();

        return 0;
    }

    /**
     * Get reminders for a user.
     *
     * @param FinnaUserEntityInterface $user User.
     *
     * @return array Array of loans to be reminded and possible login errors.
     */
    protected function getReminders(FinnaUserEntityInterface $user): array
    {
        if (trim($user->getEmail()) === '') {
            $this->warn(
                "User {$user->getUsername()} (id {$user->getId()})"
                . ' does not have an email address, bypassing due date reminders'
            );
            return ['remindLoans' => [], 'errors' => []];
        }

        $remindLoans = [];
        $errors = [];
        foreach ($this->userCardService->getLibraryCards($user) as $card) {
            assert($card instanceof FinnaUserCardEntityInterface);
            if (!$card->getId() || $card->getFinnaDueDateReminder() === 0) {
                continue;
            }
            $ddrConfig = $this->catalog->getConfig(
                'dueDateReminder',
                ['cat_username' => $card->getCatUsername()]
            );
            // Assume ddrConfig['enabled'] may contain also something else than a
            // boolean..
            if (isset($ddrConfig['enabled']) && $ddrConfig['enabled'] !== true) {
                // Due date reminders disabled for the source
                continue;
            }

            $patron = null;
            try {
                // Note: these changes are not persisted, so there's no harm in setting them here:
                $loginUser = clone $user;
                $loginUser->setCatUsername($card->getCatUsername());
                $loginUser->setRawCatPassword($card->getRawCatPassword());
                $loginUser->setCatPassEnc($card->getCatPassEnc());

                $patron = $this->catalog->patronLogin(
                    $loginUser->getCatUsername(),
                    $this->ilsAuthenticator->getCatPasswordForUser($loginUser)
                );
            } catch (\Exception $e) {
                $this->err(
                    "Catalog login error for user {$user->getUsername()}"
                        . " (id {$user->getId()}), card {$card->getCatUsername()}"
                        . " (id {$card->getId()}): " . $e->getMessage(),
                    'Catalog login error for a user'
                );
                continue;
            }

            if (null === $patron) {
                $this->warn(
                    "Catalog login failed for user {$user->getUsername()}"
                    . " (id {$user->getId()}), card {$card->getCatUsername()}"
                    . " (id {$card->getId()}) -- disabling due date reminders for the"
                    . ' card'
                );
                $errors[] = ['card' => $card['cat_username']];
                // Disable due date reminders for this card
                if ($user->getCatUsername() === $card->getCatUsername()) {
                    // Card is the active one, update user too:
                    $user->setFinnaDueDateReminder(0);
                    $this->userService->persistEntity($user);
                }
                $card->setFinnaDueDateReminder(0);
                $this->userCardService->persistEntity($card);
                continue;
            }

            $todayTime = new \DateTime();
            try {
                $loans = $this->catalog->getMyTransactions($patron);
                // Support also older driver return value:
                if (!isset($loans['count'])) {
                    $loans = [
                        'count' => count($loans),
                        'records' => $loans,
                    ];
                }
            } catch (\Exception $e) {
                $this->err(
                    "Exception trying to get loans for user {$user->getUsername()}"
                        . " (id {$user->getId()}), card {$card->getCatUsername()}"
                        . " (id {$card->getId()}): "
                        . $e->getMessage(),
                    'Exception trying to get loans for a user'
                );
                continue;
            }
            foreach ($loans['records'] as $loan) {
                $dueDate = new \DateTime($loan['duedate']);
                $dayDiff = $dueDate->diff($todayTime)->days;
                if (
                    $todayTime >= $dueDate
                    || $dayDiff <= $card->getFinnaDueDateReminder()
                ) {
                    if ($this->dueDateReminderService->getRemindedLoan($user, $loan['item_id'], $dueDate)) {
                        // Reminder already sent
                        continue;
                    }

                    $record = null;
                    if (isset($loan['id'])) {
                        $record = $this->recordLoader->load(
                            $loan['id'],
                            'Solr',
                            true
                        );
                    }

                    $dateFormat
                        = $this->currentSiteConfig['Site']['displayDateFormat']
                        ?? $this->mainConfig->Site->displayDateFormat;

                    $remindLoans[] = [
                        'loanId' => $loan['item_id'],
                        'dueDate' => $loan['duedate'],
                        'dueDateFormatted' => $dueDate->format($dateFormat),
                        'title' => $loan['title'] ?? null,
                        'record' => $record,
                    ];
                }
            }
        }
        return ['remindLoans' => $remindLoans, 'errors' => $errors];
    }

    /**
     * Send reminders for a user.
     *
     * @param FinnaUserEntityInterface $user        User.
     * @param array                    $remindLoans Loans to be reminded.
     * @param array                    $errors      Errors in due date checking.
     *
     * @return boolean success.
     */
    protected function sendReminder(FinnaUserEntityInterface $user, $remindLoans, $errors)
    {
        if (trim($user->getEmail()) === '') {
            $this->msg(
                "User {$user->getUsername()} (id {$user->getId()})"
                . ' does not have an email address, bypassing due date reminders'
            );
            return false;
        }

        [$userInstitution, ] = explode(':', $user['username'], 2);

        if (
            !$this->currentInstitution
            || $userInstitution != $this->currentInstitution
        ) {
            $templateDirs = [
                "{$this->baseDir}/themes/finna2/templates",
            ];
            if (!$viewPath = $this->resolveViewPath($userInstitution)) {
                $this->err(
                    "Could not resolve view path for user {$user->getUsername()}"
                        . " (id {$user->getId()})",
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

        $language = $this->currentSiteConfig['Site']['language'] ?? 'fi';
        $validLanguages = array_keys($this->currentSiteConfig['Languages']);
        if (
            in_array($user->getLastLanguage(), $validLanguages, true)
        ) {
            $language = $user->getLastLanguage();
        }
        assert($this->translator instanceof Translator);
        $this->translator
            ->addTranslationFile('ExtendedIni', null, 'default', $language)
            ->setLocale($language);

        $key = $this->secretCalculator->getDueDateReminderUnsubscribeSecret($user);
        $urlParams = [
            'id' => $user->getId(),
            'type' => 'reminder',
            'key' => $key,
        ];
        $unsubscribeUrl = ($this->urlHelper)('myresearch-unsubscribe')
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
        $lastLogin = $user->getLastLogin();
        $loginMethod = strtolower($user->getAuthMethod());
        $dateFormat = $this->currentSiteConfig['Site']['displayDateFormat']
            ?? $this->mainConfig->Site->displayDateFormat;

        $params = [
            'loans' => $remindLoans,
            'unsubscribeUrl' => $baseUrl . $unsubscribeUrl,
            'baseUrl' => $baseUrl,
            'lastLogin' => $lastLogin->format($dateFormat),
            'loginMethod' => $loginMethod,
            'serviceName' => $serviceName,
            'userInstitution' => $userInstitution,
            'user' => $user,
        ];

        $urlHelper = $this->urlHelper;
        if (!empty($errors)) {
            $subject = $this->translator->translate('due_date_email_error');
            $params['url'] = $baseUrl . $urlHelper('librarycards-home');
            $params['errors'] = $errors;
        } else {
            $subject = $this->translator->translate('due_date_email_subject');
            $params['url'] = $baseUrl . $urlHelper('myresearch-checkedout');
        }
        $message = $this->viewRenderer->render('Email/due-date-reminder.phtml', $params);
        $to = $user->getEmail();
        $from = $this->currentSiteConfig['Site']['email'];
        try {
            $this->sendEmailWithRetry($to, $from, $subject, $message);
        } catch (\Exception $e) {
            $this->err(
                "Failed to send due date reminders to user {$user->getUsername()}"
                    . " (id {$user->getId()}, email '$to')",
                'Failed to send due date reminders to a user'
            );
            $this->err('   ' . $e->getMessage());
            return false;
        }

        foreach ($remindLoans as $loan) {
            $this->dueDateReminderService->deleteRemindedLoan($user, $loan['loanId']);
            $this->dueDateReminderService->addRemindedLoan($user, $loan['loanId'], new \DateTime($loan['dueDate']));
        }

        return true;
    }
}
