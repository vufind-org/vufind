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

use Zend\Stdlib\Parameters;

/**
 * View helper for embedding a user list.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class UserListEmbed extends \Zend\View\Helper\AbstractHelper
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
     * Counter used to ensure unique id attributes when several lists are displayed
     *
     * @var int
     */
    protected $indexStart = 0;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Favorites\Results $results   Results
     * @param \VuFind\Db\Table\UserList        $listTable UserList table
     */
    public function __construct(
        \VuFind\Search\Favorites\Results $results,
        \VuFind\Db\Table\UserList $listTable
    ) {
        $this->results = $results;
        $this->listTable = $listTable;
    }

    /**
     * Returns HTML for embedding a user list.
     *
     * @param array $opt Options
     *
     * @return string
     */
    public function __invoke($opt)
    {
        foreach (array_keys($opt) as $key) {
            if (!in_array(
                $key, ['id', 'view', 'sort', 'limit', 'page',
                       'title', 'description', 'date', 'headingLevel']
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

        $opt['limit'] = $opt['limit'] ?? 100;

        $resultsCopy = clone $this->results;
        $params = $resultsCopy->getParams();
        $params->initFromRequest(new Parameters($opt));
        $resultsCopy->performAndProcessSearch();
        $list = $resultsCopy->getListObject();
        $view = $opt['view'] ?? 'list';
        $idStart = $this->indexStart;
        $this->indexStart += $resultsCopy->getResultTotal();
        return $this->getView()->render(
            'Helpers/userlist.phtml',
            [
                'results' => $resultsCopy,
                'params' => $params,
                'indexStart' => $idStart,
                'view' => $view,
                'title' =>
                    (isset($opt['title']) && $opt['title'] === false)
                    ? null : $list->title,
                'description' =>
                    (isset($opt['description']) && $opt['description'] === false)
                    ? null : $list->description,
                'date' =>
                    (isset($opt['date']) && $opt['date'] === false)
                    ? null : $list->finna_updated ?? $list->created,
                'headingLevel' => $opt['headingLevel'] ?? 2
            ]
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
