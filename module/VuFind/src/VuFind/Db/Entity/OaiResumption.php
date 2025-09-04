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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use VuFind\Db\Feature\DateTimeTrait;

/**
 * OaiResumption
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'oai_resumption')]
#[ORM\UniqueConstraint(name: 'oai_resumption_token_idx', columns: ['token'])]
#[ORM\Entity]
class OaiResumption implements OaiResumptionEntityInterface
{
    use DateTimeTrait;

    /**
     * Unique ID.
     *
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected int $id;

    /**
     * Resumption parameters.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'params', type: 'text', length: 65535, nullable: true)]
    protected ?string $params = null;

    /**
     * Expiry date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'expires', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected DateTime $expires;

    /**
     * Token.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'token', type: 'string', length: 255, nullable: true)]
    protected ?string $token = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Set the default value as a DateTime object
        $this->expires = $this->getUnassignedDefaultDateTime();
    }

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Resumption parameters setter
     *
     * @param ?string $params Resumption parameters.
     *
     * @return static
     */
    public function setResumptionParameters(?string $params): static
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
     * Set token used for identifying.
     *
     * @param string $token Generated token.
     *
     * @return static
     */
    public function setToken(string $token): static
    {
        $this->token = $token;
        return $this;
    }

    /**
     * Get token used for identifying.
     *
     * @return ?string
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * Expiry date setter.
     *
     * @param DateTime $dateTime Expiration date
     *
     * @return static
     */
    public function setExpiry(DateTime $dateTime): static
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
