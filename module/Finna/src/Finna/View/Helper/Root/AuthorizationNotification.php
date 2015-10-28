<?php
/**
 * Helper class for displaying a notification for unauthorized users
 * on Primo result pages.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;
use ZfcRbac\Service\AuthorizationService;

/**
 * Helper class for displaying a notification for unauthorized users
 * on Primo result pages.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class AuthorizationNotification extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Authorization service
     *
     * @var Zend\Service\AuthorizationService
     */
    protected $authService;

    /**
     * Constructor
     *
     * @param Zend\Service\AuthorizationService $authService Authorization service
     */
    public function __construct(AuthorizationService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * If needed, returns rendered notification.
     *
     * @param string $searchClass Search class
     *
     * @return null|string notification
     */
    public function __invoke($searchClass)
    {
        if (in_array($searchClass, ['MetaLib', 'Primo'])) {
            if (!$this->authService->isGranted('finna.authorized')) {
                return $this->getView()->render('Helpers/authorizationNote.phtml');
            }
        }
    }
}
