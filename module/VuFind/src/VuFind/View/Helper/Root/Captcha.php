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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Renderer\RendererInterface;
use Laminas\View\Resolver\ResolverInterface;
use VuFind\ServiceManager\Factory\Autowire;

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
class Captcha
{
    use ClassBasedTemplateRendererTrait;

    /**
     * Constructor
     *
     * @param \VuFind\Config\Config $config       Config
     * @param array                 $captchas     Captchas
     * @param RendererInterface     $viewRenderer View renderer
     * @param ResolverInterface     $viewResolver View resolver
     */
    public function __construct(
        #[Autowire(service: 'VuFind\Config\PluginManager')]
        protected \VuFind\Config\Config $config,
        protected array $captchas,
        #[Autowire(service: 'ViewRenderer')]
        RendererInterface $viewRenderer,
        #[Autowire(service: 'ViewResolver')]
        ResolverInterface $viewResolver
    ) {
        $this->viewRenderer = $viewRenderer;
        $this->viewResolver = $viewResolver;
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

        return $this->viewRenderer->render(
            'Helpers/captcha',
            [
                'wrapHtml' => $wrapHtml,
                'captchas' => $this->captchas,
            ]
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
