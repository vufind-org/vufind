<?php

/**
 * Entity model for oai_resumption table
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * OaiResumption
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="oai_resumption")
 * @ORM\Entity
 */
class OaiResumption implements EntityInterface
{
    /**
     * Unique ID.
     *
     * @var int
     *
     * @ORM\Column(name="id",
     *          type="integer",
     *          nullable=false
     * )
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * Resumption parameters.
     *
     * @var ?string
     *
     * @ORM\Column(name="params", type="text", length=65535, nullable=true)
     */
    protected $params;

    /**
     * Expiry date.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="expires",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="2000-01-01 00:00:00"}
     * )
     */
    protected $expires = '2000-01-01 00:00:00';

    /**
     * Id getter
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Resumption parameters setter
     *
     * @param ?string $params Resumption parameters.
     *
     * @return OaiResumption
     */
    public function setResumptionParameters(?string $params): OaiResumption
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Get resumption parameters.
     *
     * @return ?string
     */
    public function getResumptionParameters(): ?string
    {
        return $this->params;
    }

    /**
     * Expiry date setter.
     *
     * @param Datetime $dateTime Expiration date
     *
     * @return OaiResumption
     */
    public function setExpiry(DateTime $dateTime): OaiResumption
    {
        $this->expires = $dateTime;
        return $this;
    }

    /**
     * Get expiry date.
     *
     * @return DateTime
     */
    public function getExpiry(): DateTime
    {
        return $this->expires;
    }
}
