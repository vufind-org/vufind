<?php
/**
 * GetImageInformation AJAX handler
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2019.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use Laminas\Config\Config;
use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Db\Row\User;
use VuFind\Db\Table\User as UserTable;
use VuFind\Db\Table\UserList;
use VuFind\Record\Loader;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Helper\Root\Record;

/**
 * GetImageInformation AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetImageInformation extends \VuFind\AjaxHandler\AbstractBase
{
    /**
     * Config
     *
     * @var Config
     */
    protected $config;

    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * User table
     *
     * @var UserTable
     */
    protected $userTable;

    /**
     * UserList table
     *
     * @var UserList
     */
    protected $userListTable;

    /**
     * Logged in user (or false)
     *
     * @var User|bool
     */
    protected $user;

    /**
     * Record plugin
     *
     * @var Record
     */
    protected $recordPlugin;

    /**
     * Constructor
     *
     * @param SessionSettings $ss        Session settings
     * @param Config          $config    Main configuration
     * @param Loader          $loader    Record loader
     * @param UserTable       $userTable User table
     * @param UserList        $userList  UserList table
     * @param User|bool       $user      Logged in user (or false)
     * @param Record          $rp        Record plugin
     */
    public function __construct(SessionSettings $ss,
        Config $config, Loader $loader, UserTable $userTable, UserList $userList,
        $user, Record $rp
    ) {
        $this->sessionSettings = $ss;
        $this->config = $config;
        $this->recordLoader = $loader;
        $this->userTable = $userTable;
        $this->userListTable = $userList;
        $this->user = $user;
        $this->recordPlugin = $rp;
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

        list($source, $recId) = explode('.', $id, 2);
        if ('pci' === $source) {
            $source = 'Primo';
        } else {
            $source = 'Solr';
        }
        $driver = $this->recordLoader->load($id, $source);

        $context = [
            'driver' => $driver,
            'index' => $index
        ];
        $user = null;
        if ($publicList) {
            // Public list view: fetch list owner
            $list = $this->userListTable->select(['id' => $listId])->current();
            if ($list && $list->isPublic()) {
                $user = $this->userTable->getById($list->user_id);
            }
        } else {
            // otherwise, use logged-in user if available
            $user = $this->user;
        }

        if ($user && $data = $user->getSavedData($id, $listId)) {
            $notes = [];
            foreach ($data as $list) {
                if (!empty($list->notes)) {
                    $notes[] = $list->notes;
                }
            }
            $context['listNotes'] = $notes;
            if ($publicList) {
                $context['listUser'] = $user;
            }
        }

        $html = ($this->recordPlugin)($driver)
            ->renderTemplate('record-image-popup-information.phtml', $context);

        return $this->formatResponse(['html' => $html]);
    }
}
