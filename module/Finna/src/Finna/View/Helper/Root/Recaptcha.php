<?php
/**
 * Recaptcha view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2017.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Recaptcha view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Recaptcha extends \VuFind\View\Helper\Root\Recaptcha
{
    /**
     * Generate <div> with ReCaptcha from render.
     *
     * @param bool $useRecaptcha Boolean of active state, for compact templating
     * @param bool $wrapHtml     Include prefix and suffix?
     *
     * @return string $html
     */
    public function html($useRecaptcha = true, $wrapHtml = true)
    {
        if (!isset($useRecaptcha) || !$useRecaptcha) {
            return false;
        }
        $result = parent::html($useRecaptcha, $wrapHtml);
        $inlineScript = $this->getView()->plugin('inlinescript');
        $lang = $this->getView()->layout()->userLang;
        $recaptchaUrl = 'https://www.google.com/recaptcha/api.js'
            . "?onload=recaptchaOnLoad&render=explicit&hl=$lang";
        $result .= <<<EOT
<script type="text/javascript">
if (typeof window.recaptchaLoaded === 'undefined') {
    window.recaptchaLoaded = 1;
    $.getScript("$recaptchaUrl");
}
</script>
EOT;
        return $result;
    }
}
