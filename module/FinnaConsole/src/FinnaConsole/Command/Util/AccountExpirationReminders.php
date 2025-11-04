<?php

/**
 * Console service for reminding users x days before account expiration
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2022.
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
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace FinnaConsole\Command\Util;

use DateInterval;
use DateTime;
use Finna\Db\Entity\FinnaUserEntityInterface;
use Finna\Db\Service\FinnaUserServiceInterface;
use Laminas\I18n\Translator\Translator;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\View\Resolver\AggregateResolver;
use Laminas\View\Resolver\TemplatePathStack;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Db\Service\SearchServiceInterface;
use VuFind\Db\Service\TagServiceInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Mailer\Mailer;

use function assert;
use function count;
use function in_array;
use function sprintf;

/**
 * Console service for reminding users x days before account expiration
 *
 * @category VuFind
 * @package  Service
 * @author   Jyrki Messo <jyrki.messo@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/account_expiration_reminders'
)]
class AccountExpirationReminders extends AbstractUtilCommand
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use EmailWithRetryTrait;

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
     * UrllHelper
     *
     * @var urlHelper
     */
    protected $urlHelper;

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
     * Currently active view path
     *
     * @var string
     */
    protected $currentViewPath = '';

    /**
     * Constructor
     *
     * @param FinnaUserServiceInterface          $userService      User database service
     * @param SearchServiceInterface             $searchService    Search database service
     * @param ResourceServiceInterface           $resourceService  Resource database service
     * @param UserListServiceInterface           $userListService  User list database service
     * @param TagServiceInterface                $tagService       Tag database service
     * @param \Laminas\View\Renderer\PhpRenderer $renderer         View renderer
     * @param \VuFind\Config\Config              $datasourceConfig Data source config
     * @param Mailer                             $mailer           Mailer
     * @param TranslatorInterface                $translator       Translator
     * @param \VuFind\Config\PluginManager       $configManager    Config manager
     */
    public function __construct(
        protected FinnaUserServiceInterface $userService,
        protected SearchServiceInterface $searchService,
        protected ResourceServiceInterface $resourceService,
        protected UserListServiceInterface $userListService,
        protected TagServiceInterface $tagService,
        protected \Laminas\View\Renderer\PhpRenderer $renderer,
        protected \VuFind\Config\Config $datasourceConfig,
        Mailer $mailer,
        TranslatorInterface $translator,
        protected \VuFind\Config\PluginManager $configManager
    ) {
        $this->urlHelper = $renderer->plugin('url');
        $this->translator = $translator;
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
            ->setDescription(
                'Send email reminders to users whose accounts are about to expire.'
            )
            ->addArgument(
                'vufind_dir',
                InputArgument::REQUIRED,
                'VuFind base installation directory'
            )
            ->addArgument('view_dir', InputArgument::REQUIRED, 'View directory')
            ->addArgument(
                'expiration_days',
                InputArgument::REQUIRED,
                'After how many inactive days a user account will expire.'
                . 'Values less than 180 are not valid.'
            )
            ->addArgument(
                'remind_days_before',
                InputArgument::REQUIRED,
                'Begin reminding the user x days before the actual expiration'
            )
            ->addArgument(
                'frequency',
                InputArgument::REQUIRED,
                'How often (in days) the user will be reminded'
            )
            ->addOption(
                'report',
                null,
                null,
                'If set, only a report of messages to be sent is generated'
            );
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

        // Inactive user account will expire in expirationDays days
        $this->expirationDays = $input->getArgument('expiration_days');

        if (!is_numeric($this->expirationDays) || $this->expirationDays < 180) {
            $output->writeln('expiration_days must be at least 180.');
            return 1;
        }

        // Start reminding remindDaysBefore before expiration
        $this->remindDaysBefore = $input->getArgument('remind_days_before');
        if (!is_numeric($this->expirationDays) || $this->expirationDays <= 0) {
            $output->writeln('remind_days must be at least 1.');
            return 1;
        }

        // Remind every reminderFrequency days when reminding period has started
        $this->reminderFrequency = $input->getArgument('frequency');
        if (!is_numeric($this->reminderFrequency) || $this->reminderFrequency <= 0) {
            $output->writeln('frequency must be at least 1.');
            return 1;
        }

        $this->reportOnly = $input->getOption('report');

        try {
            $users = $this->getUsersToRemind(
                $this->expirationDays,
                $this->remindDaysBefore,
                $this->reminderFrequency
            );
            $count = 0;

            foreach ($users as $user) {
                $this->msg(
                    "Sending expiration reminder for user {$user->getUsername()} (id {$user->getId()})"
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
     * Returns all users that have not been active for given amount of days.
     *
     * @param int $days             Expiration limit (in days) for user accounts
     * @param int $remindDaysBefore How many days before expiration reminder starts
     * @param int $frequency        The freqency in days for reminding the user
     *
     * @return FinnaUserEntityInterface[]
     */
    protected function getUsersToRemind($days, $remindDaysBefore, $frequency): array
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

        $limitDate = new DateTime(sprintf('-%d days', (int)$days - (int)$remindDaysBefore));

        $initialReminderThreshold = time() + $frequency * 86400;

        $users = $this->userService->getExpiringUsers($limitDate);

        $results = [];
        foreach ($users as $user) {
            assert($user instanceof FinnaUserEntityInterface);
            $secsSinceLast = time() - ($user->getFinnaLastExpirationReminderDate()?->getTimestamp() ?? 0);
            if ($secsSinceLast < $frequency * 86400) {
                continue;
            }

            if (trim($user->getEmail()) === '') {
                $this->msg(
                    "User {$user->getUsername()} (id {$user->getId()}) does not have an"
                    . ' email address, bypassing expiration reminder'
                );
                continue;
            }

            // Avoid sending a reminder if it comes too late (i.e. no reminders have
            // been sent before and there's less than $frequency days before
            // expiration)
            $expirationDatetime = $user->getLastLogin();
            $expirationDatetime->add(new DateInterval('P' . $days . 'D'));

            $lastExpirationReminder = $user->getFinnaLastExpirationReminderDate()?->getTimestamp() ?? 0;
            if (
                (($lastExpirationReminder) < $user->getLastLogin()->getTimestamp()
                && $expirationDatetime->getTimestamp() < $initialReminderThreshold)
                || $expirationDatetime->getTimestamp() < time()
            ) {
                $expires = $expirationDatetime->format('Y-m-d');
                $this->msg(
                    "User {$user->getUsername()} (id {$user->getId()}) expires already on"
                    . " $expires without previous reminders, bypassing expiration"
                    . ' reminder'
                );
                continue;
            }

            // Check that the user has some saved content so that no reminder is sent
            // if there is none.
            if (
                $user->getFinnaDueDateReminder() === 0
                && !$this->tagService->getUserTagsFromFavorites($user)
                && !$this->searchService->getSearches('-', $user)
                && !$this->resourceService->getFavorites($user)
            ) {
                $this->msg(
                    "User {$user->getUsername()} (id {$user->getId()}) has no saved content, bypassing expiration"
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
     * @param FinnaUserEntityInterface $user           User.
     * @param int                      $expirationDays Number of days after the account expires.
     *
     * @return bool
     */
    protected function sendAccountExpirationReminder(FinnaUserEntityInterface $user, int $expirationDays): bool
    {
        if (str_contains($user->getUsername(), ':')) {
            [$userInstitution, $userName] = explode(':', $user->getUsername(), 2);
        } else {
            $userInstitution = 'national';
            $userName = $user->getUsername();
        }

        $consoleMsgPrefix = "User {$user->getUsername()} (id {$user->getId()}) institution $userInstitution";

        $dsConfig = $this->datasourceConfig[$userInstitution] ?? [];
        if (!empty($dsConfig['disableAccountExpirationReminders'])) {
            $this->msg("$consoleMsgPrefix has reminders disabled, bypassing expiration reminder");
            return false;
        }

        if (
            !$this->currentInstitution
            || $userInstitution != $this->currentInstitution
        ) {
            $templateDirs = [
                "{$this->baseDir}/themes/finna2/templates",
            ];
            if (!$viewPath = $this->resolveViewPath($userInstitution)) {
                $this->err(
                    "$consoleMsgPrefix: Could not resolve view path",
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

            // Build the config path as a relative path from LOCAL_OVERRIDE_DIR.
            // This is a bit of a hack, but the configuration plugin manager doesn't
            // currently support specifying an absolute path alone.
            $parts = explode('/', LOCAL_OVERRIDE_DIR);
            $configPath = str_repeat('../', count($parts)) . ".$viewPath/local/config/vufind";
            $this->currentSiteConfig = $this->configManager->get(
                'config',
                compact('configPath')
            );
            $this->currentMultiBackendConfig = $this->configManager->get(
                'MultiBackend',
                compact('configPath')
            );
        }

        if (
            isset($this->currentSiteConfig['System']['available'])
            && !$this->currentSiteConfig['System']['available']
        ) {
            $this->msg("$consoleMsgPrefix: site is marked unavailable, bypassing expiration reminder");
            return false;
        }

        if (!empty($this->currentSiteConfig['Authentication']['hideLogin'])) {
            $this->msg("$consoleMsgPrefix: site has login disabled, bypassing expiration reminder");
            return false;
        }

        $authMethod = $this->currentSiteConfig['Authentication']['method'] ?? '';
        if ('ChoiceAuth' === $authMethod) {
            $authOptions = explode(
                ',',
                $this->currentSiteConfig['ChoiceAuth']['choice_order'] ?? ''
            );
        } else {
            $authOptions = [$authMethod];
        }
        // Map user's authentication method 'email' to 'ils' or 'multiils'
        // accordingly:
        $userAuthMethod = $user->getAuthMethod();
        if ('email' === $userAuthMethod) {
            if (in_array('ILS', $authOptions)) {
                $userAuthMethod = 'ils';
            } elseif (in_array('MultiILS', $authOptions)) {
                $userAuthMethod = 'multiils';
            }
        }
        $match = false;
        foreach ($authOptions as $option) {
            if (strcasecmp($userAuthMethod, $option) === 0) {
                $match = true;
                break;
            }
        }
        if (!$match) {
            $this->msg(
                "$consoleMsgPrefix: user's authentication method '$userAuthMethod' is not in available authentication"
                . ' methods (' . implode(',', $authOptions) . '), bypassing expiration reminder'
            );
            return false;
        }

        if (strcasecmp($userAuthMethod, 'multiils') === 0) {
            [$target] = explode('.', $userName);
            if (empty($this->currentMultiBackendConfig['Drivers'][$target])) {
                $this->msg("$consoleMsgPrefix: unknown MultiILS login target, bypassing expiration reminder");
                return false;
            }
            $loginTargets = $this->currentMultiBackendConfig['Login']['drivers']
                ? $this->currentMultiBackendConfig['Login']['drivers']->toArray()
                : [];
            if (!in_array($target, (array)$loginTargets)) {
                $this->msg(
                    "$consoleMsgPrefix: MultiILS target '$target' not available for login, bypassing expiration"
                    . ' reminder'
                );
                return false;
            }
        }

        $expirationDatetime = $user->getLastLogin();
        $expirationDatetime->add(new DateInterval('P' . $expirationDays . 'D'));

        $language = $this->currentSiteConfig['Site']['language'] ?? 'fi';
        $validLanguages = array_keys((array)$this->currentSiteConfig['Languages']);

        if (in_array($user->getLastLanguage(), $validLanguages, true)) {
            $language = $user->getLastLanguage();
        }

        assert($this->translator instanceof Translator);
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
        $firstName = $user->getFirstname();
        if (!$firstName) {
            $firstName = $user->getLastname();
        }
        if (!$firstName) {
            $firstName = $userName;
        }

        $savedSearchCnt = count($this->searchService->getSearches('-', $user));
        $publicListCnt = $privateListCnt = 0;

        $userLists = $this->userListService->getUserListsByUser($user);
        if ($userLists) {
            $publicListCnt = count(
                array_filter(
                    $userLists,
                    function ($list) {
                        return $list->isPublic();
                    }
                )
            );
            $privateListCnt = count($userLists) - $publicListCnt;
        }

        $params = [
            'loginMethod' => strtolower($userAuthMethod),
            'username' => $userName,
            'firstname' => $firstName,
            'expirationDate' =>  $expirationDatetime->format('d.m.Y'),
            'serviceName' => $serviceName,
            'serviceAddress' => $serviceAddress,
            'publicListCnt' => $publicListCnt,
            'privateListCnt' => $privateListCnt,
            'savedSearchCnt' => $savedSearchCnt,
        ];

        $subject = $this->translate(
            'account_expiration_subject',
            [
                '%%expirationDate%%' => $params['expirationDate'],
                '%%serviceName%%' => $serviceName,
                '%%serviceAddress%%' => $serviceAddress,
            ]
        );

        $message = $this->renderer->render(
            'Email/account-expiration-reminder.phtml',
            $params
        );

        $to = $user->getEmail();
        try {
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
                $this->sendEmailWithRetry($to, $from, $subject, $message);
                $user->setFinnaLastExpirationReminderDate(new DateTime());
                $this->userService->persistEntity($user);
            }
        } catch (\Exception $e) {
            $this->err(
                "$consoleMsgPrefix: Failed to send an expiration reminder, email '$to')",
                'Failed to send an expiration reminder to a user'
            );
            $this->err('   ' . $e->getMessage());
            return false;
        }
        return true;
    }
}
