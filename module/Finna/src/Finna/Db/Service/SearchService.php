<?php

/**
 * Database service for search.
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
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Service;

use VuFind\Db\Entity\SearchEntityInterface;

/**
 * Database service for search.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class SearchService extends \VuFind\Db\Service\SearchService implements SearchServiceInterface
{
    /**
     * Get distinct notification base URLs with scheduled alerts.
     *
     * @return array URLs
     */
    public function getScheduledNotificationBaseUrls(): array
    {
        $dql = 'SELECT DISTINCT s.notificationBaseUrl as url FROM ' . SearchEntityInterface::class
            . " s WHERE s.notificationBaseUrl != '' AND s.notificationFrequency > 0";

        $query = $this->entityManager->createQuery($dql);
        return $query->getSingleColumnResult();
    }

    /**
     * Get scheduled searches by notification base URL.
     *
     * @param string $notificationBaseUrl Notification base URL
     *
     * @return SearchEntityInterface[]
     */
    public function getScheduledSearchesByBaseUrl(string $notificationBaseUrl): array
    {
        $dql = 'SELECT s FROM ' . SearchEntityInterface::class
            . ' WHERE s.notificationBaseUrl = :notificationBaseUrl AND s.notificationFrequency > 0 AND s.saved = 1'
            . ' ORDER BY s.userId';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('notificationBaseUrl'));
        return $query->getResult();
    }
}
