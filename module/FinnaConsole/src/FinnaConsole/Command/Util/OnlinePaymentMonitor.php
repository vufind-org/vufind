<?php

/**
 * Console service for processing unregistered online payments.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2024.
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

namespace FinnaConsole\Command\Util;

use Finna\Db\Entity\FinnaTransactionEntityInterface;
use Finna\Db\Service\FinnaTransactionEventLogServiceInterface;
use Finna\Db\Service\FinnaTransactionServiceInterface;
use Finna\OnlinePayment\OnlinePaymentEventLogTrait;
use Finna\OnlinePayment\OnlinePaymentHandlerTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;

use function intval;
use function sprintf;

/**
 * Console service for processing unregistered online payments.
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
#[AsCommand(
    name: 'util/online_payment_monitor'
)]
class OnlinePaymentMonitor extends AbstractUtilCommand
{
    use OnlinePaymentEventLogTrait;
    use OnlinePaymentHandlerTrait;

    /**
     * Number of hours before considering unregistered transactions to be expired.
     *
     * @var int
     */
    protected $expireHours = 3;

    /**
     * Sender email address for notification of expired transactions.
     *
     * @var string
     */
    protected $fromEmail = '';

    /**
     * Constructor
     *
     * @param \VuFind\ILS\Connection                   $ils                Catalog connection
     * @param ILSAuthenticator                         $ilsAuthenticator   ILS Authenticator
     * @param FinnaTransactionServiceInterface         $transactionService Transaction database service
     * @param UserServiceInterface                     $userService        User database service
     * @param UserCardServiceInterface                 $userCardService    User card database service (for
     * OnlinePaymentHandlerTrait)
     * @param \VuFind\Config\Config                    $datasourceConfig   Data source config
     * @param \Laminas\View\Renderer\PhpRenderer       $viewRenderer       View renderer
     * @param \VuFind\Mailer\Mailer                    $mailer             Mailer
     * @param FinnaTransactionEventLogServiceInterface $eventLogService    Transaction event log database service
     */
    public function __construct(
        protected \VuFind\ILS\Connection $ils,
        protected ILSAuthenticator $ilsAuthenticator,
        protected FinnaTransactionServiceInterface $transactionService,
        protected UserServiceInterface $userService,
        protected UserCardServiceInterface $userCardService,
        protected \VuFind\Config\Config $datasourceConfig,
        protected \Laminas\View\Renderer\PhpRenderer $viewRenderer,
        protected \VuFind\Mailer\Mailer $mailer,
        FinnaTransactionEventLogServiceInterface $eventLogService
    ) {
        $this->eventLogService = $eventLogService;
        parent::__construct();
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setDescription(
                'Validate unregistered online payment transactions and send error'
                    . ' notifications'
            )
            ->addArgument(
                'expire_hours',
                InputArgument::REQUIRED,
                'Number of hours before considering unregistered transaction to be'
                    . ' expired.'
            )
            ->addArgument(
                'from_email',
                InputArgument::REQUIRED,
                'Sender email address for notification of expired transactions'
            )
            ->addArgument(
                'report_interval_hours',
                InputArgument::REQUIRED,
                'Interval when to re-send report of unresolved transactions'
            )
            ->addArgument(
                'minimum_paid_age',
                InputArgument::OPTIONAL,
                "Minimum age of transactions in 'paid' status until they are"
                    . 'considered failed (seconds, default 120)',
                120
            )
            ->addOption(
                'no-email',
                null,
                InputOption::VALUE_NONE,
                'Disable sending of any email messages'
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
        $this->expireHours = $input->getArgument('expire_hours');
        $this->fromEmail = $input->getArgument('from_email');
        $reportIntervalHours = $input->getArgument('report_interval_hours');
        $minimumPaidAge = intval($input->getArgument('minimum_paid_age'));
        $disableEmail = $input->getOption('no-email') ?: false;

        // Abort if we have an invalid minimum paid age.
        if ($minimumPaidAge < 10) {
            $output->writeln('Minimum paid age must be at least 10 seconds');
            return 1;
        }

        $this->msg('OnlinePayment monitor started');
        $expiredCnt = $failedCnt = $registeredCnt = $remindCnt = 0;
        $report = [];
        $failed = $this->transactionService->getFailedTransactions($minimumPaidAge);
        foreach ($failed as $t) {
            $this->processTransaction(
                $t,
                $report,
                $registeredCnt,
                $expiredCnt,
                $failedCnt
            );
        }

        // Report paid and unregistered transactions whose registration
        // can not be re-tried:
        $unresolved = $this->transactionService->getUnresolvedTransactions($reportIntervalHours);
        foreach ($unresolved as $t) {
            $this->processUnresolvedTransaction($t, $report, $remindCnt);
        }

        if ($registeredCnt) {
            $this->msg("Total registered: $registeredCnt");
        }
        if ($expiredCnt) {
            $this->msg("Total expired: $expiredCnt");
        }
        if ($failedCnt) {
            $this->msg("Total failed: $failedCnt");
        }
        if ($remindCnt) {
            $this->msg("Total to be reminded: $remindCnt");
        }

        if (!$disableEmail) {
            $this->sendReports($report);
        }

        $this->msg('OnlinePayment monitor completed');

        return 0;
    }

    /**
     * Try to register a failed transaction.
     *
     * @param FinnaTransactionEntityInterface $t             Transaction
     * @param array                           $report        Transactions to be reported.
     * @param int                             $registeredCnt Number of registered transactions.
     * @param int                             $expiredCnt    Number of expired transactions.
     * @param int                             $failedCnt     Number of failed transactions.
     *
     * @return bool success
     */
    protected function processTransaction(
        FinnaTransactionEntityInterface $t,
        array &$report,
        int &$registeredCnt,
        int &$expiredCnt,
        int &$failedCnt
    ) {
        $this->msg(
            "Registering transaction id {$t->getId()} / {$t->getTransactionIdentifier()}"
            . " (status: {$t->getStatus()->value} / {$t->getStatusMessage()}"
            . ", paid: {$t->getPaidDate()->format('Y-m-d H:i:s')})"
        );

        // Check if the transaction has not been registered for too long
        $now = new \DateTime();
        $diff = $now->diff($t->getPaidDate());
        $diffHours = ($diff->days * 24) + $diff->h;
        if ($diffHours > $this->expireHours) {
            // Transaction has expired
            if (!isset($report[$t->getSourceId()])) {
                $report[$t->getSourceId()] = 0;
            }
            $report[$t->getSourceId()]++;
            $expiredCnt++;

            $t->setReportedAndExpired();
            $this->transactionService->persistEntity($t);
            $this->addTransactionEvent($t, 'Marked as reported and expired');

            $this->msg('Transaction ' . $t->getTransactionIdentifier() . ' expired.');
            return true;
        }

        try {
            $user = $t->getUser();
            if (!($patron = $this->getPatronForTransaction($t))) {
                if ($user) {
                    $this->warn(
                        "Catalog login failed for user {$user->getUsername()} (id {$user->getId()}),"
                        . " card {$t->getCatUsername()}"
                    );
                    $t->setRegistrationFailed('patron login error');
                    $this->transactionService->persistEntity($t);
                    $this->addTransactionEvent($t, 'Patron login failed');
                } else {
                    $this->warn("Library card not found for user {$t->getUserId()}, card {$t->getCatUsername()}");
                    $t->setRegistrationFailed('card not found');
                    $this->transactionService->persistEntity($t);
                    $this->addTransactionEvent(
                        $t,
                        "Library card not found for user id {$t->getUserId()}",
                        [
                            'user_id' => $t->getUserId(),
                            'card' => $t->getCatUsername(),
                        ]
                    );
                }
                $failedCnt++;
                return false;
            }

            if (!$this->markFeesAsPaidForPatron($patron, $t)) {
                $failedCnt++;
                return false;
            }
            $registeredCnt++;
            return true;
        } catch (\Exception $e) {
            $this->warn(
                "Exception while processing transaction {$t->getId()} for user id {$t->getUserId()}"
                . ", card {$t->getCatUsername()}: "
                . (string)$e
            );
            $this->addTransactionEvent(
                $t,
                'Exception while processing transaction',
                [
                    'exception' => (string)$e,
                ]
            );
            $failedCnt++;
            return false;
        }
    }

    /**
     * Process an unresolved transaction.
     *
     * @param FinnaTransactionEntityInterface $t         Transaction
     * @param array                           $report    Transactions to be reported.
     * @param int                             $remindCnt Number of transactions to be reported as unresolved.
     *
     * @return void
     */
    protected function processUnresolvedTransaction(FinnaTransactionEntityInterface $t, &$report, &$remindCnt)
    {
        $this->msg("Transaction {$t->getId()} with identifier {$t->getTransactionIdentifier()} still unresolved.");

        $t->setReportedAndExpired();
        $this->transactionService->persistEntity($t);
        if (!isset($report[$t->getSourceId()])) {
            $report[$t->getSourceId()] = 0;
        }
        $report[$t->getSourceId()]++;
        $remindCnt++;
    }

    /**
     * Send email reports of unresolved transactions
     * (that need to be resolved manually via AdminInterface).
     *
     * @param array $report Transactions to be reported.
     *
     * @return void
     */
    protected function sendReports($report)
    {
        $subject = 'Finna: ilmoitus tietokannan %s epäonnistuneista verkkomaksuista';

        foreach ($report as $driver => $cnt) {
            if ($cnt) {
                $settings = $this->ils->getConfig(
                    'onlinePayment',
                    ['id' => "$driver.123"]
                );
                if (!$settings || empty($settings['errorEmail'])) {
                    if (!empty($this->datasourceConfig[$driver]['feedbackEmail'])) {
                        $settings['errorEmail']
                            = $this->datasourceConfig[$driver]['feedbackEmail'];
                        $this->warn(
                            '  No error email for expired transactions defined for '
                            . "driver $driver, using feedback email ($cnt expired "
                            . 'transactions)'
                        );
                    } else {
                        $this->err(
                            '  No error email for expired transactions defined for '
                            . "driver $driver ($cnt expired transactions)",
                            '='
                        );
                        continue;
                    }
                }

                $email = $settings['errorEmail'];
                $this->msg(
                    "[$driver] Inform $cnt expired transactions "
                    . "for driver $driver to $email"
                );

                $params = [
                   'driver' => $driver,
                   'cnt' => $cnt,
                ];
                $messageSubject = sprintf($subject, $driver);
                $message = $this->viewRenderer->render('Email/online-payment-alert.phtml', $params);

                try {
                    $this->mailer->setMaxRecipients(0);
                    $this->mailer->send(
                        $email,
                        $this->fromEmail,
                        $messageSubject,
                        $message
                    );
                } catch (\Exception $e) {
                    $this->err(
                        "    Failed to send error email to staff: $email "
                            . "(driver: $driver)",
                        'Failed to send error email to staff'
                    );
                    $this->logException($e);
                    continue;
                }
            }
        }
    }

    /**
     * Log a payment info message
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function logPaymentInfo(string $msg): void
    {
        $this->msg($msg);
    }
}
