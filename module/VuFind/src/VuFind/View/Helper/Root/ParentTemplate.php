<?php
/**
 * Slot view helper
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;

use Zend\View\Resolver\TemplatePathStack;

/**
 * Slot view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ParentTemplate extends \Zend\View\Helper\AbstractHelper
{
    protected $templatePathStack;

    public function __construct($templatePathStack)
    {
        $this->templatePathStack = $templatePathStack;
    }

    public function __invoke($template, $targetParent = null)
    {
        $paths = $this->templatePathStack->getPaths();
        $paths->next();
        while(
            !file_exists($paths->current() . $template) ||
            (!empty($targetParent) && !strstr($paths->current(), $targetParent))
        ) {
            $paths->next();
        }
        if ($paths->current() == null) {
            throw new \Exception('not found in parent themes: ' . $template);
        }
        return $paths->current() . $template;
    }
}
