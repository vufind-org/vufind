<?php

/**
 * Interface for representing a transaction event.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\Db\Entity;

use DateTime;
use VuFind\Db\Entity\EntityInterface;

/**
 * Interface for representing a transaction.
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface FinnaTransactionEventEntityInterface extends EntityInterface
{
    /**
     * Transaction setter
     *
     * @param FinnaTransactionEntityInterface $transaction Transaction
     *
     * @return static
     */
    public function setTransaction(FinnaTransactionEntityInterface $transaction): static;

    /**
     * Transaction getter
     *
     * @return FinnaTransactionEntityInterface
     */
    public function getTransaction(): FinnaTransactionEntityInterface;

    /**
     * Date setter
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setDate(DateTime $dateTime): static;

    /**
     * Date getter
     *
     * @return DateTime
     */
    public function getDate(): Datetime;

    /**
     * Server IP address setter
     *
     * @param ?string $serverIp Server IP address
     *
     * @return static
     */
    public function setServerIp(?string $serverIp): static;

    /**
     * Server IP address getter
     *
     * @return ?string
     */
    public function getServerIp(): ?string;

    /**
     * Server name setter
     *
     * @param ?string $serverName Server name
     *
     * @return static
     */
    public function setServerName(?string $serverName): static;

    /**
     * Server name getter
     *
     * @return ?string
     */
    public function getServerName(): ?string;

    /**
     * Request URI setter
     *
     * @param ?string $requestUri Request URI
     *
     * @return static
     */
    public function setRequestUri(?string $requestUri): static;

    /**
     * Request URI getter
     *
     * @return ?string
     */
    public function getRequestUri(): ?string;

    /**
     * Message setter
     *
     * @param ?string $message Message
     *
     * @return static
     */
    public function setMessage(?string $message): static;

    /**
     * Message getter
     *
     * @return ?string
     */
    public function getMessage(): ?string;

    /**
     * Additional Data setter
     *
     * @param ?string $data Data
     *
     * @return static
     */
    public function setData(?string $data): static;

    /**
     * Additional data getter
     *
     * @return ?string
     */
    public function getData(): ?string;
}
