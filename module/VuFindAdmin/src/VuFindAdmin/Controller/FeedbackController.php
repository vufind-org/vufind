<?php
declare(strict_types=1);

/**
 * Class FeedbackController
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2022.
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

use Laminas\Db\Sql\Select;
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
     * Params
     *
     * @var array
     */
    protected $params;

    /**
     * Get the url parameters
     *
     * @param string $param A key to check the url params for
     *
     * @return string
     */
    protected function getParam($param)
    {
        return $this->params[$param] ?? $this->params()->fromPost(
            $param,
            $this->params()->fromQuery($param, null)
        );
    }

    /**
     * Home action
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        $this->params = $this->params()->fromQuery();
        $view = $this->createViewModel();
        $view->setTemplate('admin/feedback/home');
        $feedbackTable = $this->getFeedbackTable();
        $feedback = $feedbackTable->getFeedbackByFilter(
            $this->convertFilter($this->getParam('form_name')),
            $this->convertFilter($this->getParam('site_url')),
            $this->convertFilter($this->getParam('status'))
        );
        $view->setVariables(
            [
                'feedback' => $feedback,
                'statuses' => $feedbackTable->getStatuses(),
                'uniqueForms' => $this->getUniqueColumn('form_name'),
                'uniqueSites' => $this->getUniqueColumn('site_url'),
                'params' => $this->params
            ]
        );
        return $view;
    }

    /**
     * Delete action
     *
     * @return \Laminas\Http\Response
     */
    public function deleteAction()
    {
        $this->params = $this->params()->fromPost();
        $confirm = $this->getParam('confirm');
        $feedbackTable = $this->getFeedbackTable();
        $originUrl = $this->url()->fromRoute('admin/feedback');
        $formName = $this->getParam('form_name');
        $siteUrl = $this->getParam('site_url');
        $status = $this->getParam('status');
        $originUrl .= '?' . http_build_query(
            [
                'form_name' => empty($formName) ? 'ALL' : $formName,
                'site_url' => empty($siteUrl) ? 'ALL' : $siteUrl,
                'status' => empty($status) ? 'ALL' : $status,
            ]
        );
        $newUrl = $this->url()->fromRoute('admin/feedback', ['action' => 'Delete']);

        $ids = null === $this->getRequest()->getPost('deletePage')
            ? $this->params()->fromPost('ids')
            : $this->params()->fromPost('idsAll');

        if (!is_array($ids) || empty($ids)) {
            $this->flashMessenger()->addMessage('bulk_noitems_advice', 'error');
            return $this->redirect()->toUrl($originUrl);
        }
        if (!$confirm) {
            return $this->confirmDelete($ids, $originUrl, $newUrl);
        }
        $delete = $feedbackTable->deleteByIdArray($ids);
        if (0 == $delete) {
            $this->flashMessenger()->addMessage('feedback_delete_failure', 'error');
            return $this->redirect()->toUrl($originUrl);
        }
        $this->flashMessenger()->addMessage(
            [
                'msg' => 'feedback_delete_success',
                'tokens' => ['%count%' => $delete]
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
        $count = count($ids);

        $data = [
            'data' => [
                'confirm' => $newUrl,
                'cancel' => $originUrl,
                'title' => "confirm_delete_feedback",
                'messages' => $this->getConfirmDeleteMessages($count),
                'ids' => $ids,
                'extras' => [
                    'form_name' => $this->getParam('form_name'),
                    'site_url' => $this->getParam('site_url'),
                    'status' => $this->getParam('status'),
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

        $formName = $this->getParam('form_name');
        $formMessage = $formName ?: $allMessage;
        $formMessage = $formMessage === "ALL" ? $allMessage : $formMessage;

        $siteUrl = $this->getParam('site_url');
        $siteMessage = $siteUrl ?: $allMessage;
        $siteMessage = $siteMessage === "ALL" ? $allMessage : $siteMessage;

        $status = $this->getParam('status');
        $statusMessage = $status ?: $allMessage;
        $statusMessage = $statusMessage === "ALL" ? $allMessage : $statusMessage;

        $messages = [
            [
                'msg' => 'feedback_delete_warning',
                'tokens' => ['%count%' => $count]
            ]
        ];
        if ($formName || $siteUrl || $status) {
            $messages[] = [
                'msg' => 'feedback_delete_filter',
                'tokens' => [
                    '%formname%' => $formMessage,
                    '%siteurl%' => $siteMessage,
                    '%status%' => $statusMessage,
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
        $this->params = $this->params()->fromPost();
        $feedbackTable = $this->getFeedbackTable();
        $status = $this->getParam('status');
        $id = $this->getParam('id');
        $feedback = $feedbackTable->select(['id' => $id])->current();
        $feedback->status = $status;
        $success = $feedback->save();
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
        return $this->redirect()->toRoute('admin/feedback');
    }

    /**
     * Get Feedback table
     *
     * @return Feedback
     */
    protected function getFeedbackTable(): Feedback
    {
        return $this->getTable(Feedback::class);
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
        $feedbackTable = $this->getFeedbackTable();
        $feedback = $feedbackTable->select(
            function (Select $select) use ($column) {
                $select->columns(['id', $column]);
                $select->order($column);
            }
        );
        $feedbackArray = $feedback->toArray();
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
}
