<?php

/**
 * View helper for embedding a user list.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019-2024.
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\View\Helper\Root;

use Finna\Db\Entity\FinnaUserListEntityInterface;
use Laminas\Stdlib\Parameters;
use VuFind\Db\Service\TagServiceInterface;
use VuFind\Db\Service\UserListServiceInterface;

use function assert;
use function in_array;

/**
 * View helper for embedding a user list.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class UserListEmbed extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Counter used to ensure unique id attributes when several lists are displayed
     *
     * @var int
     */
    protected $indexStart = 0;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Favorites\Results $results         Results
     * @param UserListServiceInterface         $userListService User list database service
     * @param TagServiceInterface              $tagService      Tag db servce
     * @param \Laminas\View\Model\ViewModel    $viewModel       View model
     * @param bool                             $listTagsEnabled Whether list tags are enabled
     */
    public function __construct(
        protected \VuFind\Search\Favorites\Results $results,
        protected UserListServiceInterface $userListService,
        protected TagServiceInterface $tagService,
        protected \Laminas\View\Model\ViewModel $viewModel,
        protected bool $listTagsEnabled
    ) {
    }

    /**
     * Returns HTML for embedding a user list.
     *
     * @param array $opt        Options
     * @param int   $offset     Record offset
     *                          (used when loading a more results via AJAX)
     * @param int   $indexStart Result item offset in DOM
     *                          (used when loading a more results via AJAX)
     *
     * @return string
     */
    public function __invoke($opt, $offset = null, $indexStart = null)
    {
        foreach (array_keys($opt) as $key) {
            if (
                !in_array(
                    $key,
                    ['id', 'view', 'sort', 'limit', 'page',
                       'title', 'description', 'date', 'tags', 'headingLevel',
                       'allowCopy', 'showAllLink']
                )
            ) {
                unset($opt[$key]);
            }
        }

        $id = $opt['id'] ?? null;
        if (!$id) {
            return $this->error('Missing "id"');
        }

        try {
            $list = $this->userListService->getUserListById($id);
            if (!$list->isPublic()) {
                return $this->error('List is private');
            }
        } catch (\Exception $e) {
            return $this->error('Could not find list');
        }

        $loadMore = $offset !== null;

        $opt['limit'] ??= 100;

        $resultsCopy = clone $this->results;
        $params = $resultsCopy->getParams();
        $params->initFromRequest(new Parameters($opt));

        $total = $resultsCopy->getResultTotal();
        $view = $opt['view'] ?? 'list';
        if (!$loadMore) {
            $idStart = $this->indexStart;
            $this->indexStart += $total;
        } else {
            // Load more results using given $indexStart and $offset
            $idStart = $indexStart;
            $resultsCopy->overrideStartRecord($offset);
        }

        $resultsCopy->performAndProcessSearch();
        $list = $resultsCopy->getListObject();
        assert($list instanceof FinnaUserListEntityInterface);

        $listTags = null;
        if (($opt['tags'] ?? false) && $this->listTagsEnabled) {
            $listTags = $this->tagService->getListTags($list);
        }

        $html = $this->getView()->render(
            'Helpers/userlist.phtml',
            [
                'id' => $id,
                'results' => $resultsCopy,
                'params' => $params,
                'indexStart' => $idStart,
                'view' => $view,
                'total' => $total,
                'sort' => $opt['sort'] ?? null,
                'showAllLink' =>
                    ($opt['showAllLink'] ?? false)
                    && $opt['limit'] < $total,
                'title' =>
                    (isset($opt['title']) && $opt['title'] === false)
                    ? null : $list->getTitle(),
                'description' =>
                    (isset($opt['description']) && $opt['description'] === false)
                    ? null : $list->description,
                'date' =>
                    (isset($opt['date']) && $opt['date'] === false)
                    ? null : $list->getFinnaUpdated() ?? $list->getCreated(),
                'listTags' => $listTags,
                'headingLevel' => $opt['headingLevel'] ?? 2,
                'allowCopy' => $opt['allowCopy'] ?? false,
            ]
        );

        return $html;
    }

    /**
     * Returns HTML for a set of user list result items.
     *
     * @param int    $id         List id
     * @param int    $offset     Record offset
     * @param int    $startIndex Result item offset in DOM
     * @param string $view       Result view type
     * @param int    $sort       Sort
     *
     * @return string
     */
    public function loadMore($id, $offset, $startIndex, $view, $sort)
    {
        // These need to differ from Search/Results so that
        // list notes are shown...
        $this->viewModel->setVariable('templateDir', 'content');
        $this->viewModel->setVariable('templateName', 'content');

        $resultsCopy = clone $this->results;
        $params = $resultsCopy->getParams();
        $params->initFromRequest(new Parameters(['id' => $id]));

        $resultsTotal = $resultsCopy->getResultTotal();
        // Limit needs to be smaller than total amount
        // so that record start index can be overridden
        // in VuFind\Search\Results\Favorites
        $limit = $resultsTotal - 1;

        return ($this)(
            [
                'id' => $id, 'page' => 1, 'limit' => $limit,
                'view' => $view, 'sort' => $sort,
            ],
            $offset,
            $startIndex
        );
    }

    /**
     * Returns HTML for an error message.
     *
     * @param string $msg Message
     *
     * @return string
     */
    protected function error($msg)
    {
        return '<div class="alert alert-danger">' . $msg . '</div>';
    }
}
