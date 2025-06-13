<?php

/**
 * Row definition for online payment transaction
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2024.
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
use Finna\Db\Type\FinnaTransactionStatus;
use VuFind\Db\Entity\UserEntityInterface;

use function in_array;

/**
 * Row definition for online payment transaction
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 *
 * @property int $id
 * @property string $transaction_id
 * @property int $user_id
 * @property string $driver
 * @property int $amount
 * @property string $currency
 * @property int $transaction_fee
 * @property string $created
 * @property string $paid
 * @property string $registration_started
 * @property string $registered
 * @property int $complete
 * @property string $status
 * @property string $cat_username
 * @property string $reported
 */
class Transaction extends \VuFind\Db\Row\RowGateway implements
    FinnaTransactionEntityInterface,
    \VuFind\Db\Service\DbServiceAwareInterface,
    \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Service\DbServiceAwareTrait;
    use \VuFind\Db\Table\DbTableAwareTrait;

    public const NO_DATE = '2000-01-01 00:00:00';

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'finna_transaction', $adapter);
    }

    /**
     * Id getter
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Transaction Identifier setter
     *
     * @param ?string $transactionIdentifier Transaction Identifier.
     *
     * @return static
     */
    public function setTransactionIdentifier(?string $transactionIdentifier): static
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
     * Set user.
     *
     * @param UserEntityInterface $user User owning the list.
     *
     * @return static
     */
    public function setUser(UserEntityInterface $user): static
    {
        $this->user_id = $user->getId();
        return $this;
    }

    /**
     * Get user.
     *
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface
    {
        return $this->getDbService(\VuFind\Db\Service\UserServiceInterface::class)->getUserById($this->user_id);
    }

    /**
     * Get user id
     *
     * @return int
     */
    public function getUserId(): int
    {
        return $this->user_id;
    }

    /**
     * Source Id (driver) setter
     *
     * @param string $sourceId Source Id
     *
     * @return static
     */
    public function setSourceId(string $sourceId): static
    {
        $this->driver = $sourceId;
        return $this;
    }

    /**
     * Source Id (driver) getter
     *
     * @return string
     */
    public function getSourceId(): string
    {
        return $this->driver;
    }

    /**
     * Amount setter
     *
     * @param int $amount Amount
     *
     * @return static
     */
    public function setAmount(int $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * Amount getter
     *
     * @return int
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * Currency setter
     *
     * @param string $currency Currency.
     *
     * @return static
     */
    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * Currency getter
     *
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Transaction fee setter
     *
     * @param int $amount Amount
     *
     * @return static
     */
    public function setTransactionFee(int $amount): static
    {
        $this->transaction_fee = $amount;
        return $this;
    }

    /**
     * Transaction fee getter
     *
     * @return int
     */
    public function getTransactionFee(): int
    {
        return $this->transaction_fee;
    }

    /**
     * Created setter
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static
    {
        $this->created = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): Datetime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->created);
    }

    /**
     * Paid date setter
     *
     * @param ?DateTime $dateTime Paid date
     *
     * @return static
     */
    public function setPaidDate(?DateTime $dateTime): static
    {
        $this->paid = $dateTime ? $dateTime->format('Y-m-d H:i:s') : static::NO_DATE;
        return $this;
    }

    /**
     * Paid date getter
     *
     * @return DateTime
     */
    public function getPaidDate(): ?Datetime
    {
        return $this->paid !== static::NO_DATE ? DateTime::createFromFormat('Y-m-d H:i:s', $this->paid) : null;
    }

    /**
     * Registration started setter
     *
     * @param ?DateTime $dateTime Registration start date
     *
     * @return static
     */
    public function setRegistrationStartDate(?DateTime $dateTime): static
    {
        $this->registration_started = $dateTime ? $dateTime->format('Y-m-d H:i:s') : static::NO_DATE;
        return $this;
    }

    /**
     * Registration started getter
     *
     * @return ?DateTime
     */
    public function getRegistrationStartDate(): ?Datetime
    {
        return $this->registration_started !== static::NO_DATE
            ? DateTime::createFromFormat('Y-m-d H:i:s', $this->registration_started) : null;
    }

    /**
     * Registration date setter
     *
     * @param ?DateTime $dateTime Registration date
     *
     * @return static
     */
    public function setRegistrationDate(?DateTime $dateTime): static
    {
        $this->registered = $dateTime ? $dateTime->format('Y-m-d H:i:s') : static::NO_DATE;
        return $this;
    }

    /**
     * Registration date getter
     *
     * @return ?DateTime
     */
    public function getRegistrationDate(): ?Datetime
    {
        return $this->registered !== static::NO_DATE
            ? DateTime::createFromFormat('Y-m-d H:i:s', $this->registered) : null;
    }

    /**
     * Status setter
     *
     * @param FinnaTransactionStatus $status Status
     *
     * @return static
     */
    public function setStatus(FinnaTransactionStatus $status): static
    {
        $this->complete = $status->value;
        return $this;
    }

    /**
     * Status getter
     *
     * @return FinnaTransactionStatus
     */
    public function getStatus(): FinnaTransactionStatus
    {
        return FinnaTransactionStatus::from($this->complete);
    }

    /**
     * Status message setter
     *
     * @param string $description Status message
     *
     * @return static
     */
    public function setStatusMessage(string $description): static
    {
        $this->status = mb_substr($description, 0, 255, 'UTF-8');
        return $this;
    }

    /**
     * Status message getter
     *
     * @return string
     */
    public function getStatusMessage(): string
    {
        return $this->status;
    }

    /**
     * Catalog username setter
     *
     * @param string $catUsername Catalog username
     *
     * @return static
     */
    public function setCatUsername(string $catUsername): static
    {
        $this->cat_username = $catUsername;
        return $this;
    }

    /**
     * Get catalog username.
     *
     * @return string
     */
    public function getCatUsername(): string
    {
        return $this->cat_username;
    }

    /**
     * Check if the transaction is in progress
     *
     * @return bool
     */
    public function isInProgress(): bool
    {
        return $this->complete === FinnaTransactionStatus::InProgress->value;
    }

    /**
     * Check if the transaction is registered (fees marked paid in the ILS)
     *
     * @return bool
     */
    public function isRegistered(): bool
    {
        return $this->complete === FinnaTransactionStatus::Complete->value;
    }

    /**
     * Set transaction canceled
     *
     * @return static
     */
    public function setCanceled(): static
    {
        $this->complete = FinnaTransactionStatus::Canceled->value;
        $this->status = 'cancel';
        return $this;
    }

    /**
     * Check if the transaction is paid and needs registration with the ILS
     *
     * @return bool
     */
    public function needsRegistration(): bool
    {
        return in_array(
            $this->complete,
            [
                FinnaTransactionStatus::Paid->value,
                FinnaTransactionStatus::RegistrationFailed->value,
            ]
        );
    }

    /**
     * Set transaction paid
     *
     * @return static
     */
    public function setPaid(): static
    {
        $this->paid = date('Y-m-d H:i:s', time());
        $this->complete = FinnaTransactionStatus::Paid->value;
        $this->status = 'paid';
        return $this;
    }

    /**
     * Set transaction registered
     *
     * @return static
     */
    public function setRegistered(): static
    {
        $this->registered = date('Y-m-d H:i:s');
        $this->complete = FinnaTransactionStatus::Complete->value;
        $this->status = 'register_ok';
        return $this;
    }

    /**
     * Set transaction status to "registration failed"
     *
     * @param string $msg Message
     *
     * @return static
     */
    public function setRegistrationFailed(string $msg): static
    {
        $this->complete = FinnaTransactionStatus::RegistrationFailed->value;
        $this->status = mb_substr($msg, 0, 255, 'UTF-8');
        $this->registration_started = '2000-01-01 00:00:00';
        return $this;
    }

    /**
     * Set registration start timestamp
     *
     * @return static
     */
    public function setRegistrationStarted(): static
    {
        $this->registration_started = date('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Check if registration is in progress (i.e. started within 120 seconds)
     *
     * @return bool
     */
    public function isRegistrationInProgress(): bool
    {
        // Ensure fresh data:
        $transaction = $this->getDbTable('Transaction')->select(['id' => $this->id])->current();
        $startDate = $transaction->getRegistrationStartDate();
        return $startDate && (time() - $startDate->getTimestamp() < 120);
    }

    /**
     * Set transaction reported date and status to "registration expired"
     *
     * @return static
     */
    public function setReportedAndExpired(): static
    {
        $this->complete = FinnaTransactionStatus::RegistrationExpired->value;
        $this->reported = date('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Set transaction status to "fines updated"
     *
     * @return static
     */
    public function setFinesUpdated(): static
    {
        $this->complete = FinnaTransactionStatus::FinesUpdated->value;
        $this->status = 'fines_updated';
        return $this;
    }
}
