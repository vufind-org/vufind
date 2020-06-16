<?php
/**
 * Helper class for system messages
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2017.
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

use Laminas\Config\Config;

/**
 * Helper class for system messages
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SystemMessages extends \Laminas\View\Helper\AbstractHelper
    implements \VuFind\I18n\Translator\TranslatorAwareInterface
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
     * Constructor
     *
     * @param Config $coreConfig  Configuration
     * @param Config $localConfig Local configuration
     */
    public function __construct(Config $coreConfig, Config $localConfig)
    {
        $this->coreConfig = $coreConfig;
        $this->localConfig = $localConfig;
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

        if (!empty($this->coreConfig->Site->systemMessages)) {
            $messages = $getMessageFn(
                $this->coreConfig->Site->systemMessages->toArray(), $language
            );
        }

        if (!empty($this->localConfig->Site->systemMessages)) {
            $localMessages = $getMessageFn(
                $this->localConfig->Site->systemMessages->toArray(), $language
            );

            $messages = array_filter(array_merge($messages, $localMessages));
        }

        // Run all messages through translator for back-compat
        $messages = array_map([$this->translator, 'translate'], $messages);

        return $messages;
    }
}
