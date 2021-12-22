<?php
namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Shortlinks
 *
 * @ORM\Table(name="shortlinks", uniqueConstraints={@ORM\UniqueConstraint(name="shortlinks_hash_IDX", columns={"hash"})})
 * @ORM\Entity
 */
class Shortlinks
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
     * @var string
     *
     * @ORM\Column(name="path", type="text", length=16777215, nullable=false)
     */
    private $path;

    /**
     * @var string|null
     *
     * @ORM\Column(name="hash", type="string", length=32, nullable=true)
     */
    private $hash;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $created = 'CURRENT_TIMESTAMP';
}
