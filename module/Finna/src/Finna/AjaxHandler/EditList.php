<?php
/**
 * AJAX handler for editing a list.
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

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Db\Row\User;
use VuFind\Db\Table\UserList;
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * AJAX handler for editing a list.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EditList extends \VuFind\AjaxHandler\AbstractBase
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
     * @param UserList  $userList UserList database table
     * @param User|bool $user     Logged in user (or false)
     * @param bool      $enabled  Are lists enabled?
     */
    public function __construct(UserList $userList, $user, $enabled = true)
    {
        $this->userList = $userList;
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
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        if ($this->user === false) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_HTTP_NEED_AUTH
            );
        }

        $listParams = $params->fromPost('params');
        if (!isset($listParams['id']) || !isset($listParams['title'])) {
            return $this->formatResponse(
                $this->translate('Missing parameter'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        // Is this a new list or an existing list?  Handle the special 'NEW' value
        // of the ID parameter:
        $list = 'NEW' === $listParams['id'] ? $this->userList->getNew($this->user)
            : $this->userList->getExisting($listParams['id']);

        $finalId = $list->updateFromRequest(
            $this->user, new \Laminas\Stdlib\Parameters($listParams)
        );

        $listParams['id'] = $finalId;

        return $this->formatResponse($listParams);
    }
}
