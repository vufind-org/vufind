<?php
/**
 * Content page view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Content page view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Content extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Page heading
     *
     * @var string
     */
    protected $heading;

    /**
     * Returns content page view helper.
     *
     * @return Finna\View\Helper\Root\Content
     */
    public function __invoke()
    {
        return $this;
    }

    /**
     * Set content page title.
     *
     * @param string $heading Heading.
     *
     * @return string
     */
    public function setHeading($heading)
    {
        return $this->heading = $heading;
    }

    /**
     * Returns content page heading.
     *
     * @return string
     */
    public function getHeading()
    {
        return $this->heading;
    }

    /**
     * Try to find the best template for the current language
     *
     * @param string $templateName Template name without .phtml suffix
     *
     * @return string|boolean Template name or false if not available
     */
    public function findTemplateForLng($templateName)
    {
        $lng = $this->view->layout()->userLang;
        $resolver = $this->view->resolver();

        $template = "{$templateName}_$lng.phtml";
        if ($resolver->resolve($template)) {
            return $template;
        }
        $template = "$templateName.phtml";
        if ($resolver->resolve($template)) {
            return $template;
        }
        return false;
    }
}
