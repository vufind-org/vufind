<?php

/**
 * Record SMS action.
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
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Captcha\Service\CaptchaService;
use VuFind\Config\AccountCapabilities;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Exception\Mail as MailException;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\RecordTab\TabManager;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\ResultScroller;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\SMS\SMSInterface;
use VuFind\Validator\SessionCsrf;

/**
 * Record SMS action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SmsAction extends AbstractRecordAction
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
     * @param SMSInterface        $smsService          SMS service
     * @param CaptchaService      $captchaService      Captcha service
     * @param SessionCsrf         $sessionCsrf         Session CSRF service
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
        protected SMSInterface $smsService,
        protected CaptchaService $captchaService,
        protected SessionCsrf $sessionCsrf,
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
     * Send a record by SMS.
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
        // Make sure SMS support is enabled:
        if ($this->accountCapabilities->getSmsSetting() === 'disabled') {
            throw new ForbiddenException('SMS disabled');
        }

        // Retrieve the record driver:
        $driver = $this->loadRecord();

        // Set up template parameters, including loading the SMS carrier list:
        $templateParams = $this->getTemplateParams(
            [
                'carriers' => $this->smsService->getCarriers(),
                'validation' => $this->smsService->getValidationType(),
                'useCaptcha' => $this->captchaService->active('sms'),
                'to' => $this->getPostParam('to'),
                'provider' => $this->getPostParam('provider'),
            ]
        );
        // Process form submission:
        if (
            $this->getHelper(FormHelper::class)->formWasSubmitted($request, useCaptcha: $templateParams['useCaptcha'])
        ) {
            // Do CSRF check
            if (!$this->sessionCsrf->isValid($this->getPostParam('csrf'))) {
                throw new \VuFind\Exception\BadRequest('error_inconsistent_parameters');
            }

            // Attempt to send the email and show an appropriate flash message:
            $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
            try {
                $body = $this->templateRenderer->renderTemplateAsString(
                    template: 'Email/record-sms.phtml',
                    params: [
                        'driver' => $driver, 'to' => $templateParams['to'],
                    ]
                );
                $this->smsService->text($templateParams['provider'], $templateParams['to'], null, $body);
                $flashMessagesHelper->addSuccessMessage('sms_success');
                return $this->redirectToRecord();
            } catch (MailException $e) {
                $flashMessagesHelper->addErrorMessage($e->getDisplayMessage());
            }
        }

        // Display the template:
        return $this->renderTemplate($request, $response, $templateParams, 'record/sms');
    }
}
