<?php

/**
 * Interface for representing a comment.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\Db\Entity;

use DateTime;
use VuFind\Db\Entity\ResourceEntityInterface;

/**
 * Interface for representing a comment.
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface CommentsEntityInterface extends \VuFind\Db\Entity\CommentsEntityInterface
{
    /**
     * Resource getter.
     *
     * @return ResourceEntityInterface
     */
    public function getResource(): ResourceEntityInterface;

    /**
     * Get last update date.
     *
     * @return ?DateTime
     */
    public function getFinnaUpdated(): ?DateTime;

    /**
     * Set last update date.
     *
     * @param ?DateTime $dateTime Last updated
     *
     * @return static
     */
    public function setFinnaUpdated(?DateTime $dateTime): static;

    /**
     * Get comment visibility.
     *
     * @return bool
     */
    public function getFinnaVisible(): bool;

    /**
     * Set comment visibility.
     *
     * @param bool $visible Is the comment visible?
     *
     * @return static
     */
    public function setFinnaVisible(bool $visible): static;
}
