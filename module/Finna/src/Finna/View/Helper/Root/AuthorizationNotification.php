<?php

/**
 * Helper class for displaying a notification for unauthorized users
 * on Primo result pages.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use Lmc\Rbac\Mvc\Service\AuthorizationService;

use function in_array;

/**
 * Helper class for displaying a notification for unauthorized users
 * on Primo result pages.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AuthorizationNotification extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Authorization service
     *
     * @var AuthorizationService
     */
    protected $authService;

    /**
     * Constructor
     *
     * @param AuthorizationService $authService Authorization service
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
        if (in_array($searchClass, ['EDS', 'Primo', 'Summon'])) {
            if (!$this->authService->isGranted('finna.authorized')) {
                return $this->getView()->render('Helpers/authorizationNote.phtml');
            }
        }
    }
}
