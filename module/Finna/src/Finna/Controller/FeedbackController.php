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
}
