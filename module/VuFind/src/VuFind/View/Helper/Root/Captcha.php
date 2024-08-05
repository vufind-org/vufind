<?php

/**
 * Captcha view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use function count;

/**
 * Captcha view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Captcha extends \Laminas\View\Helper\AbstractHelper
{
    use ClassBasedTemplateRendererTrait;

    /**
     * Captcha services
     *
     * @var array
     */
    protected $captchas = [];

    /**
     * Config
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $config   Config
     * @param array                  $captchas Captchas
     */
    public function __construct(
        \Laminas\Config\Config $config,
        array $captchas = []
    ) {
        $this->config = $config;
        $this->captchas = $captchas;
    }

    /**
     * Return this object
     *
     * @return \VuFind\View\Helper\Root\Captcha
     */
    public function __invoke(): \VuFind\View\Helper\Root\Captcha
    {
        return $this;
    }

    /**
     * Generate HTML of a single CAPTCHA (redirect to template)
     *
     * @param \VuFind\Captcha\AbstractBase $captcha Captcha
     *
     * @return string
     */
    public function getHtmlForCaptcha(\VuFind\Captcha\AbstractBase $captcha): string
    {
        return $this->renderClassTemplate(
            'Captcha/%s',
            strtolower($captcha::class),
            ['captcha' => $captcha]
        );
    }

    /**
     * Generate HTML depending on CAPTCHA type (empty if not active).
     *
     * @param bool $useCaptcha Boolean of active state, for compact templating
     * @param bool $wrapHtml   Wrap in a form-group?
     *
     * @return string
     */
    public function html(bool $useCaptcha = true, bool $wrapHtml = true): string
    {
        if (count($this->captchas) == 0 || !$useCaptcha) {
            return '';
        }

        return $this->getView()->render(
            'Helpers/captcha',
            ['wrapHtml' => $wrapHtml,
                                'captchas' => $this->captchas]
        );
    }

    /**
     * Get list of URLs with JS dependencies to load for the active CAPTCHA type.
     *
     * @return array
     */
    public function js(): array
    {
        $jsIncludes = [];
        foreach ($this->captchas as $captcha) {
            $jsIncludes = array_merge($jsIncludes, $captcha->getJsIncludes());
        }
        return array_unique($jsIncludes);
    }

    /**
     * Return whether Captcha is active in the config
     *
     * @return bool
     */
    protected function active(): bool
    {
        return count($this->captchas) > 0
            && isset($this->config->Captcha->forms);
    }
}
