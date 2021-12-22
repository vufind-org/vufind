<?php
namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * AuthHash
 *
 * @ORM\Table(name="auth_hash", uniqueConstraints={@ORM\UniqueConstraint(name="hash_type", columns={"hash", "type"})}, indexes={@ORM\Index(name="created", columns={"created"}), @ORM\Index(name="session_id", columns={"session_id"})})
 * @ORM\Entity
 */
class AuthHash
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
     * @var string
     *
     * @ORM\Column(name="hash", type="string", length=255, nullable=false)
     */
    private $hash = '';

    /**
     * @var string|null
     *
     * @ORM\Column(name="type", type="string", length=50, nullable=true)
     */
    private $type;

    /**
     * @var string|null
     *
     * @ORM\Column(name="data", type="text", length=16777215, nullable=true)
     */
    private $data;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $created = 'CURRENT_TIMESTAMP';
}
