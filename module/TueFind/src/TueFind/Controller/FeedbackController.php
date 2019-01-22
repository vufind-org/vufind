<?php

namespace TueFind\Controller;

use VuFind\Exception\Mail as MailException;
use Zend\Mail\Address;

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
        $view = $this->createViewModel();
        $view->useRecaptcha = $this->recaptcha()->active('feedback');
        $view->name = $this->params()->fromPost('name');
        $view->email = $this->params()->fromPost('email');
        $view->comments = $this->params()->fromPost('comments');

        // Process form submission:
        if ($this->formWasSubmitted('submit', $view->useRecaptcha)) {
            if (empty($view->email) || empty($view->comments)) {
                $this->flashMessenger()->addMessage('bulk_error_missing', 'error');
                return;
            }

            // These settings are set in the feedback settion of your config.ini
            $config = $this->serviceLocator->get('VuFind\Config\PluginManager')
                ->get('config');
            $feedback = isset($config->Feedback) ? $config->Feedback : null;
            $site = isset($config->Site) ? $config->Site : null;
            $recipient_email = isset($site->email)                  // use Site email (local overrides)
                ? $site->email : null;
            $recipient_name = isset($feedback->recipient_name)
                ? $feedback->recipient_name : 'Your Library';
            $email_subject = isset($feedback->email_subject)
                ? $feedback->email_subject : 'VuFind Feedback';
            $sender_email = isset($site->email_from)                // use Site email_from (local_overrides)
                ? $site->email_from : 'noreply@vufind.org';
            $sender_name = isset($feedback->sender_name)
                ? $feedback->sender_name : 'VuFind Feedback';
            if ($recipient_email == null) {
                throw new \Exception(
                    'Feedback Module Error: Recipient Email Unset (see local_overrides)'
                );
            }

            $email_message = empty($view->name) ? '' : 'Name: ' . $view->name . "\n";
            $email_message .= 'Email: ' . $view->email . "\n";
            $email_message .= 'Comments: ' . $view->comments . "\n\n";
            $email_message .= "----------------------------------------------------------------------------------------------\n";
            $email_message .= "Aktuelle Seite: " . $this->getRequest()->getHeaders("Referer")->getUri() . "\n";
            $email_message .= "Browser:        " . htmlentities($this->getRequest()->getHeaders("User-Agent")->getFieldValue()) . "\n";
            $email_message .= "Cookies:        " . htmlentities($this->getRequest()->getCookie()->getFieldValue()) . "\n";
            $email_message .= "----------------------------------------------------------------------------------------------\n\n";

            // This sets up the email to be sent
            // Attempt to send the email and show an appropriate flash message:
            try {
                $mailer = $this->serviceLocator->get('VuFind\Mailer\Mailer');
                $mailer->send(
                    new Address($recipient_email, $recipient_name),
                    new Address($sender_email, $sender_name),
                    $email_subject, $email_message
                );
                $this->flashMessenger()->addMessage(
                    'Thank you for your feedback.', 'success'
                );
            } catch (MailException $e) {
                $this->flashMessenger()->addMessage($e->getMessage(), 'error');
            }
        }
        return $view;
    }
}
