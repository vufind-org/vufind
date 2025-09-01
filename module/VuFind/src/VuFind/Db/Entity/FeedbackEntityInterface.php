<?php

/**
 * Entity model interface for feedback table
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

use DateTime;

/**
 * Entity model interface for feedback table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface FeedbackEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Message setter
     *
     * @param string $message Message
     *
     * @return static
     */
    public function setMessage(string $message): static;

    /**
     * Message getter
     *
     * @return string
     */
    public function getMessage(): string;

    /**
     * Form data setter.
     *
     * @param ?array $data Form data
     *
     * @return static
     */
    public function setFormData(?array $data): static;

    /**
     * Form data getter
     *
     * @return ?array
     */
    public function getFormData(): ?array;

    /**
     * Form name setter.
     *
     * @param string $name Form name
     *
     * @return static
     */
    public function setFormName(string $name): static;

    /**
     * Form name getter
     *
     * @return string
     */
    public function getFormName(): string;

    /**
     * Created setter.
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static;

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): DateTime;

    /**
     * Updated setter.
     *
     * @param DateTime $dateTime Last update date
     *
     * @return static
     */
    public function setUpdated(DateTime $dateTime): static;

    /**
     * Updated getter
     *
     * @return DateTime
     */
    public function getUpdated(): DateTime;

    /**
     * Status setter.
     *
     * @param string $status Status
     *
     * @return static
     */
    public function setStatus(string $status): static;

    /**
     * Status getter
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Site URL setter.
     *
     * @param string $url Site URL
     *
     * @return static
     */
    public function setSiteUrl(string $url): static;

    /**
     * Site URL getter
     *
     * @return string
     */
    public function getSiteUrl(): string;

    /**
     * User setter.
     *
     * @param ?UserEntityInterface $user User that created request
     *
     * @return static
     */
    public function setUser(?UserEntityInterface $user): static;

    /**
     * User getter
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface;

    /**
     * Updatedby setter.
     *
     * @param ?UserEntityInterface $user User that updated request
     *
     * @return static
     */
    public function setUpdatedBy(?UserEntityInterface $user): static;

    /**
     * Updatedby getter
     *
     * @return ?UserEntityInterface
     */
    public function getUpdatedBy(): ?UserEntityInterface;
}
