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

        $config = $this->serviceLocator->get('VuFind\Config\PluginManager')
            ->get('config');
        $translator = $this->serviceLocator->get('VuFind\Translator');
        $user = $this->getUser();

        $form = new Form($formId, $config['Feedback'] ?? null, $translator, $user);
        if (!$form->isEnabled()) {
            throw new \Exception("Form '$formId' is disabled");
        }

        $view = $this->createViewModel();
        $view->useRecaptcha = $this->recaptcha()->active('feedback');
        $view->form = $form;
        $view->formId = $formId;
        $view->user = $user;

        if (!$this->formWasSubmitted('submit', $view->useRecaptcha)) {
            $form = $this->prefillUserInfo($form, $user);
            return $view;
        }

        $params = $this->params();
        $form->setData($params->fromPost());

        if (! $form->isValid()) {
            return $view;
        }

        list($messageParams, $template) = $form->formatEmailMessage($this->params());
        $emailMessage = $this->getViewRenderer()->partial(
            $template, ['fields' => $messageParams]
        );

        list($senderName, $senderEmail) = $this->getSender();

        $replyToName = $replyToEmail = null;
        $replyToName = $params->fromPost(
            '__name__',
            $user ? trim($user->firstname . ' ' . $user->lastname) : null
        );
        $replyToEmail = $params->fromPost(
            '__email__',
            $user ? $user->email : null
        );

        list($recipientName, $recipientEmail) = $form->getRecipient();

        // Translate form variables for email subject
        $translated = [];
        foreach ($params->fromPost() as $key => $val) {
            $translated["%%{$key}%%"] = $translator->translate($val);
        }
        $emailSubject = $this->translate($form->getEmailSubject(), $translated);

        list($success, $errorMsg)= $this->sendEmail(
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
                 '__name__' => $user->firstname . ' ' . $user->lastname,
                 '__email__' => $user['email']
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
            $mailer = $this->serviceLocator->get('VuFind\Mailer\Mailer');
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
        $email = isset($config->sender_email)
            ? $config->sender_email : 'noreply@vufind.org';
        $name = isset($config->sender_name)
            ? $config->sender_name : 'VuFind Feedback';

        return [$name, $email];
    }
}
