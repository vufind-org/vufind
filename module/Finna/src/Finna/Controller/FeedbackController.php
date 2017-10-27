<?php
/**
 * Feedback Controller
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2017.
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
 * PHP version 5
 *
 * @category VuFind
 * @package  Controller
 * @author   Josiah Knoll <jk1135@ship.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

use Zend\Mail as Mail;

/**
 * Feedback Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Josiah Knoll <jk1135@ship.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class FeedbackController extends \VuFind\Controller\FeedbackController
{
    /**
     * Receives input from the user and sends an email to the recipient set in
     * the config.ini
     *
     * @return void
     */
    public function emailAction()
    {
        $user = $this->getUser();

        $view = $this->createViewModel();
        $view->useRecaptcha = $this->recaptcha()->active('feedback');
        $view->category = $this->params()->fromPost(
            'category', $this->params()->fromQuery('category')
        );
        $view->name = $this->params()->fromPost(
            'name',
            $user ? trim($user->firstname . ' ' . $user->lastname) : ''
        );
        $view->users_email = $this->params()->fromPost(
            'email',
            $user ? $user->email : ''
        );
        $view->comments = $this->params()->fromPost(
            'comments', $this->params()->fromQuery('comments')
        );
        $view->url = $this->params()->fromPost(
            'url', $this->params()->fromQuery('url')
        );
        $captcha = $this->params()->fromPost('captcha');

        // Support the old captcha mechanism for now
        if ($captcha == $this->translate('feedback_captcha_answer')) {
            $view->useRecaptcha = false;
        }

        // Process form submission:
        if ($this->formWasSubmitted('submit', $view->useRecaptcha)) {
            if (empty($view->comments)) {
                throw new \Exception('Missing data.');
            }
            $validator = new \Zend\Validator\EmailAddress();
            if (!empty($view->users_email)
                && !$validator->isValid($view->users_email)
            ) {
                throw new \Exception('Email address is invalid');
            }

            // These settings are set in the feedback section of your config.ini
            $config = $this->serviceLocator->get('VuFind\Config')
                ->get('config');
            $feedback = isset($config->Feedback) ? $config->Feedback : null;
            $recipient_email = !empty($feedback->recipient_email)
                ? $feedback->recipient_email : $config->Site->email;
            $recipient_name = isset($feedback->recipient_name)
                ? $feedback->recipient_name : 'Your Library';
            $email_subject = isset($feedback->email_subject)
                ? $feedback->email_subject : 'VuFind Feedback';
            $email_subject .= ' (' . $this->translate($view->category) . ')';
            $sender_email = isset($feedback->sender_email)
                ? $feedback->sender_email : 'noreply@vufind.org';
            $sender_name = isset($feedback->sender_name)
                ? $feedback->sender_name : 'VuFind Feedback';
            if ($recipient_email == null) {
                throw new \Exception(
                    'Feedback Module Error: Recipient Email Unset (see config.ini)'
                );
            }

            $email_message = $this->translate('feedback_category') . ': '
                . $this->translate($view->category) . "\n";
            $email_message .= $this->translate('feedback_name') . ': '
                . ($view->name ? $view->name : '-') . "\n";
            $email_message .= $this->translate('feedback_email') . ': '
                . ($view->users_email ? $view->users_email : '-') . "\n";
            $email_message .= $this->translate('feedback_url') . ': '
                . ($view->url ? $view->url : '-') . "\n";
            if ($user) {
                $loginMethod = $this->translate(
                    'login_method_' . $user->finna_auth_method,
                    null,
                    $user->finna_auth_method
                );
                $email_message .= $this->translate('feedback_user_login_method')
                    . ": $loginMethod\n";
            } else {
                $email_message .= $this->translate('feedback_user_anonymous') . "\n";
            }
            $permissionManager
                = $this->serviceLocator->get('VuFind\Role\PermissionManager');
            $roles = $permissionManager->getActivePermissions();
            $email_message .= $this->translate('feedback_user_roles') . ': '
                . implode(', ', $roles) . "\n";

            $email_message .= "\n" . $this->translate('feedback_message') . ":\n";
            $email_message .= "----------\n\n$view->comments\n\n----------\n";

            // This sets up the email to be sent
            $mail = new Mail\Message();
            $mail->setEncoding('UTF-8');
            $mail->setBody($email_message);
            $mail->setFrom($sender_email, $sender_name);
            if (!empty($view->users_email)) {
                $mail->setReplyTo($view->users_email, $view->name);
            }
            $mail->addTo($recipient_email, $recipient_name);
            $mail->setSubject($email_subject);
            $headers = $mail->getHeaders();
            $headers->removeHeader('Content-Type');
            $headers->addHeaderLine('Content-Type', 'text/plain; charset=UTF-8');

            try {
                $this->serviceLocator->get('VuFind\Mailer')->getTransport()
                    ->send($mail);
                $view->setTemplate('feedback/response');
            } catch (\Exception $e) {
                $this->flashMessenger()->addErrorMessage('feedback_error');
            }
        }
        return $view;
    }
}
