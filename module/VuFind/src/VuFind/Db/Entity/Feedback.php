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
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity model for feedback table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="feedback",
 * indexes={@ORM\Index(name="created", columns={"created"}),
 * @ORM\Index(name="status",    columns={"status"}),
 * @ORM\Index(name="form_name", columns={"form_name"})}
 * )
 * @ORM\Entity
 */
class Feedback implements FeedbackEntityInterface
{
    /**
     * Unique ID.
     *
     * @var int
     *
     * @ORM\Column(name="id",
     *          type="integer",
     *          nullable=false,
     *          options={"unsigned"=true}
     * )
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * Message
     *
     * @var string
     *
     * @ORM\Column(name="message",
     *          type="text",
     *          length=0,
     *          nullable=false
     * )
     */
    protected $message;

    /**
     * Form data
     *
     * @var mixed
     *
     * @ORM\Column(name="form_data",
     *          type="json",
     *          length=0,
     *          nullable=true
     * )
     */
    protected $formData;

    /**
     * Form name
     *
     * @var string
     *
     * @ORM\Column(name="form_name",
     *          type="string",
     *          length=255,
     *          nullable=false
     * )
     */
    protected $formName;

    /**
     * Creation date
     *
     * @var DateTime
     *
     * @ORM\Column(name="created",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="CURRENT_TIMESTAMP"}
     * )
     */
    protected $created = 'CURRENT_TIMESTAMP';

    /**
     * Last update date
     *
     * @var DateTime
     *
     * @ORM\Column(name="updated",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="CURRENT_TIMESTAMP"}
     * )
     */
    protected $updated = 'CURRENT_TIMESTAMP';

    /**
     * Status
     *
     * @var string
     *
     * @ORM\Column(name="status",
     *          type="string",
     *          length=255,
     *          nullable=false,
     *          options={"default"="open"}
     * )
     */
    protected $status = 'open';

    /**
     * Site URL
     *
     * @var string
     *
     * @ORM\Column(name="site_url",
     *          type="string",
     *          length=255,
     *          nullable=false
     * )
     */
    protected $siteUrl;

    /**
     * User that created request
     *
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\User")
     * @ORM\JoinColumns({
     * @ORM\JoinColumn(name="user_id",
     *              referencedColumnName="id")
     * })
     */
    protected $user;

    /**
     * User that updated request
     *
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\User")
     * @ORM\JoinColumns({
     * @ORM\JoinColumn(name="updated_by",
     *              referencedColumnName="id")
     * })
     */
    protected $updatedBy;

    /**
     * Id getter
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
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
     * @param mixed $data Form data
     *
     * @return static
     */
    public function setFormData(array $data): static
    {
        $this->formData = $data;
        return $this;
    }

    /**
     * Form data getter
     *
     * @return array
     */
    public function getFormData(): array
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
