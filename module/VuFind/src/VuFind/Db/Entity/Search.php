<?php
namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Search
 *
 * @ORM\Table(name="search", indexes={@ORM\Index(name="folder_id", columns={"folder_id"}), @ORM\Index(name="notification_base_url", columns={"notification_base_url"}), @ORM\Index(name="notification_frequency", columns={"notification_frequency"}), @ORM\Index(name="session_id", columns={"session_id"}), @ORM\Index(name="user_id", columns={"user_id"})})
 * @ORM\Entity
 */
class Search
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="bigint", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="user_id", type="integer", nullable=false)
     */
    private $userId = '0';

    /**
     * @var string|null
     *
     * @ORM\Column(name="session_id", type="string", length=128, nullable=true)
     */
    private $sessionId;

    /**
     * @var int|null
     *
     * @ORM\Column(name="folder_id", type="integer", nullable=true)
     */
    private $folderId;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime", nullable=false, options={"default"="2000-01-01 00:00:00"})
     */
    private $created = '2000-01-01 00:00:00';

    /**
     * @var string|null
     *
     * @ORM\Column(name="title", type="string", length=20, nullable=true)
     */
    private $title;

    /**
     * @var int
     *
     * @ORM\Column(name="saved", type="integer", nullable=false)
     */
    private $saved = '0';

    /**
     * @var string|null
     *
     * @ORM\Column(name="search_object", type="blob", length=65535, nullable=true)
     */
    private $searchObject;

    /**
     * @var int|null
     *
     * @ORM\Column(name="checksum", type="integer", nullable=true)
     */
    private $checksum;

    /**
     * @var int
     *
     * @ORM\Column(name="notification_frequency", type="integer", nullable=false)
     */
    private $notificationFrequency = '0';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="last_notification_sent", type="datetime", nullable=false, options={"default"="2000-01-01 00:00:00"})
     */
    private $lastNotificationSent = '2000-01-01 00:00:00';

    /**
     * @var string
     *
     * @ORM\Column(name="notification_base_url", type="string", length=255, nullable=false)
     */
    private $notificationBaseUrl = '';
}
