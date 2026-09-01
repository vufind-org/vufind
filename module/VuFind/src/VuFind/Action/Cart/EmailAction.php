<?php

/**
 * Cart email action.
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

namespace VuFind\Action\Cart;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\BulkActionHelper;
use VuFind\ActionHelper\EmailHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Captcha\Service\CaptchaService;
use VuFind\Cart;
use VuFind\Config\AccountCapabilities;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Exception\Mail as MailException;
use VuFind\Export;
use VuFind\Http\ServerUrlHelper;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\Mailer\Mailer;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Session\Helper\FollowupHelper;

use function count;
use function is_array;

/**
 * Cart email action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EmailAction extends AbstractCartAction implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Constructor.
     *
     * @param Export              $export              Export handler
     * @param Cart                $cart                Cart handler
     * @param FollowupHelper      $followupHelper      Followup helper
     * @param AccountCapabilities $accountCapabilities Account capabilities
     * @param Mailer              $mailer              Mailer
     * @param AuthManager         $authManager         Authentication manager
     * @param RecordLoader        $recordLoader        Record loader
     * @param CaptchaService      $captchaService      Captcha service
     * @param ServerUrlHelper     $serverUrlHelper     Server URL helper
     */
    public function __construct(
        Export $export,
        Cart $cart,
        FollowupHelper $followupHelper,
        protected AccountCapabilities $accountCapabilities,
        protected Mailer $mailer,
        protected AuthManager $authManager,
        protected RecordLoader $recordLoader,
        protected CaptchaService $captchaService,
        protected ServerUrlHelper $serverUrlHelper,
    ) {
        parent::__construct($export, $cart, $followupHelper);
    }

    /**
     * Email cart.
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
        // Retrieve ID list:
        $bulkActionHelper = $this->getHelper(BulkActionHelper::class);
        $ids = $bulkActionHelper->getSelectedIds($request);

        // Retrieve follow-up information if necessary:
        if (!$ids) {
            $ids = $this->followupHelper->retrieveAndClear('cartIds') ?? [];
        }
        $actionLimit = $bulkActionHelper->getBulkActionLimit('email');
        if (!is_array($ids) || empty($ids)) {
            if ($redirect = $bulkActionHelper->redirectToSource($request, $response, 'error', 'bulk_noitems_advice')) {
                return $redirect;
            }
            $submitDisabled = true;
        } elseif (count($ids) > $actionLimit) {
            $errorMsg = [
                'msg' => 'bulk_limit_exceeded',
                'tokens' => ['%%count%%' => count($ids), '%%limit%%' => $actionLimit],
            ];
            if ($redirect = $bulkActionHelper->redirectToSource($request, $response, 'error', $errorMsg)) {
                return $redirect;
            }
            $submitDisabled = true;
        }

        $emailActionSettings = $this->accountCapabilities->getEmailActionSetting();
        if ($emailActionSettings === 'disabled') {
            throw new ForbiddenException('Email action disabled');
        }
        // Force login if necessary:
        if (
            $emailActionSettings !== 'enabled'
            && !$this->authManager->getUserObject()
        ) {
            return $this->getHelper(LoginHelper::class)->forceLogin(
                $request,
                $response,
                null,
                ['cartIds' => $ids, 'cartAction' => 'Email']
            );
        }

        $templateParams = $this->getHelper(EmailHelper::class)->createEmailTemplateParams(
            $request,
            [],
            $this->translate('bulk_email_title')
        );
        $templateParams['records'] = $this->recordLoader->loadBatch($ids);
        // Set up Captcha
        $templateParams['useCaptcha'] = $this->captchaService->active('email');

        // Process form submission:
        if (
            !($submitDisabled ?? false)
            && $this->getHelper(FormHelper::class)
                ->formWasSubmitted($request, useCaptcha: $templateParams['useCaptcha'])
        ) {
            // Build the URL to share:
            $params = [];
            foreach ($ids as $current) {
                $params[] = urlencode('id[]') . '=' . urlencode($current);
            }
            $url = $this->serverUrlHelper->getUrlForPath(
                $this->getRouteHelper()->getUrlFromRoute('records-home') . '?' . implode('&', $params)
            );

            // Attempt to send the email and show an appropriate flash message:
            try {
                // If we got this far, we're ready to send the email:
                $this->mailer->setMaxRecipients($templateParams['maxRecipients']);
                $cc = $this->getPostParam('ccself') && $templateParams['from'] !== $templateParams['to']
                    ? $templateParams['from'] : null;
                $this->mailer->sendLink(
                    $templateParams['to'],
                    $templateParams['from'],
                    $templateParams['message'],
                    $url,
                    $templateParams['subject'],
                    $cc
                );
                return $bulkActionHelper->redirectToSource($request, $response, 'success', 'bulk_email_success', true);
            } catch (MailException $e) {
                $this->getHelper(FlashMessagesHelper::class)->addErrorMessage($e->getDisplayMessage());
            }
        }

        return $this->renderTemplate($request, $response, $templateParams);
    }
}
