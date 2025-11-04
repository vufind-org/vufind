<?php

/**
 * Entity model for comments table
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Feature\DateTimeTrait;

/**
 * Entity model for comments table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Entity]
#[ORM\Index(name: 'finna_visible', columns: ['finna_visible'])]
class Comments extends \VuFind\Db\Entity\Comments implements CommentsEntityInterface
{
    use DateTimeTrait;

    /**
     * Flag indicating comment visibility.
     *
     * @var bool
     */
    #[ORM\Column(name: 'finna_visible', type: 'boolean', nullable: false, options: ['default' => true])]
    protected ?bool $finnaVisible = true;

    /**
     * Last update date.
     *
     * @var DateTime
     */
    #[ORM\Column(
        name: 'finna_updated',
        type: 'datetime',
        nullable: true,
    )]
    protected ?DateTime $finnaUpdated = null;

    /**
     * Resource getter.
     *
     * @return ResourceEntityInterface
     */
    public function getResource(): ResourceEntityInterface
    {
        return $this->resource;
    }

    /**
     * Last update date getter
     *
     * @return DateTime
     */
    public function getFinnaUpdated(): ?DateTime
    {
        return $this->getNullableDateTimeFromNonNullable($this->finnaUpdated);
    }

    /**
     * Last update date setter
     *
     * @param ?DateTime $dateTime Last updated
     *
     * @return static
     */
    public function setFinnaUpdated(?DateTime $dateTime): static
    {
        $this->finnaUpdated = $this->getNonNullableDateTimeFromNullable($dateTime);
        return $this;
    }

    /**
     * Get comment visibility.
     *
     * @return bool
     */
    public function getFinnaVisible(): bool
    {
        return $this->finnaVisible;
    }

    /**
     * Set comment visibility.
     *
     * @param bool $visible Is the comment visible?
     *
     * @return static
     */
    public function setFinnaVisible(bool $visible): static
    {
        $this->finnaVisible = $visible;
        return $this;
    }
}
