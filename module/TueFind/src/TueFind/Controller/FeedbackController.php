<?php

namespace TueFind\Controller;

class FeedbackController extends \VuFind\Controller\FeedbackController
{
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

        $form = $this->serviceLocator->get('VuFind\Form\Form');
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
        $emailMessage .= "----------------------------------------------------------------------------------------------\n";
        $emailMessage .= "Aktuelle Seite: " . $this->getRequest()->getHeaders("Referer")->getUri() . "\n";
        $emailMessage .= "Browser:        " . htmlentities($this->getRequest()->getHeaders("User-Agent")->getFieldValue()) . "\n";
        $emailMessage .= "Cookies:        " . htmlentities($this->getRequest()->getCookie()->getFieldValue()) . "\n";
        $emailMessage .= "----------------------------------------------------------------------------------------------\n\n";

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
}
