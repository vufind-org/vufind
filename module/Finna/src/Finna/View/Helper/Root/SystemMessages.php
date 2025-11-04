<?php

/**
 * Helper class for system messages
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2023.
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use Laminas\Session\Container;
use VuFind\Config\Config;

/**
 * Helper class for system messages
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SystemMessages extends \Laminas\View\Helper\AbstractHelper implements
    \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Core configuration
     *
     * @var Config
     */
    protected $coreConfig;

    /**
     * Local system configuration
     *
     * @var Config
     */
    protected $localConfig;

    /**
     * Session container
     *
     * @var Container
     */
    protected $session;

    /**
     * Session container name.
     *
     * @var string
     */
    public const SESSION_NAME = 'SystemMessages';

    /**
     * Constructor
     *
     * @param Config    $coreConfig  Configuration
     * @param Config    $localConfig Local configuration
     * @param Container $session     Session container
     */
    public function __construct(
        Config $coreConfig,
        Config $localConfig,
        Container $session
    ) {
        $this->coreConfig = $coreConfig;
        $this->localConfig = $localConfig;
        $this->session = $session;
    }

    /**
     * Return any system messages.
     *
     * @return array
     */
    public function __invoke()
    {
        $language = $this->translator->getLocale();

        $getMessageFn = function ($messages, $language) {
            if (isset($messages[$language])) {
                return [$messages[$language]];
            } else {
                // Return all language versions if current locale is not defined.
                return array_values($messages);
            }
        };

        $messages = [];

        // Use local config for both schedule values if either value is set.
        $scheduleStart
            = $this->localConfig->Site->systemMessagesScheduleStart ?? false;
        $scheduleEnd = $this->localConfig->Site->systemMessagesScheduleEnd ?? false;
        if (!$scheduleStart && !$scheduleEnd) {
            // Otherwise use core config for both values.
            $scheduleStart = $this->coreConfig->Site->systemMessagesScheduleStart;
            $scheduleEnd = $this->coreConfig->Site->systemMessagesScheduleEnd;
        }

        $scheduleStart = $scheduleStart ? new \DateTime($scheduleStart) : false;
        $scheduleEnd = $scheduleEnd ? new \DateTime($scheduleEnd) : false;
        $now = new \DateTime();
        $scheduleOk = !(($scheduleStart && $now < $scheduleStart)
            || ($scheduleEnd && $now > $scheduleEnd));

        if ($scheduleOk && !empty($this->coreConfig->Site->systemMessages)) {
            $messages = $getMessageFn(
                $this->coreConfig->Site->systemMessages->toArray(),
                $language
            );
        }

        if ($scheduleOk && !empty($this->localConfig->Site->systemMessages)) {
            $localMessages = $getMessageFn(
                $this->localConfig->Site->systemMessages->toArray(),
                $language
            );

            $messages = array_filter(array_merge($messages, $localMessages));
        }

        // Run all messages through translator for back-compat
        $messages = array_map([$this->translator, 'translate'], $messages);

        // Add messages from session
        foreach (($this->session['messages'] ?? []) as $key => $replace) {
            $messages[] = $this->translate($key, $replace);
        }

        return $messages;
    }
}
