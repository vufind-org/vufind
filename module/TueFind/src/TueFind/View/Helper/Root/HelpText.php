<?php

namespace TueFind\View\Helper\Root;

class HelpText extends \VuFind\View\Helper\Root\HelpText
{
    /**
     * Check if a template exists, depending on user+default language
     *
     * @param string $name
     * @param string $directory
     * @return mixed false if not found
     *               [true,  tpl_path] if user language exists
     *               [false, tpl_path] if only fallback language exists
     */
    public function getTemplate($name, $directory) {
        $resolver = $this->getView()->resolver();
        $tpl = "{$directory}/{$this->language}/{$name}.phtml";
        if ($resolver->resolve($tpl)) {
            return [true, $tpl];
        } else {
            // language missing -- try default language
            $tplFallback = "{$directory}/" . $this->defaultLanguage . '/'
                . $name . '.phtml';
            if ($resolver->resolve($tplFallback)) {
                return [false, $tplFallback];
            } else {
                // no translation available at all!
                die($tplFallback);
                return false;
            }
        }
    }

    /**
     * Render a help template (or return false if none found).
     *
     * @param string $name      Template name to render
     * @param array $context    Variables needed for rendering template; these will
     *                          be temporarily added to the global view context, then reverted after the
     *                          template is rendered (default = empty).
     * @param string $directory The template directory to search in.
     * @param bool $warning     Output warning if only the fallback language is available
     * @return string|bool      HTML-String or false
     */
    public function render($name, $context = null, $directory = "HelpTranslations", $warning = true)
    {
        // Set up the needed context in the view:
        $this->contextHelper->__invoke($this->getView());
        $oldContext = $this->contextHelper
            ->apply(null === $context ? [] : $context);

        // Clear warnings
        $this->warnings = [];

        $tpl_result = $this->getTemplate($name, $directory);
        if ($tpl_result === false) {
            $html = false;
        } else {
            if ($tpl_result[0] === false) {
                $this->warnings[] = 'Sorry, but the help you requested is '
                    . 'unavailable in your language.';
            }
            $html = $this->getView()->render($tpl_result[1]);
        }

        $this->contextHelper->restore($oldContext);
        return $html;
    }
}
