<?php

/**
 * GoogleTagManager view helper
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\View\Helper\Root;

/**
 * GoogleTagManager view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class GoogleTagManager extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * GTM Container ID (false if disabled)
     *
     * @var string|bool
     */
    protected $gtmContainerId;

    /**
     * Constructor
     *
     * @param string|bool $gtmContainerId Container ID (false if disabled)
     */
    public function __construct($gtmContainerId)
    {
        $this->gtmContainerId = $gtmContainerId;
    }

    /**
     * Returns GTM code block meant for the <head> element.
     *
     * @return string
     */
    public function getHeadCode()
    {
        if (!$this->gtmContainerId) {
            return '';
        }

        // phpcs:disable -- line length should be kept for this vendor snippet
        $js = <<<END
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;var n=d.querySelector('[nonce]');
            n&&j.setAttribute('nonce',n.nonce||n.getAttribute('nonce'));f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','{$this->gtmContainerId}');
            END;
        // phpcs:enable
        $inlineScript = $this->getView()->plugin('inlinescript');
        $js = $inlineScript(\Laminas\View\Helper\HeadScript::SCRIPT, $js, 'SET');
        return $js;
    }

    /**
     * Returns GTM code block meant for the <body> element.
     *
     * @return string
     */
    public function getBodyCode()
    {
        if (!$this->gtmContainerId) {
            return '';
        }

        // phpcs:disable -- line length should be kept for this vendor snippet
        $js = <<<END
            <!-- Google Tag Manager (noscript) -->
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$this->gtmContainerId}"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
            <!-- End Google Tag Manager (noscript) -->
            END;
        // phpcs:enable
        return $js;
    }
}
