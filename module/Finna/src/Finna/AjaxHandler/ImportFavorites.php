<?php
/**
 * AJAX handler for importing favorites.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2017-2018.
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
 * @package  AJAX
 * @author   Joni Nevalainen <joni.nevalainen@gofore.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Db\Row\User;
use VuFind\Db\Table\Search as SearchTable;
use VuFind\Db\Table\UserList as UserListTable;
use VuFind\Db\Table\UserResource as UserResourceTable;
use VuFind\Favorites\FavoritesService;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Record\Loader;
use VuFind\Search\Results\PluginManager as ResultsManager;

/**
 * AJAX handler for importing favorites.
 *
 * Imports searches and lists from uploaded file to logged in user's account.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Joni Nevalainen <joni.nevalainen@gofore.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ImportFavorites extends \VuFind\AjaxHandler\AbstractBase
    implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Logged in user (or false)
     *
     * @var User|bool
     */
    protected $user;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Search table
     *
     * @var SearchTable
     */
    protected $searchTable;

    /**
     * UserList table
     *
     * @var UserListTable
     */
    protected $userListTable;

    /**
     * UserResource table
     *
     * @var UserResourceTable
     */
    protected $userResourceTable;

    /**
     * Results plugin manager
     *
     * @var ResultsManager
     */
    protected $resultsManager;

    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * Favorites service
     *
     * @var FavoritesService
     */
    protected $favorites;

    /**
     * Constructor
     *
     * @param User|bool         $user      Logged in user (or false)
     * @param RendererInterface $renderer  View renderer
     * @param SearchTable       $st        Search table
     * @param UserListTable     $ult       UserList table
     * @param UserResourceTable $urt       UserResource table
     * @param ResultsManager    $rm        Results manager
     * @param Loader            $rl        Record loader
     * @param FavoritesService  $favorites Favorites service
     */
    public function __construct($user, RendererInterface $renderer,
        SearchTable $st, UserListTable $ult,
        UserResourceTable $urt, ResultsManager $rm,
        Loader $rl, FavoritesService $favorites
    ) {
        $this->user = $user;
        $this->renderer = $renderer;
        $this->searchTable = $st;
        $this->userListTable = $ult;
        $this->userResourceTable = $urt;
        $this->resultsManager = $rm;
        $this->recordLoader = $rl;
        $this->favorites = $favorites;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        if ($this->user === false) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_HTTP_NEED_AUTH
            );
        }

        $file = $params->fromFiles('favorites-file');
        $fileExists = !empty($file['tmp_name']) && file_exists($file['tmp_name']);
        $error = false;

        if ($fileExists) {
            $data = json_decode(file_get_contents($file['tmp_name']), true);
            if ($data) {
                $searches = $this->importSearches($data['searches']);
                $lists = $this->importUserLists($data['lists']);

                $templateParams = [
                    'searches' => $searches,
                    'lists' => $lists['userLists'],
                    'resources' => $lists['userResources']
                ];
            } else {
                $error = true;
                $templateParams = [
                    'error' => $this->translate(
                        'import_favorites_error_invalid_file'
                    )
                ];
            }
        } else {
            $error = true;
            $templateParams = [
                'error' => $this->translate('import_favorites_error_no_file')
            ];
        }

        $template = $error
            ? 'myresearch/import-error.phtml'
            : 'myresearch/import-success.phtml';
        $html = $this->renderer->partial($template, $templateParams);
        return $this->formatResponse(compact('html'));
    }

    /**
     * Imports an array of serialized search objects as user's saved searches.
     *
     * @param array $searches Array of search objects
     *
     * @return int Number of searches saved
     */
    protected function importSearches($searches)
    {
        $userId = $this->user->id;
        $initialSearchCount = count($this->searchTable->getSavedSearches($userId));

        foreach ($searches as $search) {
            $minifiedSO = unserialize($search);

            if ($minifiedSO) {
                $row = $this->searchTable->saveSearch(
                    $this->resultsManager,
                    $minifiedSO->deminify($this->resultsManager),
                    null,
                    $userId
                );
                $row->user_id = $userId;
                $row->saved = 1;
                $row->save();
            }
        }

        $newSearchCount = count($this->searchTable->getSavedSearches($userId));
        return $newSearchCount - $initialSearchCount;
    }

    /**
     * Imports an array of user lists into database. A single user list is expected
     * to be in following format:
     *
     *   [
     *     title: string
     *     description: string
     *     public: int (0|1)
     *     records: array of [
     *       notes: string
     *       source: string
     *       id: string
     *     ]
     *   ]
     *
     * @param array $lists User lists
     *
     * @return array [userLists => int, userResources => int], number of new user
     * lists created and number of records to saved into user lists.
     */
    protected function importUserLists($lists)
    {
        $userId = $this->user->id;
        $favoritesCount = 0;
        $listCount = 0;

        foreach ($lists as $list) {
            $existingList
                = $this->userListTable->getByTitle($userId, $list['title']);

            if (!$existingList) {
                $existingList = $this->userListTable->getNew($this->user);
                $existingList->title = $list['title'];
                $existingList->description = $list['description'];
                $existingList->public = $list['public'];
                $existingList->save($this->user);
                $listCount++;
            }

            foreach ($list['records'] as $record) {
                $driver = $this->recordLoader->load(
                    $record['id'],
                    $record['source'],
                    true
                );

                if ($driver instanceof Missing) {
                    continue;
                }

                $params = [
                    'notes' => $record['notes'],
                    'list' => $existingList->id,
                    'mytags' => $record['tags']
                ];
                $this->favorites->save($params, $this->user, $driver);

                if ($record['order'] !== null) {
                    $userResource = $this->user->getSavedData(
                        $record['id'],
                        $existingList->id,
                        $record['source']
                    )->current();

                    if ($userResource) {
                        $this->userResourceTable->createOrUpdateLink(
                            $userResource->resource_id,
                            $userId,
                            $existingList->id,
                            $record['notes'],
                            $record['order']
                        );
                    }
                }

                $favoritesCount++;
            }
        }

        return [
            'userLists' => $listCount,
            'userResources' => $favoritesCount
        ];
    }
}
