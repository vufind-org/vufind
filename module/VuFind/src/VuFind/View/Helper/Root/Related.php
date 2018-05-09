<?php
/**
 * Related records view helper
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;

/**
 * Related records view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Related extends AbstractClassBasedTemplateRenderer
{
    /**
     * Plugin manager for related record modules.
     *
     * @var \VuFind\Related\PluginManager
     */
    protected $pluginManager;

    /**
     * Constructor
     *
     * @param \VuFind\Related\PluginManager $pluginManager Plugin manager for related
     * record modules.
     */
    public function __construct(\VuFind\Related\PluginManager $pluginManager)
    {
        $this->pluginManager = $pluginManager;
    }

    /**
     * Get a list of related records modules.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return array
     */
    public function getList(\VuFind\RecordDriver\AbstractBase $driver)
    {
        return $driver->getRelated($this->pluginManager);
    }

    /**
     * Render the output of a related records module.
     *
     * @param \VuFind\Related\RelatedInterface $related The related records object to
     * render
     *
     * @return string
     */
    public function render($related)
    {
        $template = 'Related/%s.phtml';
        $className = get_class($related);
        $context = ['related' => $related];
        return $this->renderClassTemplate($template, $className, $context);
    }
}
