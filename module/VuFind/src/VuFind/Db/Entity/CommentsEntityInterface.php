<?php

/**
 * Entity model interface for comments table
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

/**
 * Entity model interface for comments table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface CommentsEntityInterface extends EntityInterface
{
    /**
     * Id getter
     *
     * @return int
     */
    public function getId(): int;

    /**
     * Comment setter
     *
     * @param string $comment Comment
     *
     * @return Comments
     */
    public function setComment(string $comment): CommentsEntityInterface;

    /**
     * Comment getter
     *
     * @return string
     */
    public function getComment(): string;

    /**
     * Created setter.
     *
     * @param DateTime $dateTime Created date
     *
     * @return Comments
     */
    public function setCreated(DateTime $dateTime): CommentsEntityInterface;

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): DateTime;

    /**
     * User setter.
     *
     * @param ?UserEntityInterface $user User that created comment
     *
     * @return Comments
     */
    public function setUser(?UserEntityInterface $user): CommentsEntityInterface;

    /**
     * User getter
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface;

    /**
     * Resource setter.
     *
     * @param ResourceEntityInterface $resource Resource id.
     *
     * @return Comments
     */
    public function setResource(ResourceEntityInterface $resource): CommentsEntityInterface;
}
