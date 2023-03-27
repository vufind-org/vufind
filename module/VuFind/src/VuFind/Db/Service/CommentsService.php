<?php
/**
 * Database service for Comments.
 *
 * PHP version 7
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
namespace VuFind\Db\Service;

use VuFind\Db\Entity\Comments;

/**
 * Database service for Comments.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class CommentsService extends AbstractService
{
    /**
     * Get statistics on use of comments.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $dql = "SELECT COUNT(DISTINCT(c.user)) AS users, "
            . "COUNT(DISTINCT(c.resource)) AS resources, "
            . "COUNT(c.id) AS total "
            . "FROM " . $this->getEntityClass(Comments::class) . " c";
        $query = $this->entityManager->createQuery($dql);
        $stats = current($query->getResult());
        return $stats;
    }
}
