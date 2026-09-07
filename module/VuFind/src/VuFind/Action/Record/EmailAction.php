<?php

/**
 * Record email action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2024.
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

namespace VuFind\Action\Record;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\EmailHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Captcha\Service\CaptchaService;
use VuFind\Config\AccountCapabilities;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Exception\Mail as MailException;
use VuFind\Mailer\Mailer;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\RecordTab\TabManager;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\ResultScroller;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Record email action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EmailAction extends AbstractRecordAction
{
    /**
     * Constructor.
     *
     * @param SearchMemory        $searchMemory        Search memory
     * @param TabManager          $tabManager          Tab manager
     * @param AuthManager         $authManager         Authentication manager
     * @param RecordLoader        $recordLoader        Record loader
     * @param RecordRouter        $recordRouter        Record router
     * @param ResultScroller      $resultScroller      Result scroller
     * @param array               $config              VuFind configuration
     * @param AccountCapabilities $accountCapabilities Account capabilities helper
     * @param Mailer              $mailer              Mailer
     * @param CaptchaService      $captchaService      Captcha service
     */
    public function __construct(
        SearchMemory $searchMemory,
        TabManager $tabManager,
        AuthManager $authManager,
        RecordLoader $recordLoader,
        RecordRouter $recordRouter,
        ResultScroller $resultScroller,
        #[Autowire(config: 'config')]
        array $config,
        protected AccountCapabilities $accountCapabilities,
        protected Mailer $mailer,
        protected CaptchaService $captchaService,
    ) {
        parent::__construct(
            $searchMemory,
            $tabManager,
            $authManager,
            $recordLoader,
            $recordRouter,
            $resultScroller,
            $config
        );
    }

    /**
     * Email a record.
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
        $emailActionSetting = $this->accountCapabilities->getEmailActionSetting();
        if ($emailActionSetting === 'disabled') {
            throw new ForbiddenException('Email action disabled');
        }
        // Force login if necessary:
        if (
            $emailActionSetting !== 'enabled'
            && !$this->getUser()
        ) {
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }

        // Retrieve the record driver:
        $driver = $this->loadRecord();

        // Create template params:
        $templateParams = $this->getHelper(EmailHelper::class)->createEmailTemplateParams(
            $request,
            [
                'driver' => $driver,
                'useCaptcha' => $this->captchaService->active('email'),
            ],
            $this->mailer->getDefaultRecordSubject($driver)
        );
        $this->mailer->setMaxRecipients($templateParams['maxRecipients']);

        // Process form submission:
        if (
            $this->getHelper(FormHelper::class)->formWasSubmitted($request, useCaptcha: $templateParams['useCaptcha'])
        ) {
            // Attempt to send the email and show an appropriate flash message:
            $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
            try {
                $cc = $this->getPostParam('ccself') && $templateParams['from'] !== $templateParams['to']
                    ? $templateParams['from'] : null;
                $this->mailer->sendRecord(
                    $templateParams['to'],
                    $templateParams['from'],
                    $templateParams['message'],
                    $driver,
                    $templateParams['subject'],
                    $cc
                );
                $flashMessagesHelper->addSuccessMessage('email_success');
                return $this->redirectToRecord();
            } catch (MailException $e) {
                $flashMessagesHelper->addErrorMessage($e->getDisplayMessage());
            }
        }

        // Display the template:
        return $this->renderTemplate($request, $response, $templateParams, 'record/email');
    }
}
