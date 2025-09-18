<?php

/**
 * Developer helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  View_Helpers
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Developer helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */
class Developer extends AbstractHelper
{
    /**
     * Constructor
     *
     * @param ?UserEntityInterface $user Current logged in user or null
     */
    public function __construct(
        protected ?UserEntityInterface $user = null
    ) {
    }

    /**
     * Invoke
     *
     * @param ?UserEntityInterface $user Override user
     *
     * @return static
     */
    public function __invoke(?UserEntityInterface $user = null): static
    {
        if ($user !== null) {
            $this->user = $user;
        }
        return $this;
    }

    /**
     * Are developer settings enabled? This includes in example: API keys
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->getView()->plugin('config')->apiKeysEnabled();
    }
}
