<?php
/**
 * View helper for embedding a user list.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

use Laminas\Stdlib\Parameters;

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
     * Favorites results
     *
     * @var \VuFind\Search\Favorites\Results
     */
    protected $results;

    /**
     * UserList table
     *
     * @var \VuFind\Search\Favorites\Results
     */
    protected $listTable;

    /**
     * Tags table
     *
     * @var \VuFind\Db\Table\Tags
     */
    protected $tagsTable;

    /**
     * Whether list tags are enabled.
     *
     * @var bool
     */
    protected $listTagsEnabled;

    /**
     * Counter used to ensure unique id attributes when several lists are displayed
     *
     * @var int
     */
    protected $indexStart = 0;

    /**
     * View model
     *
     * @var \Laminas\View\Model\ViewModel
     */
    protected $viewModel;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Favorites\Results $results   Results
     * @param \VuFind\Db\Table\UserList        $listTable UserList table
     * @param \VuFind\Db\Table\Tags            $tagsTable Tags table
     * @param \Laminas\View\Model\ViewModel    $viewModel View model
     * @param bool                             $listTags  Whether list tags
     *                                                    are enabled
     */
    public function __construct(
        \VuFind\Search\Favorites\Results $results,
        \VuFind\Db\Table\UserList $listTable,
        \VuFind\Db\Table\Tags $tagsTable,
        \Laminas\View\Model\ViewModel $viewModel,
        bool $listTags
    ) {
        $this->results = $results;
        $this->listTable = $listTable;
        $this->tagsTable = $tagsTable;
        $this->viewModel = $viewModel;
        $this->listTagsEnabled = $listTags;
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
            if (!in_array(
                $key, ['id', 'view', 'sort', 'limit', 'page',
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
            $list = $this->listTable->getExisting($id);
            if (!$list->isPublic()) {
                return $this->error('List is private');
            }
        } catch (\Exception $e) {
            return $this->error('Could not find list');
        }

        $loadMore = $offset !== null;

        $opt['limit'] = $opt['limit'] ?? 100;

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

        $listTags = null;
        if (($opt['tags'] ?? false) && $this->listTagsEnabled) {
            $listTags = $this->tagsTable
                ->getForList($list->id, $list->user_id);
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
                    ? null : $list->title,
                'description' =>
                    (isset($opt['description']) && $opt['description'] === false)
                    ? null : $list->description,
                'date' =>
                    (isset($opt['date']) && $opt['date'] === false)
                    ? null : $list->finna_updated ?? $list->created,
                'listTags' => $listTags,
                'headingLevel' => $opt['headingLevel'] ?? 2,
                'allowCopy' => $opt['allowCopy'] ?? false
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

        return $this->__invoke(
            [
                'id' => $id, 'page' => 1, 'limit' => $limit,
                'view' => $view, 'sort' => $sort
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
