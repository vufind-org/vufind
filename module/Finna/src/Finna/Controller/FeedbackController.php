<?php
/**
 * Feedback Controller
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2019.
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
 * PHP version 7
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

use Finna\Form\Form;

/**
 * Feedback Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class FeedbackController extends \VuFind\Controller\FeedbackController
{
    /**
     * True if form was submitted successfully.
     *
     * @var bool
     */
    protected $submitOk = false;

    /**
     * Show response after form submit.
     *
     * @param View    $view     View
     * @param Form    $form     Form
     * @param boolean $success  Was email sent successfully?
     * @param string  $errorMsg Error message (optional)
     *
     * @return array with name, email
     */
    protected function showResponse($view, $form, $success, $errorMsg = null)
    {
        if ($success) {
            $this->submitOk = true;
        }
        parent::showResponse($view, $form, $success, $errorMsg);
    }

    /**
     * Handles rendering and submit of dynamic forms.
     * Form configurations are specified in FeedbackForms.json
     *
     * @return void
     */
    public function formAction()
    {
        $view = parent::formAction();

        if ($this->params()->fromPost('forcingLogin', false)) {
            // Parent response is a forced login for a non-logged user. Return it.
            return $view;
        }

        // Set record driver (used by FeedbackRecord form)
        $data = $this->getRequest()->getQuery('data', []);

        if ($id = ($this->getRequest()->getPost(
            'record_id',
            $this->getRequest()->getQuery('record_id')
        ))
        ) {
            list($source, $recId) = explode('|', $id, 2);
            $view->form->setRecord($this->getRecordLoader()->load($recId, $source));
            $data['record_id'] = $id;
        }
        $view->form->populateValues($data);

        if (!$this->submitOk) {
            return $view;
        }

        // Reset flashmessages set by VuFind
        $msg = $this->flashMessenger();
        $namespaces = ['error', 'info', 'success'];
        foreach ($namespaces as $ns) {
            $msg->setNamespace($ns);
            $msg->clearCurrentMessages();
        }

        $view->setTemplate('feedback/response');
        return $view;
    }

    /**
     * Legacy support for locally customized forms.
     *
     * @return void
     */
    public function emailAction()
    {
        $post = $this->getRequest()->getPost();
        $post->set('message', $post->get('comments'));

        return $this->forwardTo('Feedback', 'Form');
    }

    /**
     * Send submitted form data via email or save the data to the database.
     *
     * @param string $recipientName  Recipient name
     * @param string $recipientEmail Recipient email
     * @param string $senderName     Sender name
     * @param string $senderEmail    Sender email
     * @param string $replyToName    Reply-to name
     * @param string $replyToEmail   Reply-to email
     * @param string $emailSubject   Email subject
     * @param string $emailMessage   Email message
     *
     * @return array with elements success:boolean, errorMessage:string (optional)
     */
    protected function sendEmail(
        $recipientName, $recipientEmail, $senderName, $senderEmail,
        $replyToName, $replyToEmail, $emailSubject, $emailMessage
    ) {
        $formId = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        if (!$formId) {
            $formId = 'FeedbackSite';
        }
        $form = $this->serviceLocator->get(\VuFind\Form\Form::class);
        $form->setFormId($formId);

        if ($formId === 'FeedbackRecord') {
            // Resolve recipient email from datasource configuration
            // when sending feedback on a record
            if ($id = ($this->getRequest()->getPost(
                'record_id',
                $this->getRequest()->getQuery('record_id')
            ))
            ) {
                list($source, $recId) = explode('|', $id, 2);
                $driver = $this->getRecordLoader()->load($recId, $source);
                $dataSource = $driver->getDataSource();
                $dataSources = $this->serviceLocator
                    ->get(\VuFind\Config\PluginManager::class)->get('datasources');
                $inst = $dataSources->$dataSource ?? null;
                $recipientEmail = isset($inst->feedbackEmail) ?
                    $inst->feedbackEmail : null;
                if ($recipientEmail == null) {
                    throw new \Exception(
                        'Error sending record feedback:'
                        . 'Recipient Email Unset (see datasources.ini)'
                    );
                }
            }
        }

        if ($form->useEmailHandler()) {
            return parent::sendEmail(...func_get_args());
        }

        // Save to database
        $user = $this->getUser();
        $userId = $user ? $user->id : null;

        $url = rtrim($this->getServerUrl('home'), '/');
        $url = substr($url, strpos($url, '://') + 3);

        $formFields = $form->getFormFields();

        $save = [];
        $params = (array)$this->params()->fromPost();
        foreach ($params as $key => $val) {
            if (! in_array($key, $formFields)) {
                continue;
            }
            $save[$key] = $val;
        }
        $save['emailSubject'] = $emailSubject;
        $messageJson = json_encode($save);

        $message
            = $emailSubject . PHP_EOL . '-----' . PHP_EOL . PHP_EOL . $emailMessage;

        $feedback = $this->getTable('Feedback');
        $feedback->saveFeedback(
            $url, $formId, $userId, $message, $messageJson
        );

        return [true, null];
    }
}
