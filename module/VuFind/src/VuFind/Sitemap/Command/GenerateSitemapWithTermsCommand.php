<?php
/**
 * Command to generate a sitemap from a backend using terms (if supported).
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFind\Sitemap\Command;

use VuFindSearch\Backend\Solr\Backend;

/**
 * Command to generate a sitemap from a backend using terms (if supported).
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GenerateSitemapWithTermsCommand extends AbstractGenerateSitemapCommand
{
    /**
     * Get the initial offset to seed the search process
     *
     * @return string
     */
    protected function getInitialOffset(): string
    {
        return '';
    }

    /**
     * Set up the backend.
     *
     * @param Backend $backend Search backend
     *
     * @return void
     */
    protected function setupBackend(Backend $backend): void
    {
        // No special action needed.
    }

    /**
     * Retrieve a batch of IDs.
     *
     * @param Backend $backend      Search backend
     * @param string  $lastTerm     String representing progress through set
     * @param int     $countPerPage Page size
     *
     * @return array
     */
    protected function getIdsFromBackend(
        Backend $backend,
        string $lastTerm,
        int $countPerPage
    ): array {
        $key = $backend->getConnector()->getUniqueKey();
        $info = $backend->terms($key, $lastTerm, $countPerPage)
            ->getFieldTerms($key);
        $ids = null === $info ? [] : array_keys($info->toArray());
        $nextOffset = empty($ids) ? null : $ids[count($ids) - 1];
        return compact('ids', 'nextOffset');
    }
}
