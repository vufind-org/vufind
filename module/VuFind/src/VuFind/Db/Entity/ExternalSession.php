<?php

namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ExternalSession
 *
 * @ORM\Table(name="external_session", uniqueConstraints={@ORM\UniqueConstraint(name="session_id", columns={"session_id"})}, indexes={@ORM\Index(name="external_session_id", columns={"external_session_id"})})
 * @ORM\Entity
 */
class ExternalSession
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
     * @var string
     *
     * @ORM\Column(name="session_id", type="string", length=128, nullable=false)
     */
    private $sessionId;

    /**
     * @var string
     *
     * @ORM\Column(name="external_session_id", type="string", length=255, nullable=false)
     */
    private $externalSessionId;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime", nullable=false, options={"default"="2000-01-01 00:00:00"})
     */
    private $created = '2000-01-01 00:00:00';


}
