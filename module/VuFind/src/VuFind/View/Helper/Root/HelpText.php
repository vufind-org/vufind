<?php
/**
 * "Load help text" view helper
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
 * "Load help text" view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class HelpText extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * The current language
     *
     * @var string
     */
    protected $language;

    /**
     * The default fallback language
     *
     * @var string
     */
    protected $defaultLanguage;

    /**
     * The context view helper
     *
     * @var Context
     */
    protected $contextHelper;

    /**
     * Warning messages
     *
     * @var array
     */
    protected $warnings = [];

    /**
     * Constructor
     *
     * @param Context $context         The context view helper
     * @param string  $language        The current user-selected language
     * @param string  $defaultLanguage The default fallback language
     */
    public function __construct(Context $context, $language, $defaultLanguage = 'en')
    {
        $this->contextHelper = $context;
        $this->language = $language;
        $this->defaultLanguage = $defaultLanguage;
    }

    /**
     * Get warnings generated during rendering (if any).
     *
     * @return array
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Render a help template (or return false if none found).
     *
     * @param string $name    Template name to render
     * @param array  $context Variables needed for rendering template; these will
     * be temporarily added to the global view context, then reverted after the
     * template is rendered (default = empty).
     *
     * @return string|bool
     */
    public function render($name, $context = null)
    {
        // Set up the needed context in the view:
        $this->contextHelper->__invoke($this->getView());
        $oldContext = $this->contextHelper
            ->apply(null === $context ? [] : $context);

        // Sanitize the template name to include only alphanumeric characters
        // or underscores.
        $safe_topic = preg_replace('/[^\w]/', '', $name);

        // Clear warnings
        $this->warnings = [];

        $resolver = $this->getView()->resolver();
        $tpl = "HelpTranslations/{$this->language}/{$safe_topic}.phtml";
        if ($resolver->resolve($tpl)) {
            $html = $this->getView()->render($tpl);
        } else {
            // language missing -- try default language
            $tplFallback = 'HelpTranslations/' . $this->defaultLanguage . '/'
                . $safe_topic . '.phtml';
            if ($resolver->resolve($tplFallback)) {
                $html = $this->getView()->render($tplFallback);
                $this->warnings[] = 'Sorry, but the help you requested is '
                    . 'unavailable in your language.';
            } else {
                // no translation available at all!
                $html = false;
            }
        }

        $this->contextHelper->restore($oldContext);
        return $html;
    }
}
