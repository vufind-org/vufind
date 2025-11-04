<?php

/**
 * AJAX handler for adding a record to a list.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Favorites\FavoritesService;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Record\Loader;
use VuFind\View\Helper\Root\Record as RecordHelper;

/**
 * AJAX handler for editing a list.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AddToList extends \VuFind\AjaxHandler\AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor
     *
     * @param ?UserEntityInterface     $user            Logged in user (or null)
     * @param UserListServiceInterface $userListService User list database service
     * @param FavoritesService         $favorites       Favorites service
     * @param Loader                   $recordLoader    Record loader
     * @param RecordHelper             $recordHelper    Record helper
     * @param bool                     $enabled         Are lists enabled?
     */
    public function __construct(
        protected ?UserEntityInterface $user,
        protected UserListServiceInterface $userListService,
        protected FavoritesService $favorites,
        protected Loader $recordLoader,
        protected RecordHelper $recordHelper,
        protected $enabled = true
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
        // Fail if lists are disabled:
        if (!$this->enabled) {
            return $this->formatResponse(
                $this->translate('Lists disabled'),
                self::STATUS_HTTP_FORBIDDEN
            );
        }

        if (null === $this->user) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_HTTP_NEED_AUTH
            );
        }

        $listParams = $params->fromPost('params');
        if (empty($listParams['listId']) || empty($listParams['ids'])) {
            return $this->formatResponse(
                $this->translate('Missing parameter'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }
        $listId = $listParams['listId'];
        $currentListId = $listParams['currentListId'];
        $ids = (array)$listParams['ids'];

        $list = $this->userListService->getUserListById($listId);
        if ($list->getUser()?->getId() !== $this->user->getId()) {
            return $this->formatResponse(
                $this->translate('Invalid list id'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        foreach ($ids as $id) {
            $source = $id[0];
            $recId = $id[1];
            try {
                $driver = $this->recordLoader->load($recId, $source, true);
                $notes = implode(
                    PHP_EOL,
                    ($this->recordHelper)($driver)->getListNotes($currentListId ?: null, $this->user)
                );

                $this->favorites->saveRecordToFavorites(['list' => $listId, 'notes' => $notes], $this->user, $driver);
            } catch (\Exception $e) {
                return $this->formatResponse(
                    $this->translate('Failed'),
                    self::STATUS_HTTP_ERROR
                );
            }
        }

        return $this->formatResponse('');
    }
}
