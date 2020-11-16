<?php
/**
 * AJAX handler for editing a list resource.
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
use VuFind\Db\Table\UserResource;
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * AJAX handler for editing a list resource.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EditListResource extends \VuFind\AjaxHandler\AbstractBase
    implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * UserResource database table
     *
     * @var UserResource
     */
    protected $userResource;

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
     * @param UserResource $userResource UserResource database table
     * @param User|bool    $user         Logged in user (or false)
     * @param bool         $enabled      Are lists enabled?
     */
    public function __construct(UserResource $userResource, $user, $enabled = true)
    {
        $this->userResource = $userResource;
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
                self::STATUS_HTTP_NEED_AUTH,
                401
            );
        }

        $listParams = $params->fromPost('params');
        if (!isset($listParams['listId']) || !isset($listParams['notes'])
            || !isset($listParams['id'])
        ) {
            return $this->formatResponse(
                $this->translate('Missing parameter'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        list($source, $id) = explode('.', $listParams['id'], 2);
        if (!empty($listParams['source'])) {
            $source = $listParams['source'];
        } else {
            $map = ['pci' => 'Primo', 'eds' => 'Eds', 'summon' => 'Summon'];
            $source = $map[$source] ?? DEFAULT_SEARCH_BACKEND;
        }

        $listId = $listParams['listId'];
        $notes = $listParams['notes'];

        $resources = $this->user->getSavedData($listParams['id'], $listId, $source);
        if (empty($resources)) {
            return $this->formatResponse(
                'User resource not found', self::STATUS_HTTP_BAD_REQUEST
            );
        }

        foreach ($resources as $res) {
            $row = $this->userResource->select(['id' => $res->id])->current();
            $row->notes = $notes;
            $row->save();
        }

        return $this->formatResponse('');
    }
}
