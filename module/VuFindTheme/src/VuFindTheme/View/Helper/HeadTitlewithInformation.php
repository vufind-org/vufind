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
class HeadTitlewithInformation extends \Laminas\View\Helper\AbstractHelper
{

    /**
     * Retrieve the Helper
     *
     * @return HeadTitlewithInformation
     */
    public function __invoke()
    {
      return $this;
    }

    /**
     * Get the Head title with additional information
     *
     * @return string
     */
    public function getHeadTitlewithInformation()
    {
        $configHelper = $this->getView()->plugin('config');
        $headTitleHelper = $this->getView()->plugin('headTitle');
        $translateHelper = $this->getView()->plugin('translate');
        $config = $configHelper->get('config');
        $ver = isset($config->Site->headTitle_ver)
          ? $config->Site->headTitle_ver : '';
        $sep = isset($config->Site->headTitle_sep)
          ? $config->Site->headTitle_sep : '';
        $pre = isset($config->Site->headTitle_pre)
          ? $config->Site->headTitle_pre : '';
        $pos = isset($config->Site->headTitle_pos)
          ? $config->Site->headTitle_pos : '';

        switch ($ver) {
            case "pre":
                $headTitle = $headTitleHelper->setPrefix($sep . $translateHelper($pre));
                break;
            case "pos":
                $headTitle = $headTitleHelper->setPostfix($sep . $translateHelper($pos));
                break;
            case "both":
                $headTitle = $headTitleHelper->setPrefix($sep . $translateHelper($pre))->setPostfix($sep . $translateHelper($pos));
                break;
            default:
                $headTitle = $headTitleHelper;
                break;
        }
        return $headTitle;
    }
}
