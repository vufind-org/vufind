<?php

/**
 * Row definition for online payment transaction event log
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023-2024.
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
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Db\Row;

use DateTime;
use Finna\Db\Entity\FinnaTransactionEntityInterface;
use Finna\Db\Entity\FinnaTransactionEventEntityInterface;

/**
 * Row definition for online payment transaction event log
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 *
 * @property int $id
 * @property int $transaction_id
 * @property string $date
 * @property string $server_ip
 * @property string $server_name
 * @property string $request_uri
 * @property string $message
 * @property string $data
 */
class TransactionEventLog extends \VuFind\Db\Row\RowGateway implements
    FinnaTransactionEventEntityInterface,
    \VuFind\Db\Service\DbServiceAwareInterface,
    \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Service\DbServiceAwareTrait;
    use \VuFind\Db\Table\DbTableAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'finna_transaction_event_log', $adapter);
    }

    /**
     * Transaction setter
     *
     * @param FinnaTransactionEntityInterface $transaction Transaction
     *
     * @return static
     */
    public function setTransaction(FinnaTransactionEntityInterface $transaction): static
    {
        $this->transaction_id = $transaction->getId();
        return $this;
    }

    /**
     * Transaction getter
     *
     * @return FinnaTransactionEntityInterface
     */
    public function getTransaction(): FinnaTransactionEntityInterface
    {
        return $this->getDbService(\Finna\Db\Service\FinnaTransactionServiceInterface::class)
            ->getTransactionById($this->transaction_id);
    }

    /**
     * Transaction Identifier setter
     *
     * @param ?string $transactionIdentifier Transaction Identifier.
     *
     * @return FinnaTransactionEventEntityInterface
     */
    public function setTransactionIdentifier(?string $transactionIdentifier): FinnaTransactionEventEntityInterface
    {
        $this->transaction_id = $transactionIdentifier;
        return $this;
    }

    /**
     * Transaction Identifier getter
     *
     * @return ?string
     */
    public function getTransactionIdentifier(): ?string
    {
        return $this->transaction_id;
    }

    /**
     * Date setter
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setDate(DateTime $dateTime): static
    {
        $this->date = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Date getter
     *
     * @return DateTime
     */
    public function getDate(): Datetime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->date);
    }

    /**
     * Server IP address setter
     *
     * @param ?string $serverIp Server IP address
     *
     * @return static
     */
    public function setServerIp(?string $serverIp): static
    {
        $this->server_ip = $serverIp;
        return $this;
    }

    /**
     * Server IP address getter
     *
     * @return ?string
     */
    public function getServerIp(): ?string
    {
        return $this->server_ip;
    }

    /**
     * Server name setter
     *
     * @param ?string $serverName Server name
     *
     * @return static
     */
    public function setServerName(?string $serverName): static
    {
        $this->server_name = $serverName;
        return $this;
    }

    /**
     * Server name getter
     *
     * @return ?string
     */
    public function getServerName(): ?string
    {
        return $this->server_name;
    }

    /**
     * Request URI setter
     *
     * @param ?string $requestUri Request URI
     *
     * @return static
     */
    public function setRequestUri(?string $requestUri): static
    {
        $this->request_uri = $requestUri;
        return $this;
    }

    /**
     * Request URI getter
     *
     * @return ?string
     */
    public function getRequestUri(): ?string
    {
        return $this->request_uri;
    }

    /**
     * Message setter
     *
     * @param ?string $message Message
     *
     * @return static
     */
    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Message getter
     *
     * @return ?string
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Additional Data setter
     *
     * @param ?string $data Data
     *
     * @return static
     */
    public function setData(?string $data): static
    {
        // Avoid messing with $this->data that RowGateway uses for storing the values:
        $this->offsetSet('data', $data);
        return $this;
    }

    /**
     * Additional data getter
     *
     * @return ?string
     */
    public function getData(): ?string
    {
        // Avoid messing with $this->data that RowGateway uses for storing the values:
        return $this->offsetGet('data');
    }
}
