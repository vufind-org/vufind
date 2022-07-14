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
 * @ORM\Table(name="feedback", indexes={@ORM\Index(name="user_id", columns={"user_id"}), @ORM\Index(name="created", columns={"created"}), @ORM\Index(name="status", columns={"status"}), @ORM\Index(name="form_name", columns={"form_name"})})
 * @ORM\Entity
 */
class Feedback implements EntityInterface
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="int", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="message", type="text", length=0, nullable=false)
     */
    protected $message;

    /**
     * @var string
     *
     * @ORM\Column(name="form_data", type="json", length=0, nullable=true)
     */
    protected $formData;

    /**
     * @var string
     *
     * @ORM\Column(name="form_name", type="string", length=255, nullable=false)
     */
    protected $formName;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    protected $created = 'CURRENT_TIMESTAMP';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    protected $updated = 'CURRENT_TIMESTAMP';

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=255, nullable=false, options={"default"="open"})
     */
    protected $status = 'open';

    /**
     * @var string
     *
     * @ORM\Column(name="site_url", type="string", length=255, nullable=false)
     */
    protected $siteUrl;

    /**
     * @var \VuFind\Db\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     * })
     */
    protected $user;

    /**
     * @var \VuFind\Db\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="updated_by", referencedColumnName="id")
     * })
     */
    protected $updatedBy;
}
