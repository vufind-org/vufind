<?php

/**
 * Update feedback status action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2026.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindAdmin\Action\AdminFeedback;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Service\FeedbackServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\GlobalsContainer;
use VuFindAdmin\Action\Admin\AbstractAdminAction;

/**
 * Update feedback status action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class UpdateStatusAction extends AbstractAdminAction implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Constructor.
     *
     * @param GlobalsContainer         $globalsContainer Globals container
     * @param array                    $config           VuFind configuration
     * @param FeedbackServiceInterface $feedbackService  Feedback database service
     * @param AuthManager              $authManager      Authentication manager
     */
    public function __construct(
        GlobalsContainer $globalsContainer,
        #[Autowire(config: 'config')]
        array $config,
        #[Autowire(container: DbServicePluginManager::class)]
        protected FeedbackServiceInterface $feedbackService,
        protected AuthManager $authManager,
    ) {
        parent::__construct($globalsContainer, $config);
    }

    /**
     * Update feedback status.
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
        $newStatus = $this->getPostOrQueryParam('new_status');
        $id = (int)($this->getPostOrQueryParam('id'));
        $success = false;
        try {
            $feedback = $this->feedbackService->getFeedbackById($id);
            if ($feedback) {
                $feedback
                    ->setStatus($newStatus)
                    ->setUpdatedBy($this->authManager->getUserObject());
                $this->feedbackService->persistEntity($feedback);
                $success = true;
            }
        } catch (\Exception $e) {
            // Fall through to display an error message
        }
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        $redirectHelper = $this->getHelper(RedirectHelper::class);
        if ($success) {
            $flashMessagesHelper->addSuccessMessage('feedback_status_update_success');
        } else {
            $flashMessagesHelper->addErrorMessage('feedback_status_update_failure');
        }
        return $redirectHelper->redirectToRoute(
            $response,
            'admin/feedback',
            queryParams: array_filter(
                [
                    'form_name' => $this->getPostOrQueryParam('form_name'),
                    'site_url' => $this->getPostOrQueryParam('site_url'),
                    'status' => $this->getPostOrQueryParam('status'),
                ]
            )
        );
    }
}
