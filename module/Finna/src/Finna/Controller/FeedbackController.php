<?php
/**
 * Feedback Controller
 *
 * PHP version 5
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
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
        $category = $this->params()->fromPost('category');
        $name = $this->params()->fromPost('name');
        $users_email = $this->params()->fromPost('email');
        $comments = $this->params()->fromPost('comments');
        $url = $this->params()->fromPost('url');
        $captcha = $this->params()->fromPost('captcha');

        if (empty($captcha)
            || $captcha != $this->translate('feedback_captcha_answer')
        ) {
            $view = $this->createViewModel();
            $view->setTemplate('feedback/home');
            $view->category = $category;
            $view->name = $name;
            $view->email = $users_email;
            $view->comments = $comments;
            $view->url = $url;
            $this->flashMessenger()->addErrorMessage('feedback_captcha_error');
            return $view;
        }
        if (empty($comments)) {
            throw new \Exception('Missing data.');
        }
        $validator = new \Zend\Validator\EmailAddress();
        if (!empty($users_email) && !$validator->isValid($users_email)) {
            throw new \Exception('Email address is invalid');
        }

        // These settings are set in the feedback settion of your config.ini
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        $feedback = isset($config->Feedback) ? $config->Feedback : null;
        $recipient_email = !empty($feedback->recipient_email)
            ? $feedback->recipient_email : $config->Site->email;
        $recipient_name = isset($feedback->recipient_name)
            ? $feedback->recipient_name : 'Your Library';
        $email_subject = isset($feedback->email_subject)
            ? $feedback->email_subject : 'VuFind Feedback';
        $email_subject .= ' (' .  $this->translate($category) . ')';
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
            . $this->translate($category) . "\n";
        $email_message .= $this->translate('feedback_name') . ': '
            . ($name ? $name : '-') . "\n";
        $email_message .= $this->translate('feedback_email') . ': '
            . ($users_email ? $users_email : '-') . "\n";
        $email_message .= $this->translate('feedback_url') . ': '
            . ($url ? $url : '-') . "\n";
        $email_message .= "\n" . $this->translate('feedback_message') . ":\n";
        $email_message .= "----------\n\n$comments\n\n---------\n";

        // This sets up the email to be sent
        $mail = new Mail\Message();
        $mail->setEncoding('UTF-8');
        $mail->setBody($email_message);
        $mail->setFrom($sender_email, $sender_name);
        $mail->addTo($recipient_email, $recipient_name);
        $mail->setSubject($email_subject);
        $headers = $mail->getHeaders();
        $headers->removeHeader('Content-Type');
        $headers->addHeaderLine('Content-Type', 'text/plain; charset=UTF-8');

        $view = $this->createViewModel();
        try {
            $this->getServiceLocator()->get('VuFind\Mailer')->getTransport()
                ->send($mail);
            $view->setTemplate('feedback/response');
        } catch (\Exception $e) {
            $this->flashMessenger()->addErrorMessage('feedback_error');
        }
        return $view;
    }
}
