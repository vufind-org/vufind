<?php
namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * OaiResumption
 *
 * @ORM\Table(name="oai_resumption")
 * @ORM\Entity
 */
class OaiResumption
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string|null
     *
     * @ORM\Column(name="params", type="text", length=65535, nullable=true)
     */
    private $params;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="expires", type="datetime", nullable=false, options={"default"="2000-01-01 00:00:00"})
     */
    private $expires = '2000-01-01 00:00:00';
}
