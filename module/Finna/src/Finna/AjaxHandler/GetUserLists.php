<?php

/**
 * AJAX handler for retrieving lists.
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

use Finna\Db\Service\UserResourceServiceInterface;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * AJAX handler for retrieving lists.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetUserLists extends \VuFind\AjaxHandler\AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor
     *
     * @param ?UserEntityInterface         $user                Logged in user (or null)
     * @param UserListServiceInterface     $userListService     UserList database service
     * @param UserResourceServiceInterface $userResourceService UserResource database service
     * @param RendererInterface            $renderer            View renderer
     * @param bool                         $enabled             Are lists enabled?
     */
    public function __construct(
        protected ?UserEntityInterface $user,
        protected UserListServiceInterface $userListService,
        protected UserResourceServiceInterface $userResourceService,
        protected RendererInterface $renderer,
        protected bool $enabled = true
    ) {
        $this->user = $user;
        $this->renderer = $renderer;
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

        if ($this->user === null) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_HTTP_NEED_AUTH
            );
        }

        $activeId = (int)$params->fromPost('active');
        $lists = $this->userListService->getUserListsAndCountsByUser($this->user);
        $totalResourceCount = $this->userResourceService->getTotalResourceCount($this->user);

        $html = $this->renderer->partial(
            'myresearch/mylist-navi.phtml',
            [
                'user' => $this->user,
                'activeId' => $activeId,
                'lists' => $lists,
                'totalResourceCount' => $totalResourceCount,
            ]
        );
        return $this->formatResponse($html);
    }
}
