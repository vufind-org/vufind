<?php

namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UserCard
 *
 * @ORM\Table(name="user_card", indexes={@ORM\Index(name="user_card_cat_username", columns={"cat_username"}), @ORM\Index(name="user_id", columns={"user_id"})})
 * @ORM\Entity
 */
class UserCard
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
     * @ORM\Column(name="card_name", type="string", length=255, nullable=false)
     */
    private $cardName = '';

    /**
     * @var string
     *
     * @ORM\Column(name="cat_username", type="string", length=50, nullable=false)
     */
    private $catUsername = '';

    /**
     * @var string|null
     *
     * @ORM\Column(name="cat_password", type="string", length=70, nullable=true)
     */
    private $catPassword;

    /**
     * @var string|null
     *
     * @ORM\Column(name="cat_pass_enc", type="string", length=255, nullable=true)
     */
    private $catPassEnc;

    /**
     * @var string
     *
     * @ORM\Column(name="home_library", type="string", length=100, nullable=false)
     */
    private $homeLibrary = '';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime", nullable=false, options={"default"="2000-01-01 00:00:00"})
     */
    private $created = '2000-01-01 00:00:00';

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


}
