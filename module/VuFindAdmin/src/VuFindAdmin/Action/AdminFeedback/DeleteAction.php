<?php

/**
 * Delete feedback action.
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

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Db\Service\FeedbackServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\GlobalsContainer;
use VuFindAdmin\Action\Admin\AbstractAdminAction;

use function count;
use function is_array;

/**
 * Delete feedback action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DeleteAction extends AbstractAdminAction implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Constructor.
     *
     * @param GlobalsContainer         $globalsContainer Globals container
     * @param array                    $config           VuFind configuration
     * @param FeedbackServiceInterface $feedbackService  Feedback database service
     */
    public function __construct(
        GlobalsContainer $globalsContainer,
        #[Autowire(config: 'config')]
        array $config,
        #[Autowire(container: DbServicePluginManager::class)]
        protected FeedbackServiceInterface $feedbackService,
    ) {
        parent::__construct($globalsContainer, $config);
    }

    /**
     * Delete feedback.
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
        $confirm = $this->getPostOrQueryParam('confirm');
        $originUrl = $this->routeHelper->getUrlFromRoute('admin/feedback');
        $formName = $this->getPostOrQueryParam('form_name');
        $siteUrl = $this->getPostOrQueryParam('site_url');
        $status = $this->getPostOrQueryParam('status');
        $originUrl .= '?' . http_build_query(
            [
                'form_name' => $formName ?: 'ALL',
                'site_url' => $siteUrl ?: 'ALL',
                'status' => $status ?: 'ALL',
            ]
        );
        $newUrl = $this->routeHelper->getUrlFromRoute('admin/feedback', ['action' => 'Delete']);

        $ids = null === $this->getPostOrQueryParam('deletePage')
            ? $this->getPostOrQueryParam('ids')
            : $this->getPostOrQueryParam('idsAll');

        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        $redirectHelper = $this->getHelper(RedirectHelper::class);
        if (!is_array($ids) || empty($ids)) {
            $flashMessagesHelper->addErrorMessage('bulk_noitems_advice');
            return $redirectHelper->redirectToUrl($response, $originUrl);
        }
        if (!$confirm) {
            return $this->confirmDelete($request, $response, $ids, $originUrl, $newUrl);
        }
        $delete = $this->feedbackService->deleteByIdArray($ids);
        if (0 == $delete) {
            $flashMessagesHelper->addErrorMessage('feedback_delete_failure');
            return $redirectHelper->redirectToUrl($response, $originUrl);
        }
        $flashMessagesHelper->addSuccessMessage(
            [
                'msg' => 'feedback_delete_success',
                'tokens' => ['%%count%%' => $delete],
            ]
        );
        return $redirectHelper->redirectToUrl($response, $originUrl);
    }

    /**
     * Confirm delete feedback messages.
     *
     * @param ServerRequestInterface $request   Server request
     * @param ResponseInterface      $response  Response
     * @param array                  $ids       IDs of feedback messages to delete
     * @param string                 $originUrl URL to redirect to after cancel
     * @param string                 $newUrl    URL to redirect to after confirm
     *
     * @return mixed
     */
    protected function confirmDelete(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $ids,
        string $originUrl,
        string $newUrl
    ): ResponseInterface {
        $data = [
            'confirm' => $newUrl,
            'cancel' => $originUrl,
            'title' => 'confirm_delete_feedback',
            'messages' => $this->getConfirmDeleteMessages(count($ids)),
            'ids' => $ids,
            'extras' => [
                'form_name' => $this->getPostOrQueryParam('form_name'),
                'site_url' => $this->getPostOrQueryParam('site_url'),
                'status' => $this->getPostOrQueryParam('status'),
                'ids' => $ids,
            ],
        ];
        if (!($routeMatch = $request->getAttribute('route-match'))) {
            throw new Exception('Route match missing from request');
        }
        $routeMatch->setParam('data', $data);
        return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, 'Confirm/Confirm');
    }

    /**
     * Get messages for confirm delete.
     *
     * @param int $count Count of feedback messages to delete
     *
     * @return array[]
     */
    protected function getConfirmDeleteMessages(int $count): array
    {
        // Default all messages to "All"; we'll make them more specific as needed:
        $allMessage = $this->translate('All');

        $params = ['form_name', 'site_url', 'status'];
        $paramMessages = [];
        foreach ($params as $param) {
            $value = $this->getPostOrQueryParam($param);
            $message = $value ?: $allMessage;
            $message = $message === 'ALL' ? $allMessage : $message;
            $paramMessages[$param] = $message;
        }

        $messages = [];
        $messages[] = [
            'msg' => 'feedback_delete_warning',
            'tokens' => ['%%count%%' => $count],
        ];

        if (array_filter(array_map([$this, 'getPostOrQueryParam'], $params))) {
            $messages[] = [
                'msg' => 'feedback_delete_filter',
                'tokens' => [
                    '%%formname%%' => $paramMessages['form_name'],
                    '%%siteurl%%' => $paramMessages['site_url'],
                    '%%status%%' => $paramMessages['status'],
                ],
            ];
        }
        $messages[] = ['msg' => 'confirm_delete'];
        return $messages;
    }
}
