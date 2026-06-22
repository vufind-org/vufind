<?php

/**
 * Alma webhook action.
 *
 * PHP version 8
 *
 * Copyright (C) AK Bibliothek Wien für Sozialwissenschaften 2018.
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
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\Action\Alma;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Throwable;
use VuFind\Account\UserAccountService;
use VuFind\Action\AbstractAction;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Config\Feature\EmailSettingsTrait;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Http\ServerUrlHelper;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\Log\LoggerAwareTrait;
use VuFind\Mailer\Mailer;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Renderer\TemplateRendererInterface;
use VuFindHttp\HttpService;

use function is_object;

/**
 * Alma webhook action.
 *
 * @category VuFind
 * @package  Action
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class WebhookAction extends AbstractAction implements LoggerAwareInterface, TranslatorAwareInterface
{
    use EmailSettingsTrait;
    use LoggerAwareTrait;
    use TranslatorAwareTrait;

    /**
     * Constructor.
     *
     * @param AuthManager               $authManager        Authentication manager
     * @param HttpService               $httpService        HTTP service
     * @param Mailer                    $mailer             Mailer
     * @param array                     $config             VuFind configuration
     * @param array                     $configAlma         Alma configuration
     * @param UserServiceInterface      $userService        User database service
     * @param UserAccountService        $userAccountService User account service
     * @param ServerUrlHelper           $serverUrlHelper    Server URL helper
     * @param TemplateRendererInterface $templateRenderer   Template renderer
     */
    public function __construct(
        protected AuthManager $authManager,
        protected HttpService $httpService,
        protected Mailer $mailer,
        #[Autowire(config: 'config')]
        protected array $config,
        #[Autowire(config: 'Alma')]
        protected array $configAlma,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserServiceInterface $userService,
        protected UserAccountService $userAccountService,
        protected ServerUrlHelper $serverUrlHelper,
        protected TemplateRendererInterface $templateRenderer,
    ) {
        parent::__construct();
    }

    /**
     * Process a webhook request.
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
        // Get request method (GET, POST, ...)
        $requestMethod = $request->getMethod();

        // Get request body if method is POST and is not empty
        $requestBodyJson = null;
        if (
            'POST' === $requestMethod
            && $request->getBody()->getSize()
        ) {
            try {
                $this->checkMessageSignature($request);
            } catch (\VuFind\Exception\Forbidden $ex) {
                return $this->createJsonResponse(
                    'Access to Alma Webhook is forbidden. The message signature is not correct.',
                    403
                );
            }
            $requestBodyJson = json_decode((string)$request->getBody());
            if (!is_object($requestBodyJson)) {
                throw new \Exception('Invalid request body');
            }
        }

        // Get webhook action
        $webhookAction = $requestBodyJson->action ?? null;

        // Perform webhook action
        switch ($webhookAction) {
            case 'USER':
                $accessPermission = 'access.alma.webhook.user';
                try {
                    $this->checkPermission($accessPermission);
                } catch (\VuFind\Exception\Forbidden $ex) {
                    return $this->createJsonResponse(
                        'Access to Alma Webhook \'' . $webhookAction .
                        '\' forbidden. Set permission \'' . $accessPermission .
                        '\' in \'permissions.ini\'.',
                        403
                    );
                }

                return $this->webhookUser($requestBodyJson);
            case 'JOB_END':
            case 'NOTIFICATION':
            case 'LOAN':
            case 'REQUEST':
            case 'BIB':
            case 'ITEM':
                return $this->webhookNotImplemented($webhookAction);
            default:
                $accessPermission = 'access.alma.webhook.challenge';
                try {
                    $this->checkPermission($accessPermission);
                } catch (\VuFind\Exception\Forbidden $ex) {
                    return $this->createJsonResponse(
                        'Access to Alma Webhook challenge forbidden. Set ' .
                        'permission \'' . $accessPermission .
                        '\' in \'permissions.ini\'.',
                        403
                    );
                }
                return $this->webhookChallenge();
        }
    }

    /**
     * Webhook actions related to a newly created, updated or deleted user in Alma.
     *
     * @param object $requestBodyJson Request JSON object
     *
     * @return ?ResponseInterface
     */
    protected function webhookUser(object $requestBodyJson): ?ResponseInterface
    {
        // Initialize user variable that should hold the user table row
        $user = null;

        // Initialize response variable
        $jsonResponse = null;

        // Get method from webhook (e. g. "create" for "new user")
        $method = $requestBodyJson->webhook_user->method ?? null;

        // Get primary ID
        $primaryId = $requestBodyJson->webhook_user->user->primary_id ?? null;

        if ($method == 'CREATE' || $method == 'UPDATE') {
            // Get username (could e. g. be the barcode)
            $username = null;
            $userIdentifiers = $requestBodyJson->webhook_user->user->user_identifier ?? [];
            $idTypeConfig = $this->configAlma['NewUser']['idType'] ?? null;
            foreach ($userIdentifiers as $userIdentifier) {
                $idTypeHook = $userIdentifier->id_type->value ?? null;
                if (
                    $idTypeHook != null
                    && $idTypeHook == $idTypeConfig
                    && $username == null
                ) {
                    $username = $userIdentifier->value ?? null;
                }
            }

            // Use primary ID as username as a fallback if no other
            // username ID is available
            $username = ($username == null) ? $primaryId : $username;

            // Get user details from Alma Webhook message
            $firstname = $requestBodyJson->webhook_user->user->first_name ?? '';
            $lastname = $requestBodyJson->webhook_user->user->last_name ?? '';

            $allEmails = $requestBodyJson->webhook_user->user->contact_info->email ?? [];
            $email = '';
            foreach ($allEmails as $currentEmail) {
                $preferred = $currentEmail->preferred ?? false;
                if ($preferred && $email === '') {
                    $email = $currentEmail->email_address ?? '';
                }
            }

            if ($method == 'CREATE') {
                $user = $this->userService->getUserByUsername($username)
                    ?? $this->userService->createEntityForUsername($username);
            } elseif ($method == 'UPDATE') {
                $user = $this->userService->getUserByCatId($primaryId);
            }

            if ($user) {
                $user->setUsername($username)
                    ->setFirstname($firstname)
                    ->setLastname($lastname)
                    ->setCatId($primaryId)
                    ->setCatUsername($username);
                $this->userService->updateUserEmail($user, $email);

                try {
                    $this->userService->persistEntity($user);
                    if ($method == 'CREATE') {
                        $this->sendSetPasswordEmail($user);
                    }
                    $jsonResponse = $this->createJsonResponse(
                        'Successfully ' . strtolower($method) .
                        'd user with primary ID \'' . $primaryId .
                        '\' | username \'' . $username . '\'.',
                        200
                    );
                } catch (\Exception $ex) {
                    $jsonResponse = $this->createJsonResponse(
                        'Error when saving new user with primary ID \'' .
                        $primaryId . '\' | username \'' . $username .
                        '\' to VuFind database and sending the welcome email: ' .
                        $ex->getMessage() . '. ',
                        400
                    );
                }
            } else {
                $jsonResponse = $this->createJsonResponse(
                    'User with primary ID \'' . $primaryId . '\' | username \'' .
                    $username . '\' was not found in VuFind database and ' .
                    'therefore could not be ' . strtolower($method) . 'd.',
                    404
                );
            }
        } elseif ($method == 'DELETE') {
            $user = $this->userService->getUserByCatId($primaryId);
            if ($user) {
                try {
                    $this->userAccountService->purgeUserData($user);
                    $jsonResponse = $this->createJsonResponse(
                        'Successfully deleted user with primary ID \'' . $primaryId .
                        '\' in VuFind.',
                        200
                    );
                } catch (Throwable) {
                    $jsonResponse = $this->createJsonResponse(
                        'Problem when deleting user with \'' . $primaryId .
                        '\' in VuFind. Please check the status ' .
                        'of the user in the VuFind database.',
                        400
                    );
                }
            } else {
                $jsonResponse = $this->createJsonResponse(
                    'User with primary ID \'' . $primaryId . '\' was not found in ' .
                    'VuFind database and therefore could not be deleted.',
                    404
                );
            }
        }

        return $jsonResponse;
    }

    /**
     * The webhook challenge. This is used to activate the webhook in Alma. Without
     * activating it, Alma will not send its webhook messages to VuFind.
     *
     * @return ResponseInterface
     */
    protected function webhookChallenge(): ResponseInterface
    {
        // Get challenge string from the get parameter that Alma sends us. We need to
        // return this string in the return message.
        $secret = $this->getQueryParam('challenge');

        // Create the return array
        $returnArray = [];

        $statusCode = 200;
        if (isset($secret) && !empty(trim($secret))) {
            $returnArray['challenge'] = $secret;
        } else {
            $returnArray['error'] = 'GET parameter \'challenge\' is empty, not ' .
            'set or not available when receiving webhook challenge from Alma.';
            $statusCode = 500;
        }

        // Remove null from array
        $returnArray = array_filter($returnArray);

        // Create return JSON value and set it to the response
        $returnJson = json_encode($returnArray, JSON_PRETTY_PRINT);
        $response = $this->response->withHeader('Content-type', 'application/json')
            ->withStatus($statusCode);
        $response->getBody()->write($returnJson);

        return $response;
    }

    /**
     * Send the "set password email" to a new user that was created in Alma and sent
     * to VuFind via webhook.
     *
     * @param UserEntityInterface $user User entity object
     *
     * @return void
     */
    protected function sendSetPasswordEmail(UserEntityInterface $user): void
    {
        if (!$user->getEmail()) {
            $this->logError(
                'Could not send the \'set-password-email\' to user with ' .
                'primary ID \'' . $user->getCatId() . '\' | username \'' .
                $user->getUsername() . '\': Email address missing'
            );
            return;
        }
        // Attempt to send the email
        try {
            // Create a fresh hash
            $this->authManager->updateUserVerifyHash($user);
            $method = $this->authManager->getAuthMethod();

            // Custom template for emails (text-only)
            $message = $this->templateRenderer->renderTemplateAsString(
                $this->request,
                'Email/new-user-welcome.phtml',
                [
                    'library' => $this->config['Site']['title'] ?? '',
                    'firstname' => $user->getFirstname(),
                    'lastname' => $user->getLastname(),
                    'username' => $user->getUsername(),
                    'url' => $this->serverUrlHelper->getUrlForPath(
                        $this->routeHelper->getUrlFromRoute(
                            'myresearch-verify',
                            queryParams: [
                                'hash' => $user->getVerifyHash(),
                                'auth_method' => $method,
                            ],
                        )
                    ),
                ]
            );
            // Send the email
            $this->mailer->send(
                $user->getEmail(),
                $this->getEmailSenderAddress($this->config),
                $this->translate(
                    'new_user_welcome_subject',
                    ['%%library%%' => $this->config['Site']['title'] ?? '']
                ),
                $message
            );
        } catch (\VuFind\Exception\Mail $e) {
            $this->logError(
                'Could not send the \'set-password-email\' to user with ' .
                'primary ID \'' . $user->getCatId() . '\' | username \'' .
                $user->getUsername() . '\': ' . (string)$e
            );
        }
    }

    /**
     * Create a HTTP response with JSON content and HTTP status codes that Alma takes
     * as "answer" to its webhook calls.
     *
     * @param string $text           The text that should be sent back to Alma
     * @param int    $httpStatusCode The HTTP status code that should be sent back
     *                               to Alma
     *
     * @return ResponseInterface
     */
    protected function createJsonResponse($text, $httpStatusCode): ResponseInterface
    {
        $returnArray = [];
        $returnArray[] = $text;
        $returnJson = json_encode($returnArray, JSON_PRETTY_PRINT);
        $response = $this->response->withHeader('Content-type', 'application/json')
            ->withStatus($httpStatusCode);
        $response->getBody()->write($returnJson);
        return $response;
    }

    /**
     * A default message to be sent back to Alma if an action for a certain webhook
     * type is not implemented (yet).
     *
     * @param string $webhookType The type of the webhook
     *
     * @return ResponseInterface
     */
    protected function webhookNotImplemented(string $webhookType): ResponseInterface
    {
        return $this->createJsonResponse(
            $webhookType . ' Alma Webhook is not (yet) implemented in VuFind.',
            400
        );
    }

    /**
     * Helper function to check access permissions defined in permissions.ini.
     * The function validateAccessPermission() will throw an exception that can be
     * caught when the permission is denied.
     *
     * @param string $accessPermission The permission name from permissions.ini that
     *                                 should be checked.
     *
     * @return void
     */
    protected function checkPermission(string $accessPermission): void
    {
        $this->accessPermission = $accessPermission;
        $this->accessDeniedBehavior = 'exception';
        $this->validateAccessPermission();
    }

    /**
     * Signing and hashing the body content of the Alma POST request with the
     * webhook secret in Alma.ini. The calculated hash value must be the same as
     * the 'X-Exl-Signature' in the request header. This is a security measure to
     * be sure that the request comes from Alma.
     *
     * @param ServerRequestInterface $request The request from Alma.
     *
     * @throws \VuFind\Exception\Forbidden Throws forbidden exception if hash values
     * are not the same.
     *
     * @return void
     */
    protected function checkMessageSignature(ServerRequestInterface $request): void
    {
        // Get request content
        $requestBodyString = (string)$request->getBody();

        // Get hashed message signature from request header of Alma webhook request
        $almaSignature = $request->getHeader('X-Exl-Signature')[0] ?? null;

        // Get the webhook secret defined in Alma.ini
        $secretConfig = $this->configAlma['Webhook']['secret'] ?? null;

        // Calculate hmac-sha256 hash from request body we get from Alma webhook and
        // sign it with the Alma webhook secret from Alma.ini
        $calculatedHash = base64_encode(
            hash_hmac(
                'sha256',
                $requestBodyString,
                $secretConfig,
                true
            )
        );

        // Check for correct signature
        if ($almaSignature != $calculatedHash) {
            $this->logError(
                '[Alma] Unauthorized: Signature value not correct! ' .
                'Hash from Alma: "' . $almaSignature . '". ' .
                'Calculated hash: "' . $calculatedHash . '". ' .
                'Body content for calculating the hash was: ' .
                '"' . json_encode(
                    json_decode($requestBodyString),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) . '"'
            );
            throw new \VuFind\Exception\Forbidden();
        }
    }
}
