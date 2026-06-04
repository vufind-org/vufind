<?php

/**
 * Action helper for form-related functionality.
 *
 * PHP version 8
 *
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
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\ActionHelper;

use Psr\Http\Message\ServerRequestInterface;
use VuFind\Captcha\Service\CaptchaService;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Action helper for form-related functionality.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class FormHelper implements HelperInterface
{
    /**
     * Constructor.
     *
     * @param CaptchaService $captchaService Captcha service
     */
    #[Autowire()]
    public function __construct(
        protected CaptchaService $captchaService,
    ) {
    }

    /**
     * Check to see if a form was submitted from its post value
     * Also validate the Captcha, if it's activated.
     *
     * @param ServerRequestInterface $request        Request
     * @param string|string[]|null   $submitElements Name of the post field(s) to check to indicate a form submission
     *                                               (or null for default)
     * @param bool                   $useCaptcha     Are we using captcha in this situation?
     *
     * @return bool
     */
    public function formWasSubmitted(
        ServerRequestInterface $request,
        string|array|null $submitElements = null,
        bool $useCaptcha = false
    ): bool {
        $buttonFound = false;
        // Use of 'submit' as an input name was deprecated in release 10.0, but the
        // check is retained for backward compatibility with legacy custom templates.
        $defaultSubmitElements = ['submitButton', 'submit'];
        $postParams = $request->getParsedBody();
        foreach ((array)($submitElements ?? $defaultSubmitElements) as $submitElement) {
            if ($postParams[$submitElement] ?? null) {
                $buttonFound = true;
                break;
            }
        }
        // Fail if all expected submission elements were missing from the POST or
        // if the form was submitted but expected CAPTCHA does not validate.
        return $buttonFound && (!$useCaptcha || $this->verifyCaptcha($request));
    }

    /**
     * Verify submitted captcha.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return bool
     */
    protected function verifyCaptcha(ServerRequestInterface $request): bool
    {
        return $this->captchaService->verify($request->getParsedBody() ?? [], $request->getQueryParams());
    }
}
