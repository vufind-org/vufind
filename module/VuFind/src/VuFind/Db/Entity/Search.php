<?php
/**
 * Entity model for search table
 *
 * PHP version 7
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

use Doctrine\ORM\Mapping as ORM;

/**
 * Search
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="search",
 * indexes={@ORM\Index(name="folder_id", columns={"folder_id"}),
 * @ORM\Index(name="notification_base_url",  columns={"notification_base_url"}),
 * @ORM\Index(name="notification_frequency", columns={"notification_frequency"}),
 * @ORM\Index(name="session_id",             columns={"session_id"}),
 * @ORM\Index(name="user_id",                columns={"user_id"})})
 * @ORM\Entity
 */
class Search implements EntityInterface
{
    /**
     * Unique ID.
     *
     * @var int
     *
     * @ORM\Column(name="id",
     *          type="bigint",
     *          nullable=false,
     *          options={"unsigned"=true}
     * )
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * User ID.
     *
     * @var int
     *
     * @ORM\Column(name="user_id", type="integer", nullable=false)
     */
    protected $userId = '0';

    /**
     * Session ID.
     *
     * @var string|null
     *
     * @ORM\Column(name="session_id", type="string", length=128, nullable=true)
     */
    protected $sessionId;

    /**
     * Folder ID.
     *
     * @var int|null
     *
     * @ORM\Column(name="folder_id", type="integer", nullable=true)
     */
    protected $folderId;

    /**
     * Created date.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="created",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="2000-01-01 00:00:00"}
     * )
     */
    protected $created = '2000-01-01 00:00:00';

    /**
     * Title.
     *
     * @var string|null
     *
     * @ORM\Column(name="title", type="string", length=20, nullable=true)
     */
    protected $title;

    /**
     * Saved.
     *
     * @var int
     *
     * @ORM\Column(name="saved", type="integer", nullable=false)
     */
    protected $saved = '0';

    /**
     * Search object.
     *
     * @var string|null
     *
     * @ORM\Column(name="search_object", type="blob", length=65535, nullable=true)
     */
    protected $searchObject;

    /**
     * Checksum
     *
     * @var int|null
     *
     * @ORM\Column(name="checksum", type="integer", nullable=true)
     */
    protected $checksum;

    /**
     * Notification frequency.
     *
     * @var int
     *
     * @ORM\Column(name="notification_frequency", type="integer", nullable=false)
     */
    protected $notificationFrequency = '0';

    /**
     * Date last notification is sent.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="last_notification_sent",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="2000-01-01 00:00:00"}
     * )
     */
    protected $lastNotificationSent = '2000-01-01 00:00:00';

    /**
     * Notification base URL.
     *
     * @var string
     *
     * @ORM\Column(name="notification_base_url",
     *          type="string",
     *          length=255, nullable=false
     * )
     */
    protected $notificationBaseUrl = '';
}
