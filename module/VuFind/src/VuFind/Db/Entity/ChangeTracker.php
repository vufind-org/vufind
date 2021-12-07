<?php

namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ChangeTracker
 *
 * @ORM\Table(name="change_tracker", indexes={@ORM\Index(name="deleted_index", columns={"deleted"})})
 * @ORM\Entity
 */
class ChangeTracker
{
    /**
     * @var string
     *
     * @ORM\Column(name="core", type="string", length=30, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $core;

    /**
     * @var string
     *
     * @ORM\Column(name="id", type="string", length=120, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $id;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="first_indexed", type="datetime", nullable=true)
     */
    private $firstIndexed;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="last_indexed", type="datetime", nullable=true)
     */
    private $lastIndexed;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="last_record_change", type="datetime", nullable=true)
     */
    private $lastRecordChange;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="deleted", type="datetime", nullable=true)
     */
    private $deleted;


}
