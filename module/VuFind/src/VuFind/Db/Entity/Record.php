<?php
namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Record
 *
 * @ORM\Table(name="record", uniqueConstraints={@ORM\UniqueConstraint(name="record_id_source", columns={"record_id", "source"})})
 * @ORM\Entity
 */
class Record
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
     * @ORM\Column(name="record_id", type="string", length=255, nullable=true)
     */
    private $recordId;

    /**
     * @var string|null
     *
     * @ORM\Column(name="source", type="string", length=50, nullable=true)
     */
    private $source;

    /**
     * @var string
     *
     * @ORM\Column(name="version", type="string", length=20, nullable=false)
     */
    private $version;

    /**
     * @var string|null
     *
     * @ORM\Column(name="data", type="text", length=0, nullable=true)
     */
    private $data;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated", type="datetime", nullable=false, options={"default"="2000-01-01 00:00:00"})
     */
    private $updated = '2000-01-01 00:00:00';
}
