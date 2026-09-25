<?php

/**
 * Email search action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2026.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Search;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\ContextHelper;
use VuFind\ActionHelper\EmailHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\ActionHelper\UrlHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Captcha\Service\CaptchaService;
use VuFind\Config\AccountCapabilities;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Exception\Mail as MailException;
use VuFind\Mailer\Mailer;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;

/**
 * Email search action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EmailAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param Mailer              $mailer              Mailer
     * @param AccountCapabilities $accountCapabilities Account capabilities
     * @param CaptchaService      $captchaService      Captcha service
     * @param AuthManager         $authManager         Authentication manager
     * @param FollowupHelper      $followupHelper      Followup helper
     */
    #[Autowire]
    public function __construct(
        protected Mailer $mailer,
        protected AccountCapabilities $accountCapabilities,
        protected CaptchaService $captchaService,
        protected AuthManager $authManager,
        protected FollowupHelper $followupHelper,
    ) {
        parent::__construct();
    }

    /**
     * Send search by email.
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
        // If a URL was explicitly passed in, use that; otherwise, try to find the HTTP referrer:
        $url = $this->getPostOrQueryParam('url')
            ?? $this->getHelper(ContextHelper::class)->getReferrer($request)
            ?? null;
        if (!$url || !$this->getHelper(UrlHelper::class)->isLocalUrl($url)) {
            throw new \Exception('Unexpected value passed to emailAction: ' . ($url ?? '<null>'));
        }

        $templateParams = $this->getHelper(EmailHelper::class)->createEmailTemplateParams(
            $request,
            defaultSubject: $this->mailer->getDefaultLinkSubject()
        );
        $this->mailer->setMaxRecipients($templateParams['maxRecipients']);

        // Set up Captcha
        $templateParams['useCaptcha'] = $this->captchaService->active('email');

        $emailActionSettings = $this->accountCapabilities->getEmailActionSetting();
        if ($emailActionSettings === 'disabled') {
            throw new ForbiddenException('Email action disabled');
        }
        // Force login if necessary:
        if (
            $emailActionSettings !== 'enabled'
            && !$this->authManager->getUserObject()
        ) {
            return $this->getHelper(LoginHelper::class)
                ->forceLogin($request, $response, extras: ['emailurl' => $url]);
        }

        // Check if we have a URL in login followup data -- this should override any existing referrer to avoid emailing
        // a login-related URL!
        $followupUrl = $this->followupHelper->retrieveAndClear('emailurl');
        if ($followupUrl) {
            $url = $followupUrl;
        }

        // Fail if we can't figure out a URL to share:
        if (!$url) {
            throw new \Exception('Cannot determine URL to share.');
        }

        $templateParams['url'] = $url;

        // Process form submission:
        if (
            $this->getHelper(FormHelper::class)->formWasSubmitted($request, useCaptcha: $templateParams['useCaptcha'])
        ) {
            // Attempt to send the email and show an appropriate flash message:
            $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
            try {
                // If we got this far, we're ready to send the email:
                $cc = $this->getPostParam('ccself') && $templateParams['from'] !== $templateParams['to']
                    ? $templateParams['from'] : null;
                $this->mailer->sendLink(
                    $templateParams['to'],
                    $templateParams['from'],
                    $templateParams['message'],
                    $templateParams['url'],
                    $templateParams['subject'],
                    $cc
                );
                $flashMessagesHelper->addSuccessMessage('email_success');
                return $this->getHelper(RedirectHelper::class)->redirectToUrl($response, $templateParams['url']);
            } catch (MailException $e) {
                $flashMessagesHelper->addErrorMessage($e->getDisplayMessage());
            }
        }
        return $this->renderTemplate($request, $response, $templateParams, 'search/email');
    }
}
