<?php

/**
 * Logout message if user has just logged out.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;
use Zend\Session\Container as SessionContainer;

/**
 * Logout message if user has just logout.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class LogoutMessage extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Authentication Manager
     *
     * @var \VuFind\Auth\Manager
     */
    protected $authManager;

    /**
     * Request
     *
     * @var \Zend\Http\Request
     */
    protected $request;

    /**
     * Session container
     *
     * @var \VuFind\Session\Container
     */
    protected $session;

    /**
     * Constructor
     *
     * @param \VuFind\Auth\Manager $authManager Authentication manager
     * @param \Zend\Http\Request   $request     Request
     */
    public function __construct($authManager, $request)
    {
        $this->authManager = $authManager;
        $this->request = $request;
        $this->session = new SessionContainer('Logout');
    }

    /**
     * Return logout text after user has logged out.
     * Shown only after the first page load if loggedOut param is true.
     *
     * @return string Logout message
     */
    public function __invoke()
    {
        if ($this->authManager->userHasLoggedOut()
            && $this->request->getQuery('logout', false)
        ) {
            if (!isset($this->session->logoutMessageShown)
                || !$this->session->logoutMessageShown
            ) {
                $this->session->logoutMessageShown = true;
                return 'logout_success_message';
            }
        }
        return false;
    }

}
