<?php

/**
 * Delete comments action.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025-2026.
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
 * @package  Action
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Comments;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Service\CommentsServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Validator\CsrfInterface;

/**
 * Delete comments action.
 *
 * @category VuFind
 * @package  Action
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DeleteCommentsAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param AuthManager              $authManager     Authentication manager
     * @param CommentsServiceInterface $commentsService Comments database service
     * @param CsrfInterface            $csrf            CSRF validator
     */
    public function __construct(
        protected AuthManager $authManager,
        #[Autowire(container: DbServicePluginManager::class)]
        protected CommentsServiceInterface $commentsService,
        protected CsrfInterface $csrf,
    ) {
        parent::__construct();
    }

    /**
     * Delete given comments by the logged in user.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        if (!($user = $this->authManager->getUserObject())) {
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }
        if ($this->getHelper(FormHelper::class)->formWasSubmitted($request, ['deleteSelectedcomment'])) {
            if (!$this->csrf->isValid($this->getPostParam('csrf'))) {
                throw new \VuFind\Exception\BadRequest('error_inconsistent_parameters');
            }
        }

        if ($comments = $this->getPostParam('deleteSelectedcomment', [])) {
            $this->commentsService->deleteByIdsAndUserId($comments, $user->getId());
        }

        return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'comments-userlist');
    }
}
