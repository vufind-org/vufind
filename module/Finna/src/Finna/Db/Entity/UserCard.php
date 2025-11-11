<?php

/**
 * Entity model for user_card table
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

use Doctrine\ORM\Mapping as ORM;

/**
 * Entity model for user_card table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'user_card')]
#[ORM\Index(name: 'user_card_cat_username_idx', columns: ['cat_username'])]
#[ORM\Index(name: 'user_card_user_id_idx', columns: ['user_id'])]
#[ORM\Entity]
class UserCard extends \VuFind\Db\Entity\UserCard implements UserCardEntityInterface
{
    /**
     * Finna due date reminder frequency.
     *
     * @var int
     */
    #[ORM\Column(name: 'finna_due_date_reminder', type: 'integer', nullable: false)]
    protected int $finnaDueDateReminder = 0;

    /**
     * Barcode from profile
     *
     * @var ?string
     *
     * @todo Hacky, get rid of this?
     */
    protected ?string $barcode = null;

    /**
     * Due date reminder setting setter
     *
     * @param int $remind New due date reminder setting.
     *
     * @return static
     */
    public function setFinnaDueDateReminder(int $remind): static
    {
        $this->finnaDueDateReminder = $remind;
        return $this;
    }

    /**
     * Due date reminder setting getter
     *
     * @return int
     */
    public function getFinnaDueDateReminder(): int
    {
        return $this->finnaDueDateReminder;
    }

    /**
     * Get barcode (RUNTIME ONLY!)
     *
     * @return ?string
     */
    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    /**
     * Set barcode (RUNTIME ONLY!)
     *
     * @param ?string $barcode Barcode
     *
     * @return static
     */
    public function setBarcode(?string $barcode): static
    {
        $this->barcode = $barcode;
        return $this;
    }
}
