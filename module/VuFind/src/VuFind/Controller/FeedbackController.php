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

use Laminas\Log\LoggerAwareInterface;
use Laminas\View\Model\ViewModel;
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

        $form = $this->serviceLocator->get($this->formClass);
        $params = [];
        if ($refererHeader = $this->getRequest()->getHeader('Referer')) {
            $params['referrer'] = $refererHeader->getFieldValue();
        }
        if ($userAgentHeader = $this->getRequest()->getHeader('User-Agent')) {
            $params['userAgent'] = $userAgentHeader->getFieldValue();
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
            $form = $this->prefillUserInfo($form, $user);
            return $view;
        }

        if (!$form->isValid()) {
            return $view;
        }

        $handlers = $form->getHandlers();
        $results = [];
        $success = false;
        foreach ($handlers as $handler) {
            $result = $handler->handle($form, $params, $user ?: null);
            $success = $success || ($result['success'] ?? false);
            $results[] = $result;
        }

        if ($success) {
            $view->setTemplate('feedback/response');
        }
        $successMessages = [];
        $errorMessages = [];
        $errorMessagesDetailed = [];
        foreach ($results as $result) {
            $successMessages[]
                = $result['successMessage'] ?? $form->getSubmitResponse();
            $errorMessages = array_merge(
                $errorMessages,
                $result['errorMessages'] ?? []
            );
            $errorMessagesDetailed = array_merge(
                $errorMessagesDetailed,
                $result['errorMessagesDetailed'] ?? []
            );
        }
        $view->successMessages = array_unique($successMessages);
        foreach ($errorMessages as $error) {
            $this->flashMessenger()->addErrorMessage($this->translate($error));
        }
        foreach ($errorMessagesDetailed as $error) {
            $this->logError(
                'Error processing form data for ' . "'$formId'" . ': ' . $error
            );
        }
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
}
