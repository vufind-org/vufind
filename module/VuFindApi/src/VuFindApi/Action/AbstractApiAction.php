<?php

/**
 * Abstract base class for API actions.
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
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFindApi\Action;

use Exception;
use Lmc\Rbac\Mvc\Service\AuthorizationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\DeveloperSettings\DeveloperSettingsService;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Abstract base class for API actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
abstract class AbstractApiAction extends AbstractTemplateRenderingAction implements ApiInterface
{
    // define some status constants
    public const STATUS_OK = 'OK';
    public const STATUS_ERROR = 'ERROR';
    public const STATUS_UNAUTHORIZED = 'UNAUTHORIZED';

    /**
     * Callback function in JSONP mode.
     *
     * @var ?string
     */
    protected ?string $jsonpCallback = null;

    /**
     * Whether to pretty-print JSON.
     *
     * @var bool
     */
    protected bool $jsonPrettyPrint = false;

    /**
     * Type of output to use.
     *
     * @var string
     */
    protected string $outputMode = 'json';

    /**
     * Whether unicode should be returned or encoded in the output.
     *
     * @var bool
     */
    protected bool $returnUnicode = false;

    /**
     * Name of HTTP header.
     *
     * @var string
     */
    protected string $apiKeyHeaderField = VUFIND_API_KEY_DEFAULT_HEADER_FIELD;

    /**
     * Constructor.
     *
     * @param AuthorizationService     $authorizationService     Authorization service
     * @param DeveloperSettingsService $developerSettingsService Developer settings service
     * @param array                    $config                   VuFind configuration
     */
    public function __construct(
        protected AuthorizationService $authorizationService,
        protected DeveloperSettingsService $developerSettingsService,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
        parent::__construct();

        $this->initApiKeySettings($config['API_Keys'] ?? []);
    }

    /**
     * Check that everything is in order for the action to be executed.
     *
     * This method is executed in the very beginning of the action invocation before any permission checks etc.
     * It is meant for technical checks such as route-based configuration being correctly applied.
     * It may return a suitable response or throw an exception if there are issues.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     *
     * @return ?ResponseInterface
     */
    protected function validateActionConfig(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ?ResponseInterface {
        if ($result = parent::validateActionConfig($request, $response)) {
            return $result;
        }

        // Determine output mode early so that we know how to return e.g. permission errors:
        $this->determineOutputMode();

        return null;
    }

    /**
     * Validate any access permission for the action.
     *
     * @return ?ResponseInterface A response if access is denied, null otherwise
     */
    public function validateAccessPermission(): ?ResponseInterface
    {
        if ($result = $this->getResponseIfAccessDenied($this->accessPermission)) {
            return $result;
        }
        return null;
    }

    /**
     * Preprocess a request before the actual action is executed.
     *
     * This method is executed just before the actual action (i.e. after permission checks etc.).
     * It is meant for preprocessing of requests in a shared base class of multiple actions.
     * It may return a suitable response or throw an exception if there are issues.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     *
     * @return ?ResponseInterface
     */
    protected function preprocessRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ?ResponseInterface {
        // Add CORS headers and handle OPTIONS requests. This is a simplistic approach since we allow any origin.
        $this->response = $response = $this->getHelper(ResponseHelper::class)->addCorsHeaders($response);
        if ($request->getMethod() === 'OPTIONS') {
            return $response->withStatus(204);
        }
        return null;
    }

    /**
     * Determine the correct output mode based on content negotiation or the view parameter.
     *
     * @return void
     */
    protected function determineOutputMode(): void
    {
        $this->jsonpCallback = $this->getPostOrQueryParam('callback', preferQuery: true);
        $this->jsonPrettyPrint = filter_var(
            $this->getPostOrQueryParam('prettyPrint', preferQuery: true),
            FILTER_VALIDATE_BOOLEAN
        );
        $this->outputMode = empty($this->jsonpCallback) ? 'json' : 'jsonp';
        $charsetHeader = $this->request->getHeader('Accept-Charset')[0] ?? null;
        if (!$charsetHeader) {
            $charsetHeader = $this->request->getHeader('Accept')[0] ?? null;
        }
        if ($charsetHeader && preg_match('/utf-8/i', $charsetHeader)) {
            $this->returnUnicode = true;
        }
    }

    /**
     * Check whether access is denied and return the appropriate message or false.
     *
     * @param string $permission Permission to check
     *
     * @return bool
     */
    protected function isAccessDenied($permission): bool
    {
        return $permission && !$this->authorizationService->isGranted($permission);
    }

    /**
     * Check whether access is denied and return the appropriate message or false.
     *
     * @param string $permission Permission to check
     *
     * @return ResponseInterface|false
     */
    protected function getResponseIfAccessDenied($permission): ResponseInterface|false
    {
        if ($this->isAccessDenied($permission)) {
            $this->logger->debug("API access denied ($permission)");
            return $this->output(
                [],
                static::STATUS_ERROR,
                403,
                'Permission denied'
            );
        }
        return false;
    }

    /**
     * Send output data and exit.
     *
     * @param mixed  $data     The response data
     * @param string $status   Status of the request
     * @param ?int   $httpCode A custom HTTP Status Code
     * @param string $message  Status message
     *
     * @return ResponseInterface
     * @throws Exception
     */
    protected function output($data, $status, $httpCode = null, $message = ''): ResponseInterface
    {
        $response = $this->response;
        if ($httpCode !== null) {
            $response = $response->withStatus($httpCode);
        }

        if (null === $data) {
            return $response;
        }

        $output = $data;
        $output['status'] ??= $status;
        if ($message && !isset($output['statusMessage'])) {
            $output['statusMessage'] = $message;
        }

        $charset = null;
        $jsonOptions = $this->jsonPrettyPrint ? JSON_PRETTY_PRINT : 0;
        if ($this->returnUnicode) {
            $charset = 'utf-8';
            $jsonOptions |= JSON_UNESCAPED_UNICODE;
        }
        if ($this->outputMode == 'json') {
            $contentType = 'application/json';
            $response->getBody()->write(json_encode($output, $jsonOptions));
        } elseif ($this->outputMode == 'jsonp') {
            $contentType = 'application/javascript';
            $response->getBody()->write($this->jsonpCallback . '(' . json_encode($output, $jsonOptions) . ');');
        } else {
            throw new Exception('Invalid output mode');
        }

        if ($charset) {
            $contentType .= "; charset=$charset";
        }
        return $response->withHeader('Content-Type', $contentType);
    }

    /**
     * Handle an exception during action.
     *
     * @param Throwable $exception Exception
     *
     * @return ResponseInterface
     */
    protected function handleException(Throwable $exception): ResponseInterface
    {
        return $this->output(
            [],
            'An error has occurred.',
            500
        );
    }

    /**
     * Init API key settings.
     *
     * @param array $settings API key settings from config.ini
     *
     * @return void
     */
    protected function initApiKeySettings(array $settings): void
    {
        if ($field = $settings['header_field'] ?? null) {
            $this->apiKeyHeaderField = $field;
        }
    }

    /**
     * Check request for API key if mode is not set to disabled.
     *
     * @return bool
     * @throws \Exception
     */
    protected function checkRequestForApiKey(): bool
    {
        $apiKey = $this->request->getHeader($this->apiKeyHeaderField)[0] ?? null;
        return $this->developerSettingsService->isApiKeyAllowed($apiKey);
    }

    /**
     * Return output if request is missing an API key and API keys are enforced.
     *
     * @return ResponseInterface
     * @throws \Exception
     */
    protected function outputMissingApiKey(): ResponseInterface
    {
        return $this->output(
            [],
            static::STATUS_UNAUTHORIZED,
            401,
            $this->developerSettingsService->getApiKeyMode()->getUnauthorizedMessage()
        );
    }

    /**
     * Get shared template params for OpenAPI templates.
     *
     * @return array
     */
    protected function getOpenApiTemplateParams(): array
    {
        return [
            'config' => $this->config,
            'version' => \VuFind\Config\Version::getBuildVersion(),
            'apiKeysEnabled' => $this->developerSettingsService->apiKeysEnabled(),
            'apiKeyHeaderField' => $this->apiKeyHeaderField,
            'apiKeyMode' => $this->developerSettingsService->getApiKeyMode(),
        ];
    }
}
