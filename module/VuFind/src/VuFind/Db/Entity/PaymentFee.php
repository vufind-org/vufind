<?php

/**
 * Entity model for payment_fee table
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

declare(strict_types=1);

namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Entity model for payment_fee table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
#[ORM\Table(name: 'payment_fee')]
#[ORM\Index(name: 'payment_fee_payment_id_idx', columns: ['payment_id'])]
#[ORM\Entity]
class PaymentFee implements PaymentFeeEntityInterface
{
    /**
     * Unique ID.
     *
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected $id;

    /**
     * Payment.
     *
     * @var Payment
     */
    #[ORM\JoinColumn(name: 'payment_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: \VuFind\Db\Entity\Payment::class)]
    protected $payment;

    /**
     * Title.
     *
     * @var string
     */
    #[ORM\Column(name: 'title', type: 'string', length: 255, nullable: false, options: ['default' => ''])]
    protected $title;

    /**
     * Type.
     *
     * @var string
     */
    #[ORM\Column(name: 'type', type: 'string', length: 255, nullable: false, options: ['default' => ''])]
    protected $type;

    /**
     * Description.
     *
     * @var string
     */
    #[ORM\Column(name: 'description', type: 'string', length: 255, nullable: false, options: ['default' => ''])]
    protected $description;

    /**
     * Amount.
     *
     * @var int
     */
    #[ORM\Column(name: 'amount', type: 'integer', nullable: false, options: ['default' => 0])]
    protected $amount;

    /**
     * Tax Percent.
     *
     * @var int
     */
    #[ORM\Column(name: 'tax_percent', type: 'integer', nullable: false, options: ['default' => 0])]
    protected $taxPercent;

    /**
     * Currency.
     *
     * @var string
     */
    #[ORM\Column(name: 'currency', type: 'string', length: 3, nullable: false)]
    protected $currency;

    /**
     * Fine ID.
     *
     * @var string
     */
    #[ORM\Column(name: 'fine_id', type: 'string', length: 1024, nullable: false, options: ['default' => ''])]
    protected $fineId;

    /**
     * Organization.
     *
     * @var string
     */
    #[ORM\Column(name: 'organization', type: 'string', length: 255, nullable: false, options: ['default' => ''])]
    protected $organization;

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get payment.
     *
     * @return PaymentEntityInterface
     */
    public function getPayment(): PaymentEntityInterface
    {
        return $this->payment;
    }

    /**
     * Set payment.
     *
     * @param PaymentEntityInterface $payment Payment.
     *
     * @return static
     */
    public function setPayment(PaymentEntityInterface $payment): static
    {
        $this->payment = $payment;
        return $this;
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Set title.
     *
     * @param string $title Title
     *
     * @return static
     */
    public function setTitle(string $title): static
    {
        $this->title = mb_substr($title, 0, 255, 'UTF-8');
        return $this;
    }

    /**
     * Get type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set type.
     *
     * @param string $type Type
     *
     * @return static
     */
    public function setType(string $type): static
    {
        $this->type = mb_substr($type, 0, 255, 'UTF-8');
        return $this;
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Set description.
     *
     * @param string $description Description
     *
     * @return static
     */
    public function setDescription(string $description): static
    {
        $this->description = mb_substr($description, 0, 255, 'UTF-8');
        return $this;
    }

    /**
     * Get amount (in pennies, including any tax).
     *
     * @return int
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * Set amount (in pennies, including any tax).
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
     * Get tax percent (in 1/100ths of a percent).
     *
     * @return int
     */
    public function getTaxPercent(): int
    {
        return $this->taxPercent;
    }

    /**
     * Set tax percent (in 1/100ths of a percent).
     *
     * @param int $taxPercent Tax percent
     *
     * @return static
     */
    public function setTaxPercent(int $taxPercent): static
    {
        $this->taxPercent = $taxPercent;
        return $this;
    }

    /**
     * Get currency.
     *
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Set currency.
     *
     * @param string $currency Currency
     *
     * @return static
     */
    public function setCurrency(string $currency): static
    {
        $this->currency = mb_substr($currency, 0, 3, 'UTF-8');
        return $this;
    }

    /**
     * Get fine identifier.
     *
     * @return string
     */
    public function getFineId(): string
    {
        return $this->fineId ?? '';
    }

    /**
     * Set fine identifier.
     *
     * @param string $fineId Fine ID (ILS)
     *
     * @return static
     */
    public function setFineId(string $fineId): static
    {
        // No trimming - fail intentionally if fine ID is too long
        $this->fineId = $fineId;
        return $this;
    }

    /**
     * Get organization.
     *
     * @return string
     */
    public function getOrganization(): string
    {
        return $this->organization ?? '';
    }

    /**
     * Set organization.
     *
     * @param string $organization Organization
     *
     * @return static
     */
    public function setOrganization(string $organization): static
    {
        $this->organization = mb_substr($organization, 0, 255, 'UTF-8');
        return $this;
    }

    /**
     * Get amount excluding any tax (in pennies).
     *
     * @return int
     */
    public function calculateAmountExcludingTax(): int
    {
        return (int)round($this->amount / (1 + $this->getTaxPercent() / 10000.0));
    }

    /**
     * Get tax included in amount.
     *
     * @return int
     */
    public function calculateTax(): int
    {
        return $this->getAmount() - $this->calculateAmountExcludingTax();
    }
}
