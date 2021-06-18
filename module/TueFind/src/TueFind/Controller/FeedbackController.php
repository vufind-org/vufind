<?php

namespace TueFind\Controller;

class FeedbackController extends \VuFind\Controller\FeedbackController
{
    protected $overwritableFields = ['title'];

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
        $params = [];
        if ($refererHeader = $this->getRequest()->getHeader('Referer')
        ) {
            $params['referrer'] = $refererHeader->getFieldValue();
        }
        $form->setFormId($formId, $params);

        if (!$form->isEnabled()) {
            throw new \VuFind\Exception\Forbidden("Form '$formId' is disabled");
        }

        if (!$user && $form->showOnlyForLoggedUsers()) {
            return $this->forceLogin();
        }

        $view = $this->createViewModel(compact('form', 'formId', 'user'));
        $view->useCaptcha
            = $this->captcha()->active('feedback') && $form->useCaptcha();

        $params = $this->params();
        $form->setData($params->fromPost());

        if (!$this->formWasSubmitted('submit', $view->useCaptcha)) {
            $form = $this->prefillUrlInfo($form);
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

        // TueFind: Deny Spam-Mails from @ixtheo.de and other addresses
        if (preg_match('"@ixtheo.de$"i', $replyToEmail)) {
            $this->showResponse($view, $form, false, 'Invalid reply-to address: ' . $replyToEmail);
            return $view;
        }

        $recipients = $form->getRecipient($params->fromPost());

        $emailSubject = $form->getEmailSubject($params->fromPost());

        $sendSuccess = true;
        foreach ($recipients as $recipient) {
            list($success, $errorMsg) = $this->sendEmail(
                $recipient['name'], $recipient['email'], $senderName, $senderEmail,
                $replyToName, $replyToEmail, $emailSubject, $emailMessage
            );

            $sendSuccess = $sendSuccess && $success;
            if (!$success) {
                $this->showResponse(
                    $view, $form, false, $errorMsg
                );
            }
        }

        if ($sendSuccess) {
            $this->showResponse($view, $form, true);
        }

        return $view;
    }

    public function prefillUrlInfo($form) {
        foreach ($this->overwritableFields as $overwritableField) {
            $value = $this->params()->fromQuery($overwritableField);
            if ($value != null) {
                $form->setData([$overwritableField => $value]);
            }
        }
        return $form;
    }
}
