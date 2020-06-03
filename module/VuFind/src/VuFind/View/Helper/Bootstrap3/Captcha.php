<?php
/**
 * Captcha view helper
 *
 * PHP version 7
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
namespace VuFind\View\Helper\Bootstrap3;

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
class Captcha extends \VuFind\View\Helper\Root\Captcha
{
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
            return false;
        }

        if ($wrapHtml) {
            $html = '<div class="form-group">';
        }

        if (count($this->captchas) == 1) {
            $html .= '<label class="control-label">CAPTCHA:</label>';
            $html .= '<p>';
            $html .= $this->captchas[0]->getHtml();
            $html .= '</p>';
        } else {
            // nav
            $html .= '<label class="control-label">';
            $html .= 'Select your favorite CAPTCHA:';
            $html .= '</label>';
            $html .= '<ul class="nav nav-tabs">';
            foreach ($this->captchas as $i => $captcha) {
                $active = $i == 0 ? ' class="active"' : '';
                $html .= '<li' . $active . '>';
                $html .= '<a href="#' . $captcha->getId() . '" '
                      . 'role="tab" data-toggle="tab">' . $captcha->getId()
                      . '</a>';
                $html .= '</li>';
            }
            $html .= '</ul>';

            // panes
            $html .= '<div class="tab-content">';
            foreach ($this->captchas as $i => $captcha) {
                $active = $i == 0 ? ' active' : '';
                $html .= '<div class="tab-pane fade in' . $active
                      . '" id="' . $captcha->getId() . '">';
                $html .= $captcha->getHtml();
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        if ($wrapHtml) {
            $html .= '</div>';
        }

        return $html;
    }
}
