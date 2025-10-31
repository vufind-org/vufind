<?php

/**
 * Console service for processing unregistered online payments.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2025.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\OnlinePayment;

use Laminas\View\Renderer\PhpRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Config\Feature\EmailSettingsTrait;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Mailer\Mailer;
use VuFind\OnlinePayment\OnlinePaymentEventTrait;
use VuFind\OnlinePayment\OnlinePaymentManager;

use function count;

/**
 * Console service for processing unregistered online payments.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'onlinepayment/monitor'
)]
class MonitorCommand extends Command
{
    use EmailSettingsTrait;
    use OnlinePaymentEventTrait;

    /**
     * Minimum time after payment was paid for it to be considered for retry (SECONDS).
     *
     * @var int
     */
    protected $minimumPaidAge = 120;

    /**
     * Notification report interval (MINUTES).
     *
     * @var int
     */
    protected $reportInterval = 120;

    /**
     * Retry duration from payment (MINUTES).
     *
     * @var int
     */
    protected $retryMinutes = 120;

    /**
     * Sender email address for notification of expired payments.
     *
     * @var ?string
     */
    protected $fromEmail = null;

    /**
     * Payments successfully registered
     *
     * @var int
     */
    protected $registeredCount = 0;

    /**
     * Payments that failed to register
     *
     * @var int
     */
    protected $failedCount = 0;

    /**
     * Expired payments
     *
     * @var int
     */
    protected $expiredCount = 0;

    /**
     * Output interface
     *
     * @var ?OutputInterface
     */
    protected ?OutputInterface $output = null;

    /**
     * Constructor
     *
     * @param PaymentServiceInterface    $paymentService       Payment database service
     * @param OnlinePaymentManager       $onlinePaymentManager Online payment manager
     * @param PhpRenderer                $viewRenderer         View renderer
     * @param Mailer                     $mailer               Mailer
     * @param array                      $config               VuFind configuration
     * @param AuditEventServiceInterface $auditEventService    Audit event database service
     */
    public function __construct(
        protected PaymentServiceInterface $paymentService,
        protected OnlinePaymentManager $onlinePaymentManager,
        protected PhpRenderer $viewRenderer,
        protected Mailer $mailer,
        protected array $config,
        AuditEventServiceInterface $auditEventService,
    ) {
        $this->auditEventService = $auditEventService;
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
            ->setDescription('Validate unregistered online payments and send error notifications')
            ->addOption(
                'report-interval',
                null,
                InputOption::VALUE_REQUIRED,
                'Interval for re-sending of reports of unresolved payments (minutes)',
                $this->reportInterval
            )
            ->addOption(
                'minimum-paid-age',
                null,
                InputOption::VALUE_REQUIRED,
                "Minimum age of payments in 'paid' status until they are considered failed (seconds)",
                $this->minimumPaidAge
            )
            ->addOption(
                'retry-duration',
                null,
                InputOption::VALUE_REQUIRED,
                'Duration of registration retry attempts (minutes). After the duration an unregistered payment is'
                . ' considered expired.',
                $this->retryMinutes
            )
            ->addOption(
                'from-email',
                null,
                InputOption::VALUE_REQUIRED,
                'Sender email address for notifications of expired payments (default is sender address for feedback)'
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
        $this->output = $output;
        $this->retryMinutes = (int)$input->getOption('retry-duration');
        $this->fromEmail = $input->getOption('from-email') ?? $this->getEmailSenderAddress($this->config);
        $this->reportInterval = (int)$input->getOption('report-interval');
        $this->minimumPaidAge = (int)$input->getOption('minimum-paid-age');
        $disableEmail = $input->getOption('no-email') ?: false;

        // Abort if we have an invalid minimum paid age.
        if ($this->minimumPaidAge < 10) {
            $output->writeln('Minimum paid age must be at least 10 seconds');
            return 1;
        }

        $this->msg('Online payment monitor started');
        $failedPayments = $this->paymentService->getFailedPayments($this->minimumPaidAge);
        foreach ($failedPayments as $payment) {
            $this->processPayment($payment);
        }

        // Report paid and unregistered payments whose registration can not be re-tried:
        $unresolvedPayments = $this->paymentService->getUnresolvedPaymentsToReport($this->reportInterval);

        if ($this->registeredCount) {
            $this->msg("Total registered: $this->registeredCount");
        }
        if ($this->expiredCount) {
            $this->msg("Total expired: $this->expiredCount");
        }
        if ($this->failedCount) {
            $this->msg("Total failed: $this->failedCount");
        }

        if (!$disableEmail && $unresolvedPayments) {
            $this->msg('Total to be reminded: ' . count($unresolvedPayments));
            $this->sendReports($unresolvedPayments);
        }

        $this->msg('Online payment monitor completed');

        return 0;
    }

    /**
     * Try to register a payment that wasn't previously registered successfully.
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return void
     */
    protected function processPayment(PaymentEntityInterface $payment): void
    {
        $this->msg(
            "Registering payment id {$payment->getId()} / {$payment->getLocalIdentifier()}"
            . " (status: {$payment->getStatus()->value} / {$payment->getStatusMessage()}"
            . ", paid: {$payment->getPaidDate()->format('Y-m-d H:i:s')})"
        );

        // Check if the payment has remained unregistered for too long
        if (time() - $payment->getPaidDate()->getTimestamp() > $this->retryMinutes * 60) {
            // Payment has expired
            $payment->applyRegistrationExpiredStatus();
            $this->onlinePaymentManager
                ->persistEntityWithAuditEvent($payment, AuditEventSubtype::PaymentRegistration, 'Marked as expired');
            $this->msg('Payment ' . $payment->getLocalIdentifier() . ' marked as expired.');
            return;
        }

        try {
            if (!$this->onlinePaymentManager->registerPaymentWithILS($payment)) {
                ++$this->failedCount;
                return;
            }
            ++$this->registeredCount;
            return;
        } catch (\Exception $e) {
            $this->msg(
                "Exception while processing payment {$payment->getId()} for user id {$payment->getUser()?->getId()}"
                . ", card {$payment->getCatUsername()}: "
                . (string)$e
            );
            $this->addPaymentEvent(
                $payment,
                AuditEventSubtype::PaymentRegistration,
                'Exception processing payment',
                ['error' => (string)$e]
            );
            ++$this->failedCount;
            return;
        }
    }

    /**
     * Send email reports of unresolved payments that need to be resolved manually.
     *
     * @param PaymentEntityInterface[] $payments Payments to be reported.
     *
     * @return void
     */
    protected function sendReports(array $payments): void
    {
        $paymentsBySourceIls = [];
        foreach ($payments as $payment) {
            $paymentsBySourceIls[$payment->getSourceIls()][] = $payment;
        }
        foreach ($paymentsBySourceIls as $source => $sourcePayments) {
            $errorCount = count($sourcePayments);
            if (!($recipient = $this->getErrorEmail($source))) {
                $msg = "No error email for expired payments defined for $source ($errorCount errors)";
                $this->msg($msg);
                $this->err($msg);
                continue;
            }
            $this->msg("Inform $errorCount expired payments to $recipient (source: $source)");

            $adminUrl = ($this->viewRenderer->plugin('url'))('admin-payments');
            $params = compact('source', 'errorCount', 'adminUrl');
            $message = $this->viewRenderer->render('Email/online-payment-alert.phtml', $params);

            try {
                $this->mailer->setMaxRecipients(0);
                $this->mailer->send(
                    $recipient,
                    $this->fromEmail,
                    '',
                    $message
                );
                foreach ($sourcePayments as $payment) {
                    $payment->applyReportedStatus();
                    $this->paymentService->persistEntity($payment);
                }
            } catch (\Exception $e) {
                $this->msg(
                    "Failed to send error email to staff at $recipient (source: $source): " . (string)$e
                );
                $this->err('Failed to send error email to staff');
                continue;
            }
        }
    }

    /**
     * Get error email recipient address for a source ILS
     *
     * @param string $sourceIls Source ILS
     *
     * @return string
     */
    protected function getErrorEmail(string $sourceIls): string
    {
        $paymentConfig = $this->onlinePaymentManager->getOnlinePaymentConfig($sourceIls);
        return $paymentConfig['errorEmail'] ?? '';
    }

    /**
     * Output a message with a timestamp
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function msg($msg)
    {
        $msg = date('Y-m-d H:i:s') . ' [' . getmypid() . "] $msg";
        $this->output->writeln($msg);
    }

    /**
     * Output an error message with a timestamp
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function err($msg)
    {
        if ($this->output instanceof ConsoleOutputInterface) {
            $msg = date('Y-m-d H:i:s') . ' [' . getmypid() . "] $msg";
            $this->output->getErrorOutput()->writeln($msg);
        }
    }
}
