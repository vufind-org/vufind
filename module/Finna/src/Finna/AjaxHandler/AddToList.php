<?php
/**
 * AJAX handler for adding a record to a list.
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use VuFind\Db\Row\User;
use VuFind\Db\Table\UserList;
use VuFind\Favorites\FavoritesService;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Record\Loader;
use Zend\Mvc\Controller\Plugin\Params;

/**
 * AJAX handler for editing a list.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AddToList extends \VuFind\AjaxHandler\AbstractBase
    implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * UserList database table
     *
     * @var UserList
     */
    protected $userList;

    /**
     * Favorites service
     *
     * @var FavoritesService
     */
    protected $favorites;

    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * Logged in user (or false)
     *
     * @var User|bool
     */
    protected $user;

    /**
     * Are lists enabled?
     *
     * @var bool
     */
    protected $enabled;

    /**
     * Constructor
     *
     * @param UserList         $userList  UserList database table
     * @param FavoritesService $favorites Favorites service
     * @param Loader           $loader    Record loader
     * @param User|bool        $user      Logged in user (or false)
     * @param bool             $enabled   Are lists enabled?
     */
    public function __construct(UserList $userList, FavoritesService $favorites,
        Loader $loader, $user, $enabled = true
    ) {
        $this->userList = $userList;
        $this->favorites = $favorites;
        $this->recordLoader = $loader;
        $this->user = $user;
        $this->enabled = $enabled;
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

        if ($this->user === false) {
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
        $ids = (array)$listParams['ids'];

        $list = $this->userList->getExisting($listId);
        if ($list->user_id !== $this->user->id) {
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
                $this->favorites->save(['list' => $listId], $this->user, $driver);
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
