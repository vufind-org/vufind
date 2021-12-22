<?php
namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Session
 *
 * @ORM\Table(name="session", uniqueConstraints={@ORM\UniqueConstraint(name="session_id", columns={"session_id"})}, indexes={@ORM\Index(name="last_used", columns={"last_used"})})
 * @ORM\Entity
 */
class Session
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
     * @var string|null
     *
     * @ORM\Column(name="session_id", type="string", length=128, nullable=true)
     */
    private $sessionId;

    /**
     * @var string|null
     *
     * @ORM\Column(name="data", type="text", length=16777215, nullable=true)
     */
    private $data;

    /**
     * @var int
     *
     * @ORM\Column(name="last_used", type="integer", nullable=false)
     */
    private $lastUsed = '0';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime", nullable=false, options={"default"="2000-01-01 00:00:00"})
     */
    private $created = '2000-01-01 00:00:00';
}
