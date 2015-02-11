<?php
/**
 * Feedback Controller
 *
 * PHP version 5
 *
 * @category VuFind2
 * @package  Controller
 * @author   Josiah Knoll <jk1135@ship.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;
use Zend\Mail as Mail;

/**
 * Feedback Class
 *
 * Controls the Feedback
 *
 * @category VuFind2
 * @package  Controller
 * @author   Josiah Knoll <jk1135@ship.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
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
        // no action needed
        return $this->createViewModel();
    }

    /**
     * Receives input from the user and sends an email to the recipient set in
     * the config.ini
     *
     * @return void
     */
    public function emailAction()
    {
        $name = $this->params()->fromPost('name');
        $users_email = $this->params()->fromPost('email');
        $comments = $this->params()->fromPost('comments');

        if (empty($name) || empty($users_email) || empty($comments)) {
            throw new \Exception('Missing data.');
        }
        $validator = new \Zend\Validator\EmailAddress();
        if (!$validator->isValid($users_email)) {
            throw new \Exception('Email address is invalid');
        }

        // These settings are set in the feedback settion of your config.ini
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
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
        if ($comments == "") {
            throw new \Exception('Feedback Module Error: Comment Post Failed');
        }

        $email_message = 'Name: ' . $name . "\n";
        $email_message .= 'Email: ' . $users_email . "\n";
        $email_message .= 'Comments: ' . $comments . "\n";

        // This sets up the email to be sent
        $mail = new Mail\Message();
        $mail->setBody($email_message);
        $mail->setFrom($sender_email, $sender_name);
        $mail->addTo($recipient_email, $recipient_name);
        $mail->setSubject($email_subject);

        $this->getServiceLocator()->get('VuFind\Mailer')->getTransport()
            ->send($mail);
    }
}
