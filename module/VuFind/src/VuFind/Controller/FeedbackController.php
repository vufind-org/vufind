<?php
/**
 * Feedback Controller
 *
 * PHP version 5
 *
 * @category VuFind
 * @package  Controller
 * @author   Josiah Knoll <jk1135@ship.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Controller;
use Zend\Mail as Mail;

/**
 * Feedback Class
 *
 * Controls the Feedback
 *
 * @category VuFind
 * @package  Controller
 * @author   Josiah Knoll <jk1135@ship.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FeedbackController extends AbstractBase
{
    /**
     * Display Feedback home form.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        $view = $this->createViewModel();
        $view->useRecaptcha = $this->recaptcha()->active('feedback');
        return $view;
    }

    /**
     * Receives input from the user and sends an email to the recipient set in
     * the config.ini
     *
     * @return void
     */
    public function emailAction()
    {
        $useRecaptcha = $this->recaptcha()->active('feedback');
        // Process form submission:
        if ($this->formWasSubmitted('submit', $useRecaptcha)) {
            $name = $this->params()->fromPost('name');
            $users_email = $this->params()->fromPost('email');
            $comments = $this->params()->fromPost('comments');

            if (empty($users_email) || empty($comments)) {
                $this->flashMessenger()->addMessage('bulk_error_missing', 'error');
                return;
            }

            // These settings are set in the feedback settion of your config.ini
            $config = $this->getServiceLocator()->get('VuFind\Config')
                ->get('config');
            $feedback = isset($config->Feedback) ? $config->Feedback : null;
            $recipient_name = isset($feedback->recipient_name)
                ? $feedback->recipient_name : 'Your Library';
            $recipient_email = isset($feedback->recipient_email)
                ? $feedback->recipient_email : null;
            $sender_name = isset($feedback->sender_name)
                ? $feedback->sender_name : 'VuFind Feedback';
            $sender_email = isset($feedback->sender_email)
                ? $feedback->sender_email : 'noreply@vufind.org';
            $email_subject = isset($feedback->email_subject)
                ? $feedback->email_subject : 'VuFind Feedback';
            if ($recipient_email == null) {
                throw new \Exception(
                    'Feedback Module Error: Recipient Email Unset (see config.ini)'
                );
            }

            $email_message = 'Dear  ' . $recipient_name . ",\n\n";
            if (!empty($name)) {
                $email_message = 'Name: ' . $name . "\n";
            }
            $email_message .= 'Email: ' . $users_email . "\n";
            $email_message .= 'Comments: ' . $comments . "\n\n";
            $email_message .= '- ' . $sender_name;

            // This sets up the email to be sent
            // Attempt to send the email and show an appropriate flash message:
            try {
                $mailer = $this->getServiceLocator()->get('VuFind\Mailer');
                $mailer->send(
                    $recipient_email, $sender_email, $email_subject, $email_message
                );
                $this->flashMessenger()->addMessage(
                    'Thank you for your feedback.', 'success'
                );
            } catch (MailException $e) {
                $this->flashMessenger()->addMessage($e->getMessage(), 'error');
            }
        } else {
            $this->flashMessenger()->addMessage('recaptcha_not_passed', 'error');
        }
        return $this->homeAction();
    }
}
