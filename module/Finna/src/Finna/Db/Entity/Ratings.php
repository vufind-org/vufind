<?php

/**
 * Entity model for ratings table
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
use VuFind\Db\Feature\DateTimeTrait;

/**
 * Entity model for ratings table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Entity]
class Ratings extends \VuFind\Db\Entity\Ratings implements RatingsEntityInterface
{
    use DateTimeTrait;

    /**
     * Last check date.
     *
     * @var DateTime
     */
    #[ORM\Column(
        name: 'finna_checked',
        type: 'datetime',
        nullable: false,
        options: ['default' => '2000-01-01 00:00:00']
    )]
    protected ?DateTime $finnaChecked;

    /**
     * Last check date getter
     *
     * @return DateTime
     */
    public function getFinnaChecked(): ?DateTime
    {
        return $this->getNullableDateTimeFromNonNullable($this->finnaChecked);
    }

    /**
     * Last check date setter
     *
     * @param ?DateTime $dateTime Last updated
     *
     * @return static
     */
    public function setFinnaChecked(?DateTime $dateTime): static
    {
        $this->finnaChecked = $this->getNonNullableDateTimeFromNullable($dateTime);
        return $this;
    }
}
