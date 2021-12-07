<?php

namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UserResource
 *
 * @ORM\Table(name="user_resource", indexes={@ORM\Index(name="list_id", columns={"list_id"}), @ORM\Index(name="resource_id", columns={"resource_id"}), @ORM\Index(name="user_id", columns={"user_id"})})
 * @ORM\Entity
 */
class UserResource
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
     * @ORM\Column(name="notes", type="text", length=65535, nullable=true)
     */
    private $notes;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="saved", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $saved = 'CURRENT_TIMESTAMP';

    /**
     * @var \VuFind\Db\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     * })
     */
    private $user;

    /**
     * @var \VuFind\Db\Entity\Resource
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\Resource")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="resource_id", referencedColumnName="id")
     * })
     */
    private $resource;

    /**
     * @var \VuFind\Db\Entity\UserList
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\UserList")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="list_id", referencedColumnName="id")
     * })
     */
    private $list;


}
