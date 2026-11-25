<?php

/**
 * VuFind Bootstrapper.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Bootstrap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind;

use GuzzleHttp\Psr7\Message;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Response;
use Laminas\Router\Http\HttpRouteMatch;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use VuFind\Cookie\CookieManager;
use VuFind\EventManager\Event;
use VuFind\EventManager\EventInterface;
use VuFind\Http\ServerUrlHelper;
use VuFind\I18n\Locale\LocaleSettings;
use VuFind\RateLimiter\RateLimiterManager;
use VuFind\View\GlobalsContainer;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * VuFind Bootstrapper.
 *
 * @category VuFind
 * @package  Bootstrap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Bootstrapper
{
    /**
     * Constructor.
     *
     * @param EventInterface        $event     Event
     * @param ContainerInterface    $container Service manager
     * @param EventManagerInterface $events    Event manager
     * @param array                 $config    VuFind configuration
     */
    public function __construct(
        protected EventInterface $event,
        protected ContainerInterface $container,
        protected EventManagerInterface $events,
        protected array $config,
    ) {
    }

    /**
     * Bootstrap all necessary resources.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        // automatically call all methods starting with "init":
        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (str_starts_with($method, 'init')) {
                $this->$method();
            }
        }
    }

    /**
     * Get a database service object.
     *
     * @param class-string<T> $name Name of service to retrieve
     *
     * @template T
     *
     * @return T
     */
    public function getDbService(string $name): \VuFind\Db\Service\DbServiceInterface
    {
        return $this->container->get(\VuFind\Db\Service\PluginManager::class)->get($name);
    }

    /**
     * Set up cookie to flag test mode.
     *
     * @return void
     */
    protected function initTestMode(): void
    {
        // If we're in test mode (as determined by the config.ini property installed
        // by the build.xml startup process), set a cookie so the front-end code can
        // act accordingly. (This is needed to work around a problem where opening
        // print dialogs during testing stalls the automated test process).
        if ($this->config['System']['runningTestSuite'] ?? false) {
            $cm = $this->container->get(\VuFind\Cookie\CookieManager::class);
            $cm->set('VuFindTestSuiteRunning', '1', 0, false);
        }
    }

    /**
     * If the system is offline, set up a handler to override the routing output.
     *
     * @return void
     */
    protected function initSystemStatus(): void
    {
        // If the system is unavailable and we're not in the console, forward to the
        // unavailable page.
        if (PHP_SAPI !== 'cli' && !($this->config['System']['available'] ?? true)) {
            $callback = function ($e): mixed {
                $routeMatch = new HttpRouteMatch(
                    ['controller' => 'Error', 'action' => 'Unavailable'],
                    'error-unavailable'
                );
                return $routeMatch;
            };
            $this->events->attach(Event::EVENT_ROUTE, $callback);
        }
    }

    /**
     * Initializes timezone value.
     *
     * @return void
     */
    protected function initTimeZone(): void
    {
        date_default_timezone_set($this->config['Site']['timezone']);
    }

    /**
     * Detect locale and update language in user account, as needed.
     *
     * @return void
     */
    protected function initUserLanguage(): void
    {
        // Initialize language at a very high priority so that it's available before everything else:
        $callback = function ($event): void {
            // Detect language:
            $settings = $this->container->get(LocaleSettings::class);
            $language = $settings->detectLocale(
                PHP_SAPI !== 'cli' ? $event->getRequest() : null
            );
            // Update cookie:
            $cookies = $this->container->get(CookieManager::class);
            if ($language !== $cookies->get('language')) {
                $cookies->set('language', $language);
            }
            // Store last selected language in user account, if applicable:
            $authManager = $this->container->get(\VuFind\Auth\Manager::class);
            if (
                ($user = $authManager->getUserObject())
                && $user->getLastLanguage() != $language
            ) {
                $user->setLastLanguage($language);
                $this->getDbService(\VuFind\Db\Service\UserServiceInterface::class)->persistEntity($user);
            }
            // Populate language-related global values:
            $globals = $this->container->get(GlobalsContainer::class);
            $globals['userLang'] = $language;
            $globals['allLangs'] = $settings->getEnabledLocales();
            $globals['rtl'] = $settings->isRightToLeftLocale($language);
        };
        $this->events->attach(Event::EVENT_DISPATCH, $callback, 15000);
        $this->events->attach(Event::EVENT_DISPATCH_ERROR, $callback, 15000);
    }

    /**
     * Set up theme handling.
     *
     * @return void
     */
    protected function initTheme(): void
    {
        // Attach remaining theme configuration to the dispatch event at high priority:
        $siteConfig = $this->config['Site'];
        $callback = function (Event $event) use ($siteConfig): mixed {
            $theme = new \VuFindTheme\Initializer($siteConfig, $this->container, $event);
            try {
                $theme->init();
                return null;
            } catch (\Exception $e) {
                // Try to display an error page if the theme fails to initialize:
                $response = $event->getResponse()
                    ->withStatus(500)
                    ->withHeader('Content-Type', 'text/plain');
                $body = $response->getBody();
                $body->write('An error has occurred.');
                $appConfig = $this->container->get('Config');
                if ($appConfig['view_manager']['display_exceptions'] ?? false) {
                    $body->write("\n" . (string)$e);
                }
                $event->stopPropagation();
                return $response;
            }
        };
        $this->events->attach(Event::EVENT_DISPATCH_ERROR, $callback, 9000);
        $this->events->attach(Event::EVENT_DISPATCH, $callback, 9000);
    }

    /**
     * The login token manager needs to be informed after the theme has been initialized,
     * so that it can send warning emails if necessary.
     *
     * @return void
     */
    protected function initLoginTokenManager(): void
    {
        $dispatchCallback = function (): void {
            $this->container->get(\VuFind\Auth\LoginTokenManager::class)->themeIsReady();
        };
        $finishCallback = function (): void {
            $this->container->get(\VuFind\Auth\LoginTokenManager::class)->requestIsFinished();
        };
        $this->events->attach(Event::EVENT_DISPATCH_ERROR, $dispatchCallback, 8000);
        $this->events->attach(Event::EVENT_DISPATCH, $dispatchCallback, 8000);
        $this->events->attach(Event::EVENT_FINISH, $finishCallback, 8000);
    }

    /**
     * Set up custom HTTP status based on exception information.
     *
     * @return void
     */
    protected function initExceptionBasedHttpStatuses(): void
    {
        // HTTP statuses not needed in console mode:
        if (PHP_SAPI === 'cli') {
            return;
        }

        $callback = function ($e): void {
            $exception = $e->getParam('exception');
            if ($exception instanceof \VuFind\Exception\HttpStatusInterface) {
                $response = $e->getResponse();
                if (!$response) {
                    $response = new \Laminas\Http\Response();
                    $e->setResponse($response);
                }
                $response->setStatusCode($exception->getHttpStatus());
            }
        };
        $this->events->attach(Event::EVENT_DISPATCH_ERROR, $callback);
    }

    /**
     * Set up search subsystem.
     *
     * @return void
     */
    protected function initSearch(): void
    {
        $bm = $this->container->get(\VuFind\Search\BackendManager::class);
        $events = $this->container->get('SharedEventManager');
        $events->attach(
            \VuFindSearch\Service::class,
            \VuFindSearch\Service::EVENT_RESOLVE,
            [$bm, 'onResolve']
        );
    }

    /**
     * Set up logging.
     *
     * @return void
     */
    protected function initErrorLogging(): void
    {
        $callback = function ($event): void {
            if ($this->container->has(\VuFind\Log\Logger::class)) {
                $log = $this->container->get(\VuFind\Log\Logger::class);
                if ($log instanceof \VuFind\Log\ExtendedLoggerInterface) {
                    $exception = $event->getParam('exception');
                    // Console request does not include server,
                    // so use a dummy in that case.
                    $server = (PHP_SAPI === 'cli')
                        ? ['env' => 'console']
                        : Message::toString($event->getRequest());
                    if (!empty($exception)) {
                        $log->logException($exception, $server);
                    }
                }
            }
        };
        $this->events->attach(Event::EVENT_DISPATCH_ERROR, $callback);
    }

    /**
     * Set up handling for rendering redirects.
     *
     * @return void
     */
    protected function initRenderRedirects(): void
    {
        // When a render is triggered, check the response status code and switch to a simple redirect template for
        // 302 redirects.
        $callback = function ($event): ?ResponseInterface {
            $response = $event->getResponse();
            if ($response instanceof ResponseInterface && $response->getStatusCode() === 302) {
                $body = $response->getBody();
                if ($body->getSize() === 0) {
                    $redirectUrl = $response->getHeader('Location')[0] ?? null;
                    $templateRenderer = $this->container->get(TemplateRendererInterface::class);
                    $body->write(
                        $templateRenderer->renderTemplateAsString(
                            template: 'layout/redirect',
                            params: compact('redirectUrl')
                        )
                    );
                    return $response;
                }
            }
            return null;
        };
        $this->events->attach(Event::EVENT_FINISH, $callback, 10000);
    }

    /**
     * Set up content security policy.
     *
     * @return void
     */
    protected function initContentSecurityPolicy(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $callback = function ($event): void {
            $response = $event->getResponse();
            $cspHeaderGenerator = $this->container->get(\VuFind\Security\CspHeaderGenerator::class);
            foreach ($cspHeaderGenerator->getHeaders() as $header) {
                $response = $response->withHeader($header['name'], $header['value']);
            }
            $event->setResponse($response);
        };
        $this->events->attach(Event::EVENT_FINISH, $callback);
    }

    /**
     * Set up rate limiter.
     *
     * @return void
     */
    protected function initRateLimiter(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $callback = function ($event): ?ResponseInterface {
            // Create rate limiter manager here so that we don't e.g. initialize the session too early:
            $rateLimiterManager = $this->container->get(RateLimiterManager::class);
            if (!$rateLimiterManager->isEnabled()) {
                return null;
            }
            $result = $rateLimiterManager->check($event);
            if (!$result['allow']) {
                if ($result['presentTurnstileChallenge'] ?? false) {
                    return $this->presentTurnstileChallenge($rateLimiterManager, $event);
                } else {
                    $response = $event->getResponse();
                    $response = $response->withStatus(429);
                    $response->getBody()->write($result['message']);
                    return $response;
                }
            }
            return null;
        };
        $this->events->attach(Event::EVENT_DISPATCH, $callback, 11000);
    }

    /**
     * Present a Cloudflare Turnstile challenge to the user.
     *
     * @param RateLimiterManager $rateLimiterManager The RateLimiterManager
     * @param EventInterface     $event              The current event
     *
     * @return ResponseInterface
     */
    protected function presentTurnstileChallenge(
        RateLimiterManager $rateLimiterManager,
        EventInterface $event
    ): ResponseInterface {
        // Although the challenge could be displayed at the current URL, redirecting to a simple URL (combined with a
        // policy that blocks the referrer URL) may hide search or result data from being accessible to Turnstile.
        $response = $event->getResponse();
        $response = $response->withStatus(307);
        $policyId = $rateLimiterManager->getPolicyIdForEvent($event);
        // base64_encoding the destination URL is just further obfuscation
        $context = base64_encode(json_encode([
            'policyId' => $policyId,
            'destination' => (string)$event->getRequest()->getUri(),
        ]));
        $serverUrlHelper = $this->container->get(ServerUrlHelper::class);
        $response = $response->withHeader(
            'Location',
            $serverUrlHelper->getBaseUrl() . '/Turnstile/Challenge?context=' . $context
        );
        return $response;
    }
}
