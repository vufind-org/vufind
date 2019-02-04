<?php
/**
 * Controller for configurable forms (feedback etc).
 *
 * PHP version 7
 *
 * @category VuFind
 * @package  Controller
 * @author   Josiah Knoll <jk1135@ship.edu>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Controller;

use VuFind\Exception\Mail as MailException;
use VuFind\Form\Form;
use Zend\Mail\Address;

/**
 * Controller for configurable forms (feedback etc).
 *
 * @category VuFind
 * @package  Controller
 * @author   Josiah Knoll <jk1135@ship.edu>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
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
        return $this->forwardTo('Feedback', 'Form');
    }

    /**
     * Handles rendering and submit of dynamic forms.
     * Form configurations are specified in FeedbackForms.json
     *
     * @return void
     */
    public function formAction()
    {
        $formId = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        if (!$formId) {
            $formId = 'FeedbackSite';
        }

        $user = $this->getUser();

        $form = $this->serviceLocator->get(\VuFind\Form\Form::class);
        $form->setFormId($formId);

        if (!$form->isEnabled()) {
            throw new \VuFind\Exception\Forbidden("Form '$formId' is disabled");
        }

        $view = $this->createViewModel(compact('form', 'formId', 'user'));
        $view->useRecaptcha
            = $this->recaptcha()->active('feedback') && $form->useCaptcha();

        $params = $this->params();
        $form->setData($params->fromPost());

        if (!$this->formWasSubmitted('submit', $view->useRecaptcha)) {
            $form = $this->prefillUserInfo($form, $user);
            return $view;
        }

        if (! $form->isValid()) {
            return $view;
        }

        list($messageParams, $template)
            = $form->formatEmailMessage($this->params()->fromPost());
        $emailMessage = $this->getViewRenderer()->partial(
            $template, ['fields' => $messageParams]
        );

        list($senderName, $senderEmail) = $this->getSender();

        $replyToName = $params->fromPost(
            'name',
            $user ? trim($user->firstname . ' ' . $user->lastname) : null
        );
        $replyToEmail = $params->fromPost(
            'email',
            $user ? $user->email : null
        );

        list($recipientName, $recipientEmail) = $form->getRecipient();

        $emailSubject = $form->getEmailSubject($params->fromPost());

        list($success, $errorMsg) = $this->sendEmail(
            $recipientName, $recipientEmail, $senderName, $senderEmail,
            $replyToName, $replyToEmail, $emailSubject, $emailMessage
        );

        $this->showResponse($view, $form, $success, $errorMsg);

        return $view;
    }

    /**
     * Prefill form sender fields for logged in users.
     *
     * @param Form  $form Form
     * @param array $user User
     *
     * @return Form
     */
    protected function prefillUserInfo($form, $user)
    {
        if ($user) {
            $form->setData(
                [
                 'name' => $user->firstname . ' ' . $user->lastname,
                 'email' => $user['email']
                ]
            );
        }
        return $form;
    }

    /**
     * Send form data as email.
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
        try {
            $mailer = $this->serviceLocator->get(\VuFind\Mailer\Mailer::class);
            if ($replyToEmail) {
                $mailer->setFromAddressOverride('');
            }
            $mailer->send(
                new Address($recipientEmail, $recipientName),
                new Address($senderEmail, $senderName),
                $emailSubject, $emailMessage, null, $replyToEmail
            );
            return [true, null];
        } catch (MailException $e) {
            return [false, $e->getMessage()];
        }
    }

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
            $this->flashMessenger()->addMessage(
                $form->getSubmitResponse(), 'success'
            );
        } else {
            $this->flashMessenger()->addMessage($errorMsg, 'error');
        }
    }

    /**
     * Return email sender from configuration.
     *
     * @return array with name, email
     */
    protected function getSender()
    {
        $config = $this->getConfig()->Feedback;
        $email = $config->sender_email ?? 'noreply@vufind.org';
        $name = $config->sender_name ?? 'VuFind Feedback';

        return [$name, $email];
    }
}
