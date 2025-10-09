<?php

/**
 * Entity model for feedback table
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity model for feedback table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'feedback')]
#[ORM\Index(name: 'feedback_user_id_idx', columns: ['user_id'])]
#[ORM\Index(name: 'feedback_created_idx', columns: ['created'])]
#[ORM\Index(name: 'feedback_status_idx', columns: ['status'], options: ['lengths' => [191]])]
#[ORM\Index(name: 'feedback_form_name_idx', columns: ['form_name'], options: ['lengths' => [191]])]
#[ORM\Index(name: 'feedback_updated_by_idx', columns: ['updated_by'])]
#[ORM\Entity]
class Feedback implements FeedbackEntityInterface
{
    /**
     * Unique ID.
     *
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected int $id;

    /**
     * Message
     *
     * @var string
     */
    #[ORM\Column(name: 'message', type: 'text', nullable: true)]
    protected string $message;

    /**
     * Form data
     *
     * @var ?array
     */
    #[ORM\Column(name: 'form_data', type: 'json', nullable: true)]
    protected ?array $formData = null;

    /**
     * Form name
     *
     * @var string
     */
    #[ORM\Column(name: 'form_name', type: 'string', length: 255, nullable: false)]
    protected string $formName;

    /**
     * Creation date
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'created', type: 'datetime', nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected DateTime $created;

    /**
     * Last update date
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'updated', type: 'datetime', nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected DateTime $updated;

    /**
     * Status
     *
     * @var string
     */
    #[ORM\Column(name: 'status', type: 'string', length: 255, nullable: false, options: ['default' => 'open'])]
    protected string $status = 'open';

    /**
     * Site URL
     *
     * @var string
     */
    #[ORM\Column(name: 'site_url', type: 'string', length: 255, nullable: false)]
    protected string $siteUrl;

    /**
     * User that created request
     *
     * @var ?UserEntityInterface
     */
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[ORM\ManyToOne(targetEntity: UserEntityInterface::class)]
    protected ?UserEntityInterface $user = null;

    /**
     * User that updated request
     *
     * @var ?UserEntityInterface
     */
    #[ORM\JoinColumn(name: 'updated_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[ORM\ManyToOne(targetEntity: UserEntityInterface::class)]
    protected ?UserEntityInterface $updatedBy = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Set the default value as a DateTime object
        $this->created = new Datetime();
        $this->updated = new Datetime();
    }

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
     * Message setter
     *
     * @param string $message Message
     *
     * @return static
     */
    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Message getter
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Form data setter.
     *
     * @param ?array $data Form data
     *
     * @return static
     */
    public function setFormData(?array $data): static
    {
        $this->formData = $data;
        return $this;
    }

    /**
     * Form data getter
     *
     * @return ?array
     */
    public function getFormData(): ?array
    {
        return $this->formData;
    }

    /**
     * Form name setter.
     *
     * @param string $name Form name
     *
     * @return static
     */
    public function setFormName(string $name): static
    {
        $this->formName = $name;
        return $this;
    }

    /**
     * Form name getter
     *
     * @return string
     */
    public function getFormName(): string
    {
        return $this->formName;
    }

    /**
     * Created setter.
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static
    {
        $this->created = $dateTime;
        return $this;
    }

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return $this->created;
    }

    /**
     * Updated setter.
     *
     * @param DateTime $dateTime Last update date
     *
     * @return static
     */
    public function setUpdated(DateTime $dateTime): static
    {
        $this->updated = $dateTime;
        return $this;
    }

    /**
     * Updated getter
     *
     * @return DateTime
     */
    public function getUpdated(): DateTime
    {
        return $this->updated;
    }

    /**
     * Status setter.
     *
     * @param string $status Status
     *
     * @return static
     */
    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Status getter
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Site URL setter.
     *
     * @param string $url Site URL
     *
     * @return static
     */
    public function setSiteUrl(string $url): static
    {
        $this->siteUrl = $url;
        return $this;
    }

    /**
     * Site URL getter
     *
     * @return string
     */
    public function getSiteUrl(): string
    {
        return $this->siteUrl;
    }

    /**
     * User setter.
     *
     * @param ?UserEntityInterface $user User that created request
     *
     * @return static
     */
    public function setUser(?UserEntityInterface $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * User getter
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface
    {
        return $this->user;
    }

    /**
     * Updatedby setter.
     *
     * @param ?UserEntityInterface $user User that updated request
     *
     * @return static
     */
    public function setUpdatedBy(?UserEntityInterface $user): static
    {
        $this->updatedBy = $user;
        return $this;
    }

    /**
     * Updatedby getter
     *
     * @return ?UserEntityInterface
     */
    public function getUpdatedBy(): ?UserEntityInterface
    {
        return $this->updatedBy;
    }
}
