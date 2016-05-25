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
use Zend\Mail\Address;

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
        return $this->forwardTo('Feedback', 'Email');
    }

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
            $config = $this->getServiceLocator()->get('VuFind\Config')
                ->get('config');
            $feedback = isset($config->Feedback) ? $config->Feedback : null;
            $recipient_email = isset($feedback->recipient_email)
                ? $feedback->recipient_email : null;
            $recipient_name = isset($feedback->recipient_name)
                ? $feedback->recipient_name : 'Your Library';
            $email_subject = isset($feedback->email_subject)
                ? $feedback->email_subject : 'VuFind Feedback';
            $sender_email = isset($feedback->sender_email)
                ? $feedback->sender_email : 'noreply@vufind.org';
            $sender_name = isset($feedback->sender_name)
                ? $feedback->sender_name : 'VuFind Feedback';
            if ($recipient_email == null) {
                throw new \Exception(
                    'Feedback Module Error: Recipient Email Unset (see config.ini)'
                );
            }

            $email_message = empty($view->name) ? '' : 'Name: ' . $view->name . "\n";
            $email_message .= 'Email: ' . $view->email . "\n";
            $email_message .= 'Comments: ' . $view->comments . "\n\n";

            // This sets up the email to be sent
            // Attempt to send the email and show an appropriate flash message:
            try {
                $mailer = $this->getServiceLocator()->get('VuFind\Mailer');
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
