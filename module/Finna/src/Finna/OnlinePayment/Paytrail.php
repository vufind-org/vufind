<?php
/**
 * Paytrail payment handler
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * @package  OnlinePayment
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 * @link     http://docs.paytrail.com/ Paytrial API docoumentation
 */
namespace Finna\OnlinePayment;
use Finna\Db\Row\Transaction,
    Finna\OnlinePayment\OnlinePaymentHanderInterface,
    Zend\Log\LoggerAwareInterface,
    Zend\Log\LoggerInterface;

require_once 'Paytrail_Module_Rest.php';

/**
 * Paytrail payment handler module.
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 * @link     http://docs.paytrail.com/ Paytrial API docoumentation
 */
class Paytrail implements OnlinePaymentHandlerInterface, LoggerAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait {
        getDbTable as getTable;
    }
    use \VuFind\Log\LoggerAwareTrait;

    const PAYMENT_SUCCESS = 'success';
    const PAYMENT_FAILURE = 'failure';
    const PAYMENT_NOTIFY = 'notify';

    /**
     * Configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Constructor
     *
     * @param array $config Configuration as key-value pairs.
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Return name of handler.
     *
     * @return string name
     */
    public function getName()
    {
        return 'Paytrail';
    }

    /**
     * Set logger instance
     *
     * @param LoggerInterface $logger Logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Start transaction.
     *
     * @param string $finesUrl           Return URL to MyResearch/Fines
     * @param string $ajaxUrl            Base URL for AJAX-actions
     * @param int    $userId             User ID
     * @param string $patronId           Patron's catalog username (e.g. barcode)
     * @param string $driver             Patron MultiBackend ILS source
     * @param int    $amount             Amount (excluding transaction fee)
     * @param int    $transactionFee     Transaction fee
     * @param array  $fines              Fines data
     * @param strin  $currency           Currency
     * @param string $statusParam        Payment status URL parameter
     * @param string $transactionIdParam Transaction Id URL parameter
     *
     * @return false on error, otherwise redirects to payment handler.
     */
    public function startPayment(
        $finesUrl, $ajaxUrl, $userId, $patronId, $driver, $amount, $transactionFee,
        $fines, $currency, $statusParam, $transactionIdParam
    ) {
        $orderNumber = $this->generateTransactionId($patronId);

        $successUrl
            = "{$finesUrl}?{$statusParam}=" . self::PAYMENT_SUCCESS
            . "&{$transactionIdParam}=" . urlencode($orderNumber);
        $failUrl
            = "{$finesUrl}?{$statusParam}=" . self::PAYMENT_FAILURE
            . "&{$transactionIdParam}=" . urlencode($orderNumber);
        $notifyUrl
            = "{$ajaxUrl}/paytrailNotify?{$statusParam}=" . self::PAYMENT_NOTIFY
            . "&{$transactionIdParam}=" . urlencode($orderNumber);

        $urlset
            = new Paytrail_Module_Rest_Urlset($successUrl, $failUrl, $notifyUrl, '');

        $totAmount = ($amount + $transactionFee) / 100.00;
        $payment
            = new Paytrail_Module_Rest_Payment_S1($orderNumber, $urlset, $totAmount);

        if (!$module = $this->initPaytrail()) {
            $this->logger->err('Paytrail: error starting payment processing.');
            return false;
        }

        try {
            $result = $module->processPayment($payment);
        } catch (Paytrail_Exception $e) {
            $err = 'Paytrail: error starting payment processing: '
                . $e->getMessage();
            $this->logger->err($err);
            header("Location: {$finesUrl}");
        }

        $t = $this->getTable('transaction')->createTransaction(
            $orderNumber,
            $driver,
            $userId,
            $patronId,
            $amount,
            $transactionFee,
            $currency
        );

        if (!$t) {
            $this->logger->err('Paytrail: error creating transaction');
            return false;
        }

        $feeTable = $this->getTable('fee');
        foreach ($fines as $fine) {
            if (!$feeTable->addFee($t->id, $fine, $t->user_id, $t->currency)) {
                $this->logger->err('Paytrail: error adding fee to transaction.');
                return false;
            }
        }
        header("Location: {$result->getUrl()}");
    }

    /**
     * Process the response from payment service.
     *
     * @param array $params Response variables
     *
     * @return string error message (not translated)
     *   or associative array with keys:
     *     'markFeesAsPaid' (boolean) true if payment was successful and fees
     *     should be registered as paid.
     *     'transactionId' (string) Transaction ID.
     *     'amount' (int) Amount to be registered (does not include transaction fee).
     */
    public function processResponse($params)
    {
        $status = $params['payment'];
        $orderNum = $params['ORDER_NUMBER'];
        $timestamp = $params['TIMESTAMP'];

        $table = $this->getTable('transaction');

        if (!$table->isTransactionInProgress($orderNum)) {
            return 'online_payment_transaction_already_processed_or_unknown';
        }

        if (($t = $table->getTransaction($orderNum)) === false) {
            $this->logger->err(
                "Paytrail: error processing transaction $orderNum"
                . ': transaction not found'
            );
            return 'online_payment_failed';
        }

        $amount = $t->amount;
        $paid = false;
        if ($status == self::PAYMENT_SUCCESS || $status == self::PAYMENT_NOTIFY) {
            if (!$module = $this->initPaytrail()) {
                return 'online_payment_failed';
            }
            if (!$module->confirmPayment(
                $params["ORDER_NUMBER"],
                $params["TIMESTAMP"],
                $params["PAID"],
                $params["METHOD"],
                $params["RETURN_AUTHCODE"]
            )) {
                $this->logger->err(
                    'Paytrail: error processing response: invalid checksum'
                );
                $this->logger->err("   " . var_export($params, true));
                return 'online_payment_failed';
            }

            if (!$table->setTransactionPaid($orderNum, $timestamp)) {
                $this->logger->err(
                    "Paytrail: error updating transaction $orderNum to paid"
                );
            }
            $paid = true;
        } else if ($status == self::PAYMENT_FAILURE) {
            if (!$table->setTransactionCancelled($orderNum)) {
                $this->logger->err(
                    "Paytrail: error updating transaction $orderNum to cancelled"
                );
            }
            return 'online_payment_canceled';
        } else {
            $table->setTransactionUnknownPaymentResponse($orderNum, $status);
            return 'online_payment_failed';
        }

        return [
           'markFeesAsPaid' => $paid,
           'transactionId' => $orderNum,
           'amount' => $amount
        ];
    }

    /**
     * Init Paytrail module with configured merchantId, secret and URL.
     *
     * @return Paytrail_Module_Rest module.
     */
    protected function initPaytrail()
    {
        foreach (['merchantId', 'secret', 'url'] as $req) {
            if (!isset($this->config[$req])) {
                $this->logger->err("Paytrail: missing parameter $req");
                return false;
            }
        }
        return new Paytrail_Module_Rest(
            $this->config['merchantId'],
            $this->config['secret'],
            $this->config['url']
        );
    }

    /**
     * Generate the internal payment transaction identifer.
     *
     * @param string $patronId Patron's Catalog Username (barcode)
     *
     * @return string Transaction identifier
     */
    protected function generateTransactionId($patronId)
    {
        return md5($patronId . '_' . microtime(true));
    }
}
