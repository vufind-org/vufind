<?php

/**
 * Interval CAPTCHA (requires an interval between actions or from start of session).
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021.
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
 * @package  CAPTCHA
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Captcha;

use Laminas\Session\Container as SessionContainer;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\SessionContainerRegistry;

/**
 * Interval CAPTCHA (requires an interval between actions or from start of session).
 *
 * @category VuFind
 * @package  CAPTCHA
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Interval extends AbstractBase implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Minimum action interval in seconds.
     *
     * @var int
     */
    protected $actionInterval;

    /**
     * Minimum time from session start to first action.
     *
     * @var int
     */
    protected $timeFromSessionStart;

    /**
     * Verification error message.
     *
     * @var string
     */
    protected $errorMessage = '';

    /**
     * Constructor.
     *
     * @param SessionContainer $sessionData Session data container
     * @param array            $config      VuFind main configuration
     */
    public function __construct(
        #[Autowire(container: SessionContainerRegistry::class, service: 'SessionState')]
        protected SessionContainer $sessionData,
        #[Autowire(config: 'config')]
        array $config
    ) {
        $this->actionInterval = (int)($config['Captcha']['action_interval'] ?? 60);
        $this->timeFromSessionStart = (int)(
            $config['Captcha']['time_from_session_start'] ?? $this->actionInterval
        );
    }

    /**
     * Pull the captcha fields from request params and check them for accuracy.
     *
     * @param array $postParams  POST params
     * @param array $queryParams Query params
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function verify(array $postParams, array $queryParams): bool
    {
        if (isset($this->sessionData->lastProtectedActionTime)) {
            $timestamp = $this->sessionData->lastProtectedActionTime;
            $requiredInterval = $this->actionInterval;
        } else {
            $timestamp = $this->sessionData->sessionStartTime;
            $requiredInterval = $this->timeFromSessionStart;
        }
        $timePassed = time() - $timestamp;
        if ($timePassed < $requiredInterval) {
            $this->errorMessage = $this->translate(
                'interval_captcha_not_passed',
                [
                    '%%delay%%' => max($requiredInterval - $timePassed, 1),
                ]
            );
            return false;
        }
        $this->sessionData->lastProtectedActionTime = time();
        return true;
    }

    /**
     * Get any error message after a failed captcha verification. The message can be
     * displayed to the user.
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }
}
