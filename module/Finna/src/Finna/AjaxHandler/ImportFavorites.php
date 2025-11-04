<?php

/**
 * AJAX handler for importing favorites.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2017-2024.
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
 * @package  AJAX
 * @author   Joni Nevalainen <joni.nevalainen@gofore.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Finna\Db\Service\UserListServiceInterface;
use Finna\Db\Service\UserResourceServiceInterface;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Session\SessionManager;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\SearchServiceInterface;
use VuFind\Favorites\FavoritesService;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\ResourcePopulator;
use VuFind\Search\SearchNormalizer;

use function count;

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
class ImportFavorites extends \VuFind\AjaxHandler\AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor
     *
     * @param ?UserEntityInterface         $user                Logged in user (or null)
     * @param SearchNormalizer             $searchNormalizer    Search normalize
     * @param SearchServiceInterface       $searchService       Search database service
     * @param UserListServiceInterface     $userListService     User list database service
     * @param UserResourceServiceInterface $userResourceService User resource database service
     * @param FavoritesService             $favoritesService    Favorites service
     * @param SessionManager               $sessionManager      Session manager
     * @param RecordLoader                 $recordLoader        Record loader
     * @param ResourcePopulator            $resourcePopulator   Resource populator
     * @param RendererInterface            $renderer            View renderer
     */
    public function __construct(
        protected ?UserEntityInterface $user,
        protected SearchNormalizer $searchNormalizer,
        protected SearchServiceInterface $searchService,
        protected UserListServiceInterface $userListService,
        protected UserResourceServiceInterface $userResourceService,
        protected FavoritesService $favoritesService,
        protected SessionManager $sessionManager,
        protected RecordLoader $recordLoader,
        protected ResourcePopulator $resourcePopulator,
        protected RendererInterface $renderer
    ) {
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
        if (!$this->user) {
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
                    'resources' => $lists['userResources'],
                ];
            } else {
                $error = true;
                $templateParams = [
                    'error' => $this->translate(
                        'import_favorites_error_invalid_file'
                    ),
                ];
            }
        } else {
            $error = true;
            $templateParams = [
                'error' => $this->translate('import_favorites_error_no_file'),
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
        $sessionId = $this->sessionManager->getId();
        $initialSearchCount = count($this->searchService->getSearches($sessionId, $this->user));
        foreach ($searches as $searchObject) {
            if ($minifiedSO = unserialize($searchObject)) {
                $normalized = $this->searchNormalizer->normalizeMinifiedSearch($minifiedSO);
                $matches = $this->searchNormalizer->getSearchesMatchingNormalizedSearch(
                    $normalized,
                    $sessionId,
                    $this->user->getId(),
                    1 // we only need to identify at most one duplicate match
                );

                if (!$matches) {
                    // If we got this far, we didn't find a saved duplicate, so we should
                    // save the new search:
                    $row = $this->searchService->createAndPersistEntityWithChecksum($normalized->getChecksum());

                    // Now that we have a new id for the search, set it to the search object too:
                    $minifiedSO->id = $row->getId();

                    // Don't set session ID until this stage, because we don't want to risk
                    // ever having a row that's associated with a session but which has no
                    // search object data attached to it; this could cause problems!
                    $row->setSessionId($sessionId);
                    $row->setUser($this->user);
                    $row->setSaved(true);
                    $row->setSearchObject($minifiedSO);
                    $this->searchService->persistEntity($row);
                }
            }
        }

        $newSearchCount = count($this->searchService->getSearches($sessionId, $this->user));
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
        $favoritesCount = 0;
        $listCount = 0;

        foreach ($lists as $list) {
            if ('' === ($title = $list['title'] ?? '')) {
                continue;
            }
            if (!($targetList = $this->userListService->getListByTitle($this->user, $title))) {
                $targetList = $this->favoritesService->createListForUser($this->user);
                $targetList->setTitle($title);
                $targetList->setDescription($list['description'] ?? '');
                $targetList->setPublic($list['public'] ?? false);
                $this->userListService->persistEntity($targetList);
                $listCount++;
            }

            // Reverse the list because saving an item always puts it first in the list:
            foreach (array_reverse($list['records']) as $record) {
                $driver = $this->recordLoader->load(
                    $record['id'],
                    $record['source'],
                    true
                );

                if ($driver instanceof \VuFind\RecordDriver\Missing) {
                    continue;
                }

                $params = [
                    'notes' => $record['notes'] ?? '',
                    'list' => $targetList,
                    'mytags' => $record['tags'] ?? [],
                ];
                $this->favoritesService->saveRecordToFavorites($params, $this->user, $driver);

                if (null !== ($order = $record['order'] ?? null)) {
                    if ($resource = $this->resourcePopulator->getOrCreateResourceForDriver($driver)) {
                        $this->userResourceService->createOrUpdateLinkWithOrder(
                            $resource,
                            $this->user,
                            $targetList,
                            $record['notes'] ?? '',
                            $order
                        );
                    }
                }

                $favoritesCount++;
            }
        }

        return [
            'userLists' => $listCount,
            'userResources' => $favoritesCount,
        ];
    }
}
