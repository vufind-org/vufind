<?php

/**
 * Feedback form action.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2026.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Feedback;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Captcha\Service\CaptchaService;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Form\Form;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Feedback form action.
 *
 * @category VuFind
 * @package  Action
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class FormAction extends AbstractTemplateRenderingAction
{
    /**
     * Feedback form class.
     *
     * @var string
     */
    protected $formClass = \VuFind\Form\Form::class;

    /**
     * Constructor.
     *
     * @param AuthManager              $authManager    Authentication manager
     * @param Form                     $form           Form
     * @param protected CaptchaService $captchaService Captcha service
     * @param array                    $config         VuFind configuration
     */
    public function __construct(
        protected AuthManager $authManager,
        protected Form $form,
        protected CaptchaService $captchaService,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
        parent::__construct();
    }

    /**
     * Display feedback form or handle form submission.
     *
     * Form configurations are specified in FeedbackForms.yaml.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $formId = $this->getRouteParam('id') ?? $this->getQueryParam('id');
        if (!$formId) {
            $formId = 'FeedbackSite';
        }

        $user = $this->authManager->getUserObject();

        $prefill = $request->getQueryParams();
        $params = [];
        if ($referer = $request->getHeader('Referer')[0] ?? null) {
            $params['referrer'] = $referer;
        }
        if ($userAgent = $request->getHeader('User-Agent')[0] ?? null) {
            $params['userAgent'] = $userAgent;
        }
        $form = clone $this->form;
        $form->setFormId($formId, $params, $prefill);

        if (!$form->isEnabled()) {
            throw new \VuFind\Exception\Forbidden("Form '$formId' is disabled");
        }

        if (!$user && $form->showOnlyForLoggedUsers()) {
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }

        $templateParams = compact('form', 'formId', 'user');
        $templateParams['useCaptcha'] = $form->useCaptcha() && $this->captchaService->active('feedback');

        $form->setData($request->getParsedBody());

        $template = null;
        if (
            !$this->getHelper(FormHelper::class)->formWasSubmitted($request, useCaptcha: $templateParams['useCaptcha'])
        ) {
            $this->prefillUserInfo($form, $user);
        } elseif ($form->isValid()) {
            if ($this->senderIsBlocked($form)) {
                $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('could_not_process_feedback');
            } elseif ($this->senderIsIgnored($form)) {
                $templateParams['successMessage'] = $form->getSubmitResponse();
                $template = 'feedback/response';
            } else {
                $primaryHandler = $form->getPrimaryHandler();
                $success = $primaryHandler->handle($form, $request, $user);
                if ($success) {
                    $templateParams['successMessage'] = $form->getSubmitResponse();
                    $template = 'feedback/response';
                } else {
                    $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('could_not_process_feedback');
                }

                $handlers = $form->getSecondaryHandlers();
                foreach ($handlers as $handler) {
                    try {
                        $handler->handle($form, $request, $user);
                    } catch (\Exception $e) {
                        $this->logError($e->getMessage());
                    }
                }
            }
        }

        return $this->renderTemplate($request, $response, $templateParams, $template);
    }

    /**
     * Prefill form sender fields for logged in users.
     *
     * @param Form                 $form Form
     * @param ?UserEntityInterface $user User
     *
     * @return Form
     */
    protected function prefillUserInfo(Form $form, ?UserEntityInterface $user): Form
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

    /**
     * Check if sender email is blocked.
     *
     * @param Form $form Form
     *
     * @return bool
     */
    protected function senderIsBlocked(Form $form): bool
    {
        return $this->senderEmailMatchesPattern($form, (array)($this->config['Feedback']['blocked_senders'] ?? []));
    }

    /**
     * Check if sender email is ignored.
     *
     * @param Form $form Form
     *
     * @return bool
     */
    protected function senderIsIgnored(Form $form): bool
    {
        return $this->senderEmailMatchesPattern($form, (array)($this->config['Feedback']['ignored_senders'] ?? []));
    }

    /**
     * Check if an email address matches any of the given patterns.
     *
     * @param Form  $form     Form
     * @param array $patterns Patterns (substring or regexp)
     *
     * @return bool
     */
    protected function senderEmailMatchesPattern(Form $form, array $patterns): bool
    {
        $email = $form->getData()['email'] ?? '';
        foreach ($patterns as $pattern) {
            if (str_starts_with($pattern, '/') && str_ends_with($pattern, '/')) {
                if (preg_match($pattern, $email)) {
                    return true;
                }
            } elseif (str_contains($email, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
