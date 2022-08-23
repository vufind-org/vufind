<?php
/**
 * Entity model for feedback table
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2022.
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
use VuFind\Db\Entity\User;

/**
 * Entity model for feedback table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="feedback", indexes={@ORM\Index(name="user_id", columns={"user_id"}), @ORM\Index(name="created", columns={"created"}), @ORM\Index(name="status", columns={"status"}), @ORM\Index(name="form_name", columns={"form_name"})})
 * @ORM\Entity
 */
class Feedback implements EntityInterface
{
    /**
     * Unique ID.
     *
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * Message
     *
     * @var string
     *
     * @ORM\Column(name="message", type="text", length=0, nullable=false)
     */
    protected $message;

    /**
     * Form data
     *
     * @var string
     *
     * @ORM\Column(name="form_data", type="json", length=0, nullable=true)
     */
    protected $formData;

    /**
     * Form name
     *
     * @var string
     *
     * @ORM\Column(name="form_name", type="string", length=255, nullable=false)
     */
    protected $formName;

    /**
     * Creation date
     *
     * @var DateTime
     *
     * @ORM\Column(name="created", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    protected $created = 'CURRENT_TIMESTAMP';

    /**
     * Last update date
     *
     * @var DateTime
     *
     * @ORM\Column(name="updated", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    protected $updated = 'CURRENT_TIMESTAMP';

    /**
     * Status
     *
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=255, nullable=false, options={"default"="open"})
     */
    protected $status = 'open';

    /**
     * Site URL
     *
     * @var string
     *
     * @ORM\Column(name="site_url", type="string", length=255, nullable=false)
     */
    protected $siteUrl;

    /**
     * User that created request
     *
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="user_id", referencedColumnName="id")
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
     *   @ORM\JoinColumn(name="updated_by", referencedColumnName="id")
     * })
     */
    protected $updatedBy;

    public function setMessage(string $message): Feedback
    {
        $this->message = $message;
        return $this;
    }

    public function setFormData(string $data): Feedback
    {
        $this->formData = $data;
        return $this;
    }

    public function setFormName(string $name): Feedback
    {
        $this->formName = $name;
        return $this;
    }

    public function setCreated(DateTime $dateTime): Feedback
    {
        $this->created = $dateTime;
        return $this;
    }

    public function setUpdated(DateTime $dateTime): Feedback
    {
        $this->updated = $dateTime;
        return $this;
    }

    public function setStatus(string $status): Feedback
    {
        $this->status = $status;
        return $this;
    }

    public function setSiteUrl(string $url): Feedback
    {
        $this->siteUrl = $url;
        return $this;
    }

    public function setUser(?User $user): Feedback
    {
        $this->user = $user;
        return $this;
    }

    public function setUpdatedBy(?User $user): Feedback
    {
        $this->updatedBy = $user;
        return $this;
    }
}
