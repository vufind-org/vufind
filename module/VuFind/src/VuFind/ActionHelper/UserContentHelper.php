<?php

/**
 * Action helper for user-created content functionality.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025-2026.
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
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\ActionHelper;

use Laminas\Paginator\Paginator;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Config\AccountCapabilities;
use VuFind\Record\Loader as RecordLoader;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Action helper for user content functionality.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class UserContentHelper implements HelperInterface
{
    /**
     * Constructor.
     *
     * @param RecordLoader        $recordLoader        Record loader
     * @param AccountCapabilities $accountCapabilities Account capabilities helper
     */
    #[Autowire]
    public function __construct(
        protected RecordLoader $recordLoader,
        protected AccountCapabilities $accountCapabilities,
    ) {
    }

    /**
     * Get sort list for user content.
     *
     * @param array  $options Array of sort options
     * @param string $active  Currently active sort
     *
     * @return array
     */
    public function getSortList(array $options, string $active): array
    {
        $sortList = [];
        foreach ($options as $key => $value) {
            $sortList[$key] = [
                'desc' => $value,
                'url' => '?sort=' . urlencode($key),
                'selected' => $active == $key,
            ];
        }
        return $sortList;
    }

    /**
     * Get record titles for user content.
     *
     * @param Paginator $contents User content
     *
     * @return Paginator
     */
    public function getUserContentRecordTitles(Paginator $contents): Paginator
    {
        // Clone the object to avoid modifying the original data as a side-effect:
        $contents = clone $contents;
        $ids = array_map(
            fn (array $content) => $content['source'] . '|' . $content['record_id'],
            iterator_to_array($contents)
        );
        $records = $this->recordLoader->loadBatch($ids, true);
        foreach ($contents as $i => &$c) {
            $c['recordTitle'] = $records[$i]->getTitle() ?? '';
        }
        return $contents;
    }

    /**
     * Get paging parameters from query parameters.
     *
     * @param ServerRequestInterface $request  Request
     * @param array                  $sortList Allowed sort options
     *
     * @return array
     */
    public function getPagingParams(ServerRequestInterface $request, array $sortList): array
    {
        $queryParams = $request->getQueryParams();
        $sort = $queryParams['sort'] ?? '';
        if (!isset($sortList[$sort])) {
            $sort = array_keys($sortList)[0] ?? '';
        }
        return [
            'page' => max(1, (int)($queryParams['page'] ?? 1)),
            'limit' => $this->accountCapabilities->getUserContentPageSize(),
            'sort' => $sort,
        ];
    }

    /**
     * Are comments enabled?
     *
     * @return bool
     */
    public function commentsEnabled(): bool
    {
        return $this->accountCapabilities->getCommentSetting() !== 'disabled';
    }

    /**
     * Are ratings enabled?
     *
     * @return bool
     */
    public function ratingsEnabled(): bool
    {
        return $this->accountCapabilities->getRatingSetting() !== 'disabled';
    }

    /**
     * Can ratings be removed?
     *
     * @return bool
     */
    public function isRatingRemovalAllowed(): bool
    {
        return $this->accountCapabilities->isRatingRemovalAllowed();
    }

    /**
     * Are lists enabled?
     *
     * @return bool
     */
    public function listsEnabled(): bool
    {
        return $this->accountCapabilities->getListSetting() !== 'disabled';
    }

    /**
     * Are tags enabled?
     *
     * @return bool
     */
    public function tagsEnabled(): bool
    {
        return $this->accountCapabilities->getTagSetting() !== 'disabled';
    }
}
