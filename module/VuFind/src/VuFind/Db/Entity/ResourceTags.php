<?php
namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ResourceTags
 *
 * @ORM\Table(name="resource_tags", indexes={@ORM\Index(name="list_id", columns={"list_id"}), @ORM\Index(name="resource_id", columns={"resource_id"}), @ORM\Index(name="tag_id", columns={"tag_id"}), @ORM\Index(name="user_id", columns={"user_id"})})
 * @ORM\Entity
 */
class ResourceTags
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
     * @var \DateTime
     *
     * @ORM\Column(name="posted", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $posted = 'CURRENT_TIMESTAMP';

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
     * @var \VuFind\Db\Entity\Tags
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\Tags")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="tag_id", referencedColumnName="id")
     * })
     */
    private $tag;

    /**
     * @var \VuFind\Db\Entity\UserList
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\UserList")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="list_id", referencedColumnName="id")
     * })
     */
    private $list;

    /**
     * @var \VuFind\Db\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     * })
     */
    private $user;
}
