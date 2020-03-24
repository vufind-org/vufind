<?php
/**
 * Get user list items via AJAX.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use Finna\View\Helper\Root\UserlistEmbed;
use VuFind\Session\Settings as SessionSettings;
use Zend\Mvc\Controller\Plugin\Params;

/**
 * Get user list items.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetUserList extends \VuFind\AjaxHandler\AbstractBase
{
    /**
     * UserlistEmbed helper
     *
     * @var UserlistEmbed
     */
    protected $helper;

    /**
     * Constructor
     *
     * @param SessionSettings $ss     Session settings
     * @param UserlistEmbed   $helper UserlistEmbed helper.
     */
    public function __construct(SessionSettings $ss, UserlistEmbed $helper)
    {
        $this->sessionSettings = $ss;
        $this->helper = $helper;
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

        $id = $params->fromPost('id', $params->fromQuery('id'));
        $offset = $params->fromPost('offset', $params->fromQuery('offset'));
        $indexStart
            = $params->fromPost('indexStart', $params->fromQuery('indexStart'));
        $view
            = $params->fromPost('view', $params->fromQuery('view'));
        $sort
            = $params->fromPost('sort', $params->fromQuery('sort'));

        $html = $this->helper->loadMore($id, $offset, $indexStart, $view, $sort);
        return $this->formatResponse(compact('html'));
    }
}
