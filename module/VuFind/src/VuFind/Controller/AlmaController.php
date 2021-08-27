<?php
/**
 * Alma controller
 *
 * PHP version 7
 *
 * Copyright (C) AK Bibliothek Wien für Sozialwissenschaften 2018.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFind\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\RequestInterface;

/**
 * Alma controller, mainly for webhooks.
 *
 * @category VuFind
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AlmaController extends AbstractBase
{
    /**
     * Http service
     *
     * @var \VuFindHttp\HttpService
     */
    protected $httpService;

    /**
     * Http response
     *
     * @var \Laminas\Http\PhpEnvironment\Response
     */
    protected $httpResponse;

    /**
     * Http headers
     *
     * @var \Laminas\Http\Headers
     */
    protected $httpHeaders;

    /**
     * Configuration from config.ini
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Alma.ini config
     *
     * @var \Laminas\Config\Config
     */
    protected $configAlma;

    /**
     * User table
     *
     * @var \VuFind\Db\Table\User
     */
    protected $userTable;

    /**
     * Alma Controller constructor.
     *
     * @param ServiceLocatorInterface $sm The ServiceLocatorInterface
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        parent::__construct($sm);
        $this->httpResponse = $this->getResponse();
        $this->httpHeaders = $this->httpResponse->getHeaders();
        $this->config = $this->getConfig('config');
        $this->configAlma = $this->getConfig('Alma');
        $this->userTable = $this->getTable('user');
    }

    /**
     * Action that is executed when the webhook page is called.
     *
     * @return \Laminas\Http\Response|NULL
     */
    public function webhookAction()
    {
        // Request from external
        $request = $this->getRequest();

        // Get request method (GET, POST, ...)
        $requestMethod = $request->getMethod();

        // Get request body if method is POST and is not empty
        $requestBodyJson = null;
        if ($request->getContent() != null
            && !empty($request->getContent())
            && $requestMethod == 'POST'
        ) {
            try {
                $this->checkMessageSignature($request);
            } catch (\VuFind\Exception\Forbidden $ex) {
                return $this->createJsonResponse(
                    'Access to Alma Webhook is forbidden. ' .
                    'The message signature is not correct.',
                    403
                );
            }
            $requestBodyJson = json_decode($request->getContent());
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
                    'Access to Alma Webhook \'' . $webhookAction . '\' forbidden. ' .
                    'Set permission \'' . $accessPermission .
                    '\' in \'permissions.ini\'.',
                    403
                );
            }

            return $this->webhookUser($requestBodyJson);
                break;
        case 'JOB_END':
        case 'NOTIFICATION':
        case 'LOAN':
        case 'REQUEST':
        case 'BIB':
        case 'ITEM':
            return $this->webhookNotImplemented($webhookAction);
                break;
        default:
            $accessPermission = 'access.alma.webhook.challenge';
            try {
                $this->checkPermission($accessPermission);
            } catch (\VuFind\Exception\Forbidden $ex) {
                return $this->createJsonResponse(
                    'Access to Alma Webhook challenge forbidden. Set permission \'' .
                    $accessPermission . '\' in \'permissions.ini\'.',
                    403
                );
            }
            return $this->webhookChallenge();
                break;
        }
    }

    /**
     * Webhook actions related to a newly created, updated or deleted user in Alma.
     *
     * @param mixed $requestBodyJson A JSON string decode with json_decode()
     *
     * @return NULL|\Laminas\Http\Response
     */
    protected function webhookUser($requestBodyJson)
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
            $userIdentifiers
                = $requestBodyJson->webhook_user->user->user_identifier ?? null;
            $idTypeConfig = $this->configAlma->NewUser->idType ?? null;
            foreach ($userIdentifiers as $userIdentifier) {
                $idTypeHook = $userIdentifier->id_type->value ?? null;
                if ($idTypeHook != null
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
            $firstname = $requestBodyJson->webhook_user->user->first_name ?? null;
            $lastname = $requestBodyJson->webhook_user->user->last_name ?? null;

            $allEmails
                = $requestBodyJson->webhook_user->user->contact_info->email ?? null;
            $email = null;
            foreach ($allEmails as $currentEmail) {
                $preferred = $currentEmail->preferred ?? false;
                if ($preferred && $email == null) {
                    $email = $currentEmail->email_address ?? null;
                }
            }

            if ($method == 'CREATE') {
                $user = $this->userTable->getByUsername($username, true);
            }

            if ($method == 'UPDATE') {
                $user = $this->userTable->getByCatalogId($primaryId);
            }

            if ($user) {
                $user->username = $username;
                $user->firstname = $firstname;
                $user->lastname = $lastname;
                $user->updateEmail($email);
                $user->cat_id = $primaryId;
                $user->cat_username = $username;

                try {
                    $user->save();
                    if ($method == 'CREATE') {
                        $this->sendSetPasswordEmail($user, $this->config);
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
            $user = $this->userTable->getByCatalogId($primaryId);
            if ($user) {
                $rowsAffected = $user->delete();
                if ($rowsAffected == 1) {
                    $jsonResponse = $this->createJsonResponse(
                        'Successfully deleted use with primary ID \'' . $primaryId .
                        '\' in VuFind.',
                        200
                    );
                } else {
                    $jsonResponse = $this->createJsonResponse(
                        'Problem when deleting user with \'' . $primaryId .
                        '\' in VuFind. It is expected that only 1 row of the ' .
                        'VuFind user table is affected by the deletion. But ' .
                        $rowsAffected . ' were affected. Please check the status ' .
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
     * @return \Laminas\Http\Response
     */
    protected function webhookChallenge()
    {
        // Get challenge string from the get parameter that Alma sends us. We need to
        // return this string in the return message.
        $secret = $this->params()->fromQuery('challenge');

        // Create the return array
        $returnArray = [];

        if (isset($secret) && !empty(trim($secret))) {
            $returnArray['challenge'] = $secret;
            $this->httpResponse->setStatusCode(200);
        } else {
            $returnArray['error'] = 'GET parameter \'challenge\' is empty, not ' .
            'set or not available when receiving webhook challenge from Alma.';
            $this->httpResponse->setStatusCode(500);
        }

        // Remove null from array
        $returnArray = array_filter($returnArray);

        // Create return JSON value and set it to the response
        $returnJson = json_encode($returnArray, JSON_PRETTY_PRINT);
        $this->httpHeaders->addHeaderLine('Content-type', 'application/json');
        $this->httpResponse->setContent($returnJson);

        return $this->httpResponse;
    }

    /**
     * Send the "set password email" to a new user that was created in Alma and sent
     * to VuFind via webhook.
     *
     * @param \VuFind\Db\Row\User    $user   A user row object from the VuFind
     * user table.
     * @param \Laminas\Config\Config $config A config object of config.ini
     *
     * @return void
     */
    protected function sendSetPasswordEmail($user, $config)
    {
        // If we can't find a user
        if (null == $user) {
            error_log(
                'Could not send the email to new user for setting the ' .
                'password because the user object was not found.'
            );
        } else {
            // Attempt to send the email
            try {
                // Create a fresh hash
                $user->updateHash();
                $config = $this->getConfig();
                $renderer = $this->getViewRenderer();
                $method = $this->getAuthManager()->getAuthMethod();

                // Custom template for emails (text-only)
                $message = $renderer->render(
                    'Email/new-user-welcome.phtml',
                    [
                        'library' => $config->Site->title,
                        'firstname' => $user->firstname,
                        'lastname' => $user->lastname,
                        'username' => $user->username,
                        'url' => $this->getServerUrl('myresearch-verify') . '?hash='
                            . $user->verify_hash . '&auth_method=' . $method
                    ]
                );
                // Send the email
                $this->serviceLocator->get(\VuFind\Mailer\Mailer::class)->send(
                    $user->email,
                    $config->Site->email,
                    $this->translate(
                        'new_user_welcome_subject',
                        ['%%library%%' => $config->Site->title]
                    ),
                    $message
                );
            } catch (\VuFind\Exception\Mail $e) {
                error_log(
                    'Could not send the \'set-password-email\' to user with ' .
                    'primary ID \'' . $user->cat_id . '\' | username \'' .
                    $user->username . '\': ' . $e->getMessage()
                );
            }
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
     * @return \Laminas\Http\Response
     */
    protected function createJsonResponse($text, $httpStatusCode)
    {
        $returnArray = [];
        $returnArray[] = $text;
        $returnJson = json_encode($returnArray, JSON_PRETTY_PRINT);
        $this->httpHeaders->addHeaderLine('Content-type', 'application/json');
        $this->httpResponse->setStatusCode($httpStatusCode);
        $this->httpResponse->setContent($returnJson);
        return $this->httpResponse;
    }

    /**
     * A default message to be sent back to Alma if an action for a certain webhook
     * type is not implemented (yet).
     *
     * @param string $webhookType The type of the webhook
     *
     * @return \Laminas\Http\Response
     */
    protected function webhookNotImplemented($webhookType)
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
    protected function checkPermission($accessPermission)
    {
        $this->accessPermission = $accessPermission;
        $this->accessDeniedBehavior = 'exception';
        $this->validateAccessPermission($this->getEvent());
    }

    /**
     * Signing and hashing the body content of the Alma POST request with the
     * webhook secret in Alma.ini. The calculated hash value must be the same as
     * the 'X-Exl-Signature' in the request header. This is a security measure to
     * be sure that the request comes from Alma.
     *
     * @param RequestInterface $request The request from Alma.
     *
     * @throws \VuFind\Exception\Forbidden Throws forbidden exception if hash values
     * are not the same.
     *
     * @return void
     */
    protected function checkMessageSignature(RequestInterface $request)
    {
        // Get request content
        $requestBodyString = $request->getContent();

        // Get hashed message signature from request header of Alma webhook request
        $almaSignature = ($request->getHeaders()->get('X-Exl-Signature'))
        ? $request->getHeaders()->get('X-Exl-Signature')->getFieldValue()
        : null;

        // Get the webhook secret defined in Alma.ini
        $secretConfig = $this->configAlma->Webhook->secret ?? null;

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
            error_log(
                '[Alma] Unauthorized: Signature value not correct! ' .
                'Hash from Alma: "' . $almaSignature . '". ' .
                'Calculated hash: "' . $calculatedHash . '". ' .
                'Body content for calculating the hash was: ' .
                '"' . json_encode(
                    json_decode($requestBodyString),
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                ) . '"'
            );
            throw new \VuFind\Exception\Forbidden;
        }
    }
}
