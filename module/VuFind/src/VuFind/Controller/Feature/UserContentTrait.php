<?php

/**
 * User Content trait
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
 * @package  Controller
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller\Feature;

use Laminas\Mvc\Controller\Plugin\Params;

use function in_array;

/**
 * User Content trait
 *
 * @category VuFind
 * @package  Controller
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait UserContentTrait
{
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
    public function getUserContentRecordTitles(\Laminas\Paginator\Paginator $contents)
    {
        $recordLoader = $this->serviceLocator->get(\VuFind\Record\Loader::class);
        $ids = array_map(
            fn ($content) => $content['source'] . '|' . $content['record_id'],
            iterator_to_array($contents)
        );
        $records = $recordLoader->loadBatch($ids, true);
        foreach ($contents as $i => &$c) {
            $c['recordTitle'] = $records[$i]->getTitle() ?? '';
        }
        return $contents;
    }

    /**
     * Get paging parameters from query parameters.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array
     */
    public function getPagingParams(Params $params): array
    {
        $result = [];
        $result['limit'] = $this->getService(\VuFind\Config\AccountCapabilities::class)->getUserContentPageSize();
        $page = (int)$params->fromQuery('page', 1);
        $result['page'] = $page < 1 ? 1 : $page;
        $sort = $params->fromQuery('sort', '');
        $result['sort'] = in_array($sort, array_keys($this->sortList))
            ? $sort
            : array_keys($this->sortList)[0];
        return $result;
    }
}
