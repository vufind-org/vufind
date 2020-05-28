<?php
/**
 * Console service for processing unregistered online payments.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2018.
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
namespace FinnaConsole\Service;

use Finna\Db\Row\User;
use Finna\Db\Table\Transaction;

use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\RequestInterface as Request;

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
class OnlinePaymentMonitor extends AbstractService
{
    /**
     * ILS connection.
     *
     * @var \Finna\ILS\Connection
     */
    protected $catalog = null;

    /**
     * Configuration
     *
     * @var \Zend\Config\Config
     */
    protected $mainConfig = null;

    /**
     * Datasource configuration
     *
     * @var \Zend\Config\Config
     */
    protected $datasourceConfig = null;

    /**
     * Table for user accounts
     *
     * @var \VuFind\Config
     */
    protected $configReader = null;

    /**
     * Transaction table
     *
     * @var \Finna\Db\Table\Transaction
     */
    protected $transactionTable = null;

    /**
     * User account table
     *
     * @var \Finna\Db\Table\User
     */
    protected $userTable = null;

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
     * View manager
     *
     * @var Zend\Mvc\View\Console\ViewManage
     */
    protected $viewManager = null;

    /**
     * View renderer
     *
     * @var Zend\View\Renderer\PhpRenderer
     */
    protected $viewRenderer = null;

    /**
     * Number of hours before considering unregistered transactions to be expired.
     *
     * @var int
     */
    protected $expireHours;

    /**
     * Send eamil address for notification of expired transactions.
     *
     * @var string
     */
    protected $fromEmail;

    /**
     * Interval (in hours) when to re-send repor of unresolved transactions.
     *
     * @var int
     */
    protected $reportIntervalHours;

    /**
     * Minimum age of paid transactions before they're considered failed.
     *
     * @var int
     */
    protected $minimumPaidAge;

    /**
     * Constructor
     *
     * @param \Finna\ILS\Connection               $catalog          Catalog
     *                                                              connection
     * @param \Finna\Db\Table\Transaction         $transactionTable Transaction table
     * @param \Finna\Db\Table\User                $userTable        User table
     * @param \VuFind\Config                      $configReader     Config reader
     * @param \Zend\ServiceManager\ServiceManager $serviceManager   Service manager.
     * @param \Zend\Mvc\View\Console\ViewManage   $viewManager      View manager
     * @param Zend\View\Renderer\PhpRenderer      $viewRenderer     View renderer
     */
    public function __construct($catalog, $transactionTable, $userTable,
        $configReader, $serviceManager, $viewManager, $viewRenderer
    ) {
        $this->catalog = $catalog;
        $this->datasourceConfig = $configReader->get('datasources');
        $this->configReader = $configReader;
        $this->transactionTable = $transactionTable;
        $this->userTable = $userTable;
        $this->serviceManager = $serviceManager;
        $this->viewManager = $viewManager;
        $this->viewRenderer = $viewRenderer;
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
        if (count($arguments) < 3) {
            echo $this->usage();
            return false;
        }

        $this->collectScriptArguments($arguments);
        $this->msg('OnlinePayment monitor started');

        $expiredCnt = $failedCnt = $registeredCnt = $remindCnt = 0;
        $report = [];
        $user = false;
        $failed = $this->transactionTable
            ->getFailedTransactions($this->minimumPaidAge);
        foreach ($failed as $t) {
            $this->processTransaction(
                $t, $report, $registeredCnt, $expiredCnt, $failedCnt, $user
            );
        }

        // Report paid and unregistered transactions whose registration
        // can not be re-tried:
        $unresolved = $this->transactionTable->getUnresolvedTransactions(
            $this->reportIntervalHours
        );
        foreach ($unresolved as $t) {
            $this->processUnresolvedTransaction($t, $report, $remindCnt);
        }

        if ($registeredCnt) {
            $this->msg("  Total registered: $registeredCnt");
        }
        if ($expiredCnt) {
            $this->msg("  Total expired: $expiredCnt");
        }
        if ($failedCnt) {
            $this->msg("  Total failed: $failedCnt");
        }
        if ($remindCnt) {
            $this->msg("  Total to be reminded: $remindCnt");
        }

        $this->sendReports($report);

        $this->msg('OnlinePayment monitor completed');

        return true;
    }

    /**
     * Try to register a failed transaction.
     *
     * @param Transaction $t             Transaction
     * @param array       $report        Transactions to be reported.
     * @param int         $registeredCnt Number of registered transactions.
     * @param int         $expiredCnt    Number of expired transactions.
     * @param int         $failedCnt     Number of failed transactions.
     * @param User        $user          User object.
     *
     * @return boolean success
     */
    protected function processTransaction(
        $t, &$report, &$registeredCnt, &$expiredCnt, &$failedCnt, &$user
    ) {
        $this->msg(
            "  Registering transaction id {$t->id} / {$t->transaction_id}"
            . " (status: {$t->complete} / {$t->status}, paid: {$t->paid})"
        );

        // Check if the transaction has not been registered for too long
        $now = new \DateTime();
        $paid_time = new \DateTime($t->paid);
        $diff = $now->diff($paid_time);
        $diffHours = ($diff->days * 24) + $diff->h;
        if ($diffHours > $this->expireHours) {
            if (!isset($report[$t->driver])) {
                $report[$t->driver] = 0;
            }
            $report[$t->driver]++;
            $expiredCnt++;

            $result = $this->transactionTable->setTransactionReported(
                $t->transaction_id
            );
            if (!$result) {
                $this->err(
                    '    Failed to update transaction ' . $t->transaction_id
                        . ' as reported',
                    'Failed to update a transaction as reported'
                );
            }

            $t->complete = Transaction::STATUS_REGISTRATION_EXPIRED;
            if (!$t->save()) {
                $this->err(
                    '    Failed to update transaction ' . $t->transaction_id
                        . ' as expired',
                    'Failed to update a transaction as expired'
                );
            } else {
                $this->msg('    Transaction ' . $t->transaction_id . ' expired.');
                return true;
            }
        } else {
            if ($user === false || $t->user_id != $user->id) {
                $user = $this->userTable->getById($t->user_id);
            }

            $patron = null;
            foreach ($user->getLibraryCards() as $card) {
                $card = $user->getLibraryCard($card['id']);

                if ($card['cat_username'] == $t->cat_username) {
                    try {
                        $cardUser = $this->userTable->createRow();
                        $cardUser->cat_username = $card['cat_username'];
                        $cardUser->cat_pass_enc = $card['cat_pass_enc'];
                        $patron = $this->catalog->patronLogin(
                            $card['cat_username'], $cardUser->getCatPassword()
                        );

                        if ($patron) {
                            break;
                        }
                    } catch (\Exception $e) {
                        $this->err(
                            'Patron login error: ' . $e->getMessage(),
                            'Patron login failed for a user'
                        );
                        $this->logException($e);
                    }
                }
            }

            if (!$patron) {
                $this->warn(
                    "Catalog login failed for user {$user->username}"
                    . " (id {$user->id}), card {$card->cat_username}"
                    . " (id {$card->id})"
                );
                $failedCnt++;
                return false;
            }

            try {
                $this->catalog->markFeesAsPaid(
                    $patron, $t->amount, $t->transaction_id, $t->id
                );
                $result = $this->transactionTable->setTransactionRegistered(
                    $t->transaction_id
                );
                if (!$result) {
                    $this->err(
                        '    Failed to update transaction ' . $t->transaction_id
                            . ' as registered',
                        'Failed to update a transaction as expired'
                    );
                }
                $registeredCnt++;
                return true;
            } catch (\Exception $e) {
                $this->err(
                    '    Registration of transaction '
                        . $t->transaction_id . " failed for user {$user->username}"
                        . " (id {$user->id}), card {$card->cat_username}"
                        . " (id {$card->id})",
                    'Registration of a transaction failed'
                );
                $this->err('      ' . $e->getMessage());
                $this->logException($e);

                $result = $this->transactionTable->setTransactionRegistrationFailed(
                    $t->transaction_id, $e->getMessage()
                );
                if (!$result) {
                    $this->err(
                        'Error updating transaction ' . $t->transaction_id
                            . ' status: registration failed',
                        'Failed to update a transaction status: registration failed'
                    );
                }
                $failedCnt++;
                return false;
            }
        }
    }

    /**
     * Process an unresolved transaction.
     *
     * @param Transaction $t         Transaction
     * @param array       $report    Transactions to be reported.
     * @param int         $remindCnt Number of transactions to be
     *                               reported as unresolved.
     *
     * @return void
     */
    protected function processUnresolvedTransaction($t, &$report, &$remindCnt)
    {
        $this->msg("  Transaction id {$t->transaction_id} still unresolved.");

        if (!$this->transactionTable->setTransactionReported($t->transaction_id)) {
            $this->err(
                '    Failed to update transaction ' . $t->transaction_id
                    . ' as reported',
                'Failed to update a transaction as reported'
            );
        }
        if (!isset($report[$t->driver])) {
            $report[$t->driver] = 0;
        }
        $report[$t->driver]++;
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
                $settings = $this->catalog->getConfig(
                    'onlinePayment', ['id' => "$driver.123"]
                );
                if (!$settings || !isset($settings['errorEmail'])) {
                    if (!empty($this->datasourceConfig[$driver]['feedbackEmail'])) {
                        $settings['errorEmail']
                            = $this->datasourceConfig[$driver]['feedbackEmail'];
                        $this->warn(
                            "  No error email for expired transactions defined for "
                            . "driver $driver, using feedback email ($cnt expired "
                            . "transactions)",
                            '='
                        );
                    } else {
                        $this->err(
                            "  No error email for expired transactions defined for "
                            . "driver $driver ($cnt expired transactions)",
                            '='
                        );
                        continue;
                    }
                }

                $email = $settings['errorEmail'];
                $this->msg(
                    "  [$driver] Inform $cnt expired transactions "
                    . "for driver $driver to $email"
                );

                $params = [
                   'driver' => $driver,
                   'cnt' => $cnt
                ];
                $messageSubject = sprintf($subject, $driver);

                $message = $this->viewRenderer
                    ->render('Email/online-payment-alert.phtml', $params);

                try {
                    $mailer = $this->serviceManager
                        ->build(\VuFind\Mailer\Mailer::class);
                    $mailer->setMaxRecipients(0);
                    $mailer->send(
                        $email, $this->fromEmail, $messageSubject, $message
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
     * Collect command line arguments.
     *
     * @param array $arguments Command line arguments
     *
     * @return void
     */
    protected function collectScriptArguments($arguments)
    {
        $this->expireHours = $arguments[0];
        $this->fromEmail = $arguments[1];
        $this->reportIntervalHours = $arguments[2];
        $this->minimumPaidAge = $arguments[3] ?? 120;
    }

    /**
     * Get usage information.
     *
     * @return string
     */
    protected function usage()
    {
        // @codingStandardsIgnoreStart
        return <<<EOT
Usage:
  php index.php util online_payment_monitor <expire_hours> <from_email>
    <report_interval_hours> [minimum_paid_age]

  Validates unregistered online payment transactions.
    expire_hours          Number of hours before considering unregistered
                          transaction to be expired.
    from_email            Sender email address for notification of expired
                          transactions
    report_interval_hours Interval when to re-send report of unresolved transactions
    minimum_paid_age      Minimum age of transactions in 'paid' status until they are
                          considered failed (seconds, default 120)

EOT;
        // @codingStandardsIgnoreEnd
    }
}
