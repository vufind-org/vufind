<?php

/**
 * GetImageInformation AJAX handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2024.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Config\Config;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Service\UserResourceServiceInterface;
use VuFind\Favorites\FavoritesService;
use VuFind\Record\Loader;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Helper\Root\Record;

/**
 * GetImageInformation AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetImageInformation extends \VuFind\AjaxHandler\AbstractBase
{
    use \Finna\Statistics\ReporterTrait;

    /**
     * Constructor
     *
     * @param SessionSettings              $sessionSettings     Session settings
     * @param Config                       $config              Main configuration
     * @param Loader                       $recordLoader        Record loader
     * @param ?UserEntityInterface         $user                Logged in user (or null)
     * @param UserListServiceInterface     $userListService     UserList database service
     * @param UserResourceServiceInterface $userResourceService UserResource database service
     * @param FavoritesService             $favoritesService    Favorites service
     * @param Record                       $recordPlugin        Record plugin
     */
    public function __construct(
        SessionSettings $sessionSettings,
        protected Config $config,
        protected Loader $recordLoader,
        protected ?UserEntityInterface $user,
        protected UserListServiceInterface $userListService,
        protected UserResourceServiceInterface $userResourceService,
        protected FavoritesService $favoritesService,
        protected Record $recordPlugin
    ) {
        $this->sessionSettings = $sessionSettings;
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
        $this->disableSessionWrites();  // avoid session write timing bug

        $id = $params->fromQuery('id');
        $index = $params->fromQuery('index');
        $publicList = $params->fromQuery('publicList') === '1';
        $listId = $params->fromQuery('listId');
        $searchId = $params->fromQuery('sid');

        if (null === ($source = $params->fromQuery('source'))) {
            [$source] = explode('.', $id, 2);
            if ('pci' === $source) {
                $source = 'Primo';
            } else {
                $source = 'Solr';
            }
        }
        $driver = $this->recordLoader->load($id, $source);

        $context = [
            'driver' => $driver,
            'index' => $index,
            'searchId' => $searchId,
        ];
        $user = null;
        if ($publicList) {
            // Public list view: fetch list owner
            $list = $this->userListService->getUserListById($listId);
            if ($list && $list->isPublic()) {
                $user = $list->getUser();
            }
        } else {
            // otherwise, use logged-in user if available
            $user = $this->user;
        }

        if ($user && $data = $this->userResourceService->getFavoritesForRecord($id, $source, $listId, $user)) {
            $notes = [];
            foreach ($data as $list) {
                if ($listNotes = $list->getNotes()) {
                    $notes[] = $listNotes;
                }
            }
            $context['listNotes'] = $notes;
            if ($publicList) {
                $context['listUser'] = $user;
            }
        }

        $this->triggerStatsRecordView($driver);

        $html = ($this->recordPlugin)($driver)
            ->renderTemplate('record-image-popup-information.phtml', $context);

        return $this->formatResponse(['html' => $html]);
    }
}
