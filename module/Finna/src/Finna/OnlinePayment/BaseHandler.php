<?php
/**
 * Payment base handler
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */
namespace Finna\OnlinePayment;
use Finna\Db\Row\Transaction,
    Finna\OnlinePayment\OnlinePaymentHanderInterface,
    Zend\Log\LoggerAwareInterface,
    Zend\Log\LoggerInterface;

/**
 * Payment base handler
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */
abstract class BaseHandler
implements OnlinePaymentHandlerInterface, LoggerAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait {
        getDbTable as getTable;
    }
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * HTTP service.
     *
     * @var \VuFind\Http
     */
    protected $http;

    /**
     * Constructor
     *
     * @param array        $config Configuration as key-value pairs.
     * @param \VuFind\Http $http   HTTP service
     */
    public function __construct($config, $http)
    {
        $this->config = $config;
        $this->http = $http;
    }

    /**
     * Return name of handler.
     *
     * @return string name
     */
    public function getName()
    {
        return $this->config->onlinePayment->handler;
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

    /**
     * Store transaction to database.
     *
     * @param string $orderNumber    ID
     * @param string $driver         Patron MultiBackend ILS source
     * @param int    $userId         User ID
     * @param string $patronId       Patron's catalog username
     * (e.g. barcode)
     * @param int    $amount         Amount
     * (excluding transaction fee)
     * @param int    $transactionFee Transaction fee
     * @param string $currency       Currency
     * @param array  $fines          Fines data
     *
     * @return boolean success
     */
    protected function createTransaction(
        $orderNumber, $driver, $userId, $patronId, $amount, $transactionFee,
        $currency, $fines
    ) {
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
            $this->logger->err($this->getName() . ': error creating transaction');
            return false;
        }

        $feeTable = $this->getTable('fee');
        foreach ($fines as $fine) {
            if (!$feeTable->addFee($t->id, $fine, $t->user_id, $t->currency)) {
                $this->logger->err(
                    $this->getName() . ': error adding fee to transaction.'
                );
                return false;
            }
        }

        return true;
    }

    /**
     * Return started transaction from database.
     *
     * @param string $id Transaction ID
     *
     * @return array Array:
     * - true|false success
     * - string|Finna\Db\Table\Row\Transaction 
     * Transaction or error message (translation key).
     */
    protected function getStartedTransaction($id)
    {
        $table = $this->getTable('transaction');
        if (!$table->isTransactionInProgress($id)) {
            return [
                false, 'online_payment_transaction_already_processed_or_unknown'
            ];
        }

        if (($t = $table->getTransaction($id)) === false) {
            $this->logger->err(
                "Error retrieving started transaction $id: transaction not found"
            );
            return [false, 'transaction_found'];
        }

        return [true, $t];
    }

    /**
     * Redirect to payment handler.
     *
     * @param string $url URL
     *
     * @return void
     */
    protected function redirectToPayment($url)
    {
        header("Location: $url", true, 302);
        exit();
    }

    /**
     * Set transaction paid.
     *
     * @param string $orderNum  Transaction ID.
     * @param string $timestamp Time stamp.
     *
     * @return void
     */
    protected function setTransactionPaid($orderNum, $timestamp = null)
    {
        if (!$timestamp) {
            $timestamp = time();
        }
        $table = $this->getTable('transaction');
        if (!$table->setTransactionPaid($orderNum, $timestamp)) {
            $this->logger->err(
                $this->getName() . ": error updating transaction $orderNum to paid"
            );
        }
    }

    /**
     * Set transaction cancelled.
     *
     * @param string $orderNum Transaction ID.
     *
     * @return void
     */
    protected function setTransactionCancelled($orderNum)
    {
        $table = $this->getTable('transaction');
        if (!$table->setTransactionCancelled($orderNum)) {
            $this->logger->err(
                $this->getName()
                . ": error updating transaction $orderNum to cancelled"
            );
        }
    }

    /**
     * Set transaction failed.
     *
     * @param string $orderNum Transaction ID.
     * @param string $msg      Message
     *
     * @return void
     */
    protected function setTransactionFailed($orderNum, $msg = null)
    {
        $table = $this->getTable('transaction');
        if (!$table->setTransactionRegistrationFailed($orderNum, $msg)) {
            $this->logger->err(
                $this->getName()
                . ": error updating transaction $orderNum to failed"
            );
        }
    }

    /**
     * Set transaction unknown response.
     *
     * @param string $orderNum Transaction ID.
     * @param string $status   Message.
     *
     * @return void
     */
    protected function setTransactionUnknownResponse($orderNum, $status)
    {
        $table = $this->getTable('transaction');
        $table->setTransactionUnknownPaymentResponse($orderNum, $status);
    }
}
