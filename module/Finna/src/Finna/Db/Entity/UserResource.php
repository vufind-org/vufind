<?php

/**
 * Entity model for user_resource table
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
 * Entity model for user_resource table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'user_resource')]
#[ORM\Index(name: 'user_resource_list_id_idx', columns: ['list_id'])]
#[ORM\Index(name: 'user_resource_resource_id_idx', columns: ['resource_id'])]
#[ORM\Index(name: 'user_resource_user_id_idx', columns: ['user_id'])]
#[ORM\Entity]
class UserResource extends \VuFind\Db\Entity\UserResource implements UserResourceEntityInterface
{
    /**
     * Finna custom order index.
     *
     * @var ?int
     */
    #[ORM\Column(name: 'finna_custom_order_index', type: 'integer', nullable: true)]
    protected ?int $finnaCustomOrderIndex;

    /**
     * Custom order index setter
     *
     * @param ?int $index New due date reminder setting.
     *
     * @return static
     */
    public function setFinnaCustomOrderIndex(?int $index): static
    {
        $this->finnaCustomOrderIndex = $index;
        return $this;
    }

    /**
     * Custom order index getter
     *
     * @return ?int
     */
    public function getFinnaCustomOrderIndex(): ?int
    {
        return $this->finnaCustomOrderIndex;
    }
}
