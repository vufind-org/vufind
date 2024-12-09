<?php

/**
 * Controller for configurable forms (feedback etc).
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Controller
 * @author   Josiah Knoll <jk1135@ship.edu>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\Log\LoggerAwareInterface;
use Laminas\View\Model\ViewModel;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Form\Form;
use VuFind\Log\LoggerAwareTrait;

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
class FeedbackController extends AbstractBase implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Feedback form class
     *
     * @var string
     */
    protected $formClass = \VuFind\Form\Form::class;

    /**
     * Display Feedback home form.
     *
     * @return ViewModel
     */
    public function homeAction()
    {
        return $this->forwardTo('Feedback', 'Form');
    }

    /**
     * Handles rendering and submit of dynamic forms.
     * Form configurations are specified in FeedbackForms.yaml.
     *
     * @return mixed
     */
    public function formAction()
    {
        $formId = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        if (!$formId) {
            $formId = 'FeedbackSite';
        }

        $user = $this->getUser();

        $form = $this->getService($this->formClass);
        $prefill = $this->params()->fromQuery();
        $params = [];
        if ($refererHeader = $this->getRequest()->getHeader('Referer')) {
            $params['referrer'] = $refererHeader->getFieldValue();
        }
        if ($userAgentHeader = $this->getRequest()->getHeader('User-Agent')) {
            $params['userAgent'] = $userAgentHeader->getFieldValue();
        }
        $form->setFormId($formId, $params, $prefill);

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

        if (!$this->formWasSubmitted(useCaptcha: $view->useCaptcha)) {
            $form = $this->prefillUserInfo($form, $user);
            return $view;
        }

        if (!$form->isValid()) {
            return $view;
        }

        $primaryHandler = $form->getPrimaryHandler();
        $success = $primaryHandler->handle($form, $params, $user);
        if ($success) {
            $view->setVariable('successMessage', $form->getSubmitResponse());
            $view->setTemplate('feedback/response');
        } else {
            $this->flashMessenger()->addErrorMessage(
                $this->translate('could_not_process_feedback')
            );
        }

        $handlers = $form->getSecondaryHandlers();
        foreach ($handlers as $handler) {
            try {
                $handler->handle($form, $params, $user);
            } catch (\Exception $e) {
                $this->logError($e->getMessage());
            }
        }

        return $view;
    }

    /**
     * Prefill form sender fields for logged in users.
     *
     * @param Form                 $form Form
     * @param ?UserEntityInterface $user User
     *
     * @return Form
     */
    protected function prefillUserInfo(Form $form, ?UserEntityInterface $user)
    {
        if ($user) {
            $form->setData(
                [
                 'name' => $user->getFirstname() . ' ' . $user->getLastname(),
                 'email' => $user->getEmail(),
                ]
            );
        }
        return $form;
    }
}
