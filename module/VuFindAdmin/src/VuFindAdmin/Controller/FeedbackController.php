<?php
declare(strict_types=1);

/**
 * Class FeedbackController
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2023.
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
 * @package  VuFindAdmin\Controller
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFindAdmin\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\Db\Service\FeedbackService;
use VuFind\Db\Table\Feedback;

/**
 * Class FeedbackController
 *
 * @category VuFind
 * @package  VuFindAdmin\Controller
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class FeedbackController extends AbstractAdmin
{
    /**
     * Feedback service
     *
     * @var FeedbackService
     */
    protected $feedbackService;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        parent::__construct($sm);
        $this->feedbackService = $sm->get(\VuFind\Db\Service\PluginManager::class)
            ->get(FeedbackService::class);
    }

    /**
     * Get the url parameters
     *
     * @param string $param          A key to check the url params for
     * @param bool   $prioritizePost If true, check the POST params first
     * @param mixed  $default        Default value if no value found
     *
     * @return string
     */
    protected function getParam($param, $prioritizePost = false, $default = null)
    {
        $primary = $prioritizePost ? 'fromPost' : 'fromQuery';
        $secondary = $prioritizePost ? 'fromQuery' : 'fromPost';
        return $this->params()->$primary($param)
            ?? $this->params()->$secondary($param, $default);
    }

    /**
     * Home action
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        $feedback = $this->feedbackService->getFeedbackByFilter(
            $this->convertFilter($this->getParam('form_name')),
            $this->convertFilter($this->getParam('site_url')),
            $this->convertFilter($this->getParam('status'))
        );
        $view = $this->createViewModel(
            [
                'feedback' => new \Laminas\Paginator\Paginator(
                    new \DoctrineORMModule\Paginator\Adapter\DoctrinePaginator(
                        $feedback
                    )
                ),
                'statuses' => $this->getStatuses(),
                'uniqueForms' => $this->getUniqueColumn('form_name'),
                'uniqueSites' => $this->getUniqueColumn('site_url'),
                'params'
                    => $this->params()->fromQuery() + $this->params()->fromPost(),
            ]
        );
        $page = $this->getParam('page', false, '1');
        $view->feedback->setCurrentPageNumber($page);
        $view->feedback->setItemCountPerPage(20);
        $view->setTemplate('admin/feedback/home');
        return $view;
    }

    /**
     * Delete action
     *
     * @return \Laminas\Http\Response
     */
    public function deleteAction()
    {
        $confirm = $this->getParam('confirm', true);
        $originUrl = $this->url()->fromRoute('admin/feedback');
        $formName = $this->getParam('form_name', true);
        $siteUrl = $this->getParam('site_url', true);
        $status = $this->getParam('status', true);
        $originUrl .= '?' . http_build_query(
            [
                'form_name' => empty($formName) ? 'ALL' : $formName,
                'site_url' => empty($siteUrl) ? 'ALL' : $siteUrl,
                'status' => empty($status) ? 'ALL' : $status,
            ]
        );
        $newUrl = $this->url()->fromRoute('admin/feedback', ['action' => 'Delete']);

        $ids = null === $this->getParam('deletePage', true)
            ? $this->getParam('ids', true)
            : $this->getParam('idsAll', true);

        if (!is_array($ids) || empty($ids)) {
            $this->flashMessenger()->addMessage('bulk_noitems_advice', 'error');
            return $this->redirect()->toUrl($originUrl);
        }
        if (!$confirm) {
            return $this->confirmDelete($ids, $originUrl, $newUrl);
        }
        $delete = $this->feedbackService->deleteByIdArray($ids);
        if (0 == $delete) {
            $this->flashMessenger()->addMessage('feedback_delete_failure', 'error');
            return $this->redirect()->toUrl($originUrl);
        }
        $this->flashMessenger()->addMessage(
            [
                'msg' => 'feedback_delete_success',
                'tokens' => ['%%count%%' => $delete]
            ],
            'success'
        );
        return $this->redirect()->toUrl($originUrl);
    }

    /**
     * Confirm delete feedback messages
     *
     * @param array  $ids       IDs of feedback messages to delete
     * @param string $originUrl URL to redirect to after cancel
     * @param string $newUrl    URL to redirect to after confirm
     *
     * @return mixed
     */
    protected function confirmDelete(array $ids, string $originUrl, string $newUrl)
    {
        $data = [
            'data' => [
                'confirm' => $newUrl,
                'cancel' => $originUrl,
                'title' => "confirm_delete_feedback",
                'messages' => $this->getConfirmDeleteMessages(count($ids)),
                'ids' => $ids,
                'extras' => [
                    'form_name' => $this->getParam('form_name', true),
                    'site_url' => $this->getParam('site_url', true),
                    'status' => $this->getParam('status', true),
                    'ids' => $ids,
                ]
            ]
        ];
        return $this->forwardTo('Confirm', 'Confirm', $data);
    }

    /**
     * Get messages for confirm delete
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
            $value = $this->getParam($param, true);
            $message = $value ?: $allMessage;
            $message = $message === 'ALL' ? $allMessage : $message;
            $paramMessages[$param] = $message;
        }

        $messages = [];
        $messages[] = [
            'msg' => 'feedback_delete_warning',
            'tokens' => ['%%count%%' => $count]
        ];

        if (array_filter(array_map([$this, 'getParam'], $params))) {
            $messages[] = [
                'msg' => 'feedback_delete_filter',
                'tokens' => [
                    '%%formname%%' => $paramMessages['form_name'],
                    '%%siteurl%%' => $paramMessages['site_url'],
                    '%%status%%' => $paramMessages['status'],
                ]
            ];
        }
        $messages[] = ['msg' => 'confirm_delete'];
        return $messages;
    }

    /**
     * Update status field of feedback message
     *
     * @return \Laminas\Http\Response
     */
    public function updateStatusAction()
    {
        $newStatus = $this->getParam('new_status', true);
        $id = $this->getParam('id', true);
        $success = $this->feedbackService->updateColumn("status", $newStatus, $id);
        if ($success) {
            $this->flashMessenger()->addMessage(
                'feedback_status_update_success',
                'success'
            );
        } else {
            $this->flashMessenger()->addMessage(
                'feedback_status_update_failure',
                'error'
            );
        }
        return $this->redirect()->toRoute(
            'admin/feedback',
            [],
            [
                'query' => array_filter(
                    [
                        'form_name' => $this->getParam('form_name'),
                        'site_url' => $this->getParam('site_url'),
                        'status' => $this->getParam('status'),
                    ]
                ),
            ]
        );
    }

    /**
     * Get unique values for a column
     *
     * @param string $column Column name
     *
     * @return array
     */
    protected function getUniqueColumn(string $column): array
    {
        $feedbackArray = $this->feedbackService->getColumn($column);
        $column = $this->feedbackService->mapField($column);
        return array_unique(array_column($feedbackArray, $column));
    }

    /**
     * Converts null and "ALL" params to null
     *
     * @param string|null $value A parameter to check
     *
     * @return string|null A modified parameter
     */
    protected function convertFilter(?string $value): ?string
    {
        return ("ALL" !== $value && null !== $value)
            ? $value : null;
    }

    /**
     * Get available feedback statuses
     *
     * @return array
     */
    protected function getStatuses(): array
    {
        return [
            'open',
            'in progress',
            'pending',
            'answered',
            'closed',
        ];
    }
}
