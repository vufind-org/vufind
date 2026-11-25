<?php

/**
 * GoogleTagManager view helper.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\View\Helper\Root;

use VuFind\ServiceManager\Factory\Autowire;
use VuFindTheme\View\Helper\AssetManager;

/**
 * GoogleTagManager view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class GoogleTagManager
{
    /**
     * Constructor.
     *
     * @param string|bool  $gtmContainerId Container ID (false if disabled)
     * @param AssetManager $assetManager   Asset manager helper
     */
    public function __construct(
        #[Autowire(config: 'config', path: 'GoogleTagManager/gtmContainerId', default: false)]
        protected $gtmContainerId,
        #[Autowire(container: 'ViewHelperManager')]
        protected AssetManager $assetManager
    ) {
    }

    /**
     * Returns GTM code block meant for the <head> element.
     *
     * @return string
     */
    public function getHeadCode(): string
    {
        if (!$this->gtmContainerId) {
            return '';
        }

        $js = <<<END
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;var n=d.querySelector('[nonce]');
            n&&j.setAttribute('nonce',n.nonce||n.getAttribute('nonce'));f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','{$this->gtmContainerId}');
            END;
        return $this->assetManager->outputInlineScriptString($js);
    }

    /**
     * Returns GTM code block meant for the <body> element.
     *
     * @return string
     */
    public function getBodyCode(): string
    {
        if (!$this->gtmContainerId) {
            return '';
        }

        return <<<END
            <!-- Google Tag Manager (noscript) -->
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$this->gtmContainerId}"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
            <!-- End Google Tag Manager (noscript) -->
            END;
    }

    /**
     * Make helper invokable.
     *
     * @return static
     */
    public function __invoke(): static
    {
        return $this;
    }
}
