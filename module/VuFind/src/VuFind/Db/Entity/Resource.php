<?php

namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Resource
 *
 * @ORM\Table(name="resource", indexes={@ORM\Index(name="record_id", columns={"record_id"})})
 * @ORM\Entity
 */
class Resource
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
     * @ORM\Column(name="record_id", type="string", length=255, nullable=false)
     */
    private $recordId = '';

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=255, nullable=false)
     */
    private $title = '';

    /**
     * @var string|null
     *
     * @ORM\Column(name="author", type="string", length=255, nullable=true)
     */
    private $author;

    /**
     * @var int|null
     *
     * @ORM\Column(name="year", type="integer", nullable=true)
     */
    private $year;

    /**
     * @var string
     *
     * @ORM\Column(name="source", type="string", length=50, nullable=false, options={"default"="Solr"})
     */
    private $source = 'Solr';

    /**
     * @var string|null
     *
     * @ORM\Column(name="extra_metadata", type="text", length=16777215, nullable=true)
     */
    private $extraMetadata;


}
