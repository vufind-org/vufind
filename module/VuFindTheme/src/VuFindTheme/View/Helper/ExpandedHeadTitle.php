<?php
/**
 * Head Title with additional Information View Helper
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Gregor Gawol <gawol@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFindTheme\View\Helper;

/**
 * Head Title with additional Information View Helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Gregor Gawol <gawol@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ExpandedHeadTitle extends \Laminas\View\Helper\AbstractHelper
{

    /**
     * Retrieve the Helper
     *
     * @return ExpandedHeadTitle
     */
    public function __invoke()
    {
        $configHelper = $this->getView()->plugin('config');
        $headTitleHelper = $this->getView()->plugin('headTitle');
        $translateHelper = $this->getView()->plugin('translate');
        $config = $configHelper->get('config');
        // Version of what want to see
        $style = $config->Site->expandedHeadTitle_style ?? '';
        $sep = $config->Site->expandedHeadTitle_sep ?? '';
        $pre = $config->Site->expandedHeadTitle_pre ?? '';
        $post = $config->Site->expandedHeadTitle_post ?? '';

        switch ($style) {
          case "pre":
              return $headTitleHelper->setPrefix($translateHelper($pre). $sep);
          case "post":
              return $headTitleHelper->setPostfix($sep . $translateHelper($post));
          case "both":
             return $headTitleHelper->setPrefix($translateHelper($pre). $sep)->setPostfix($sep . $translateHelper($post));
          default:
              return $headTitleHelper;
        }
    }
}
