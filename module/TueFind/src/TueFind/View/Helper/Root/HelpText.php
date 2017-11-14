<?php

namespace TueFind\View\Helper\Root;

class HelpText extends \VuFind\View\Helper\Root\HelpText
{

    /**
     * Render a help template (or return false if none found).
     *
     * @param string $name    Template name to render
     * @param array  $context Variables needed for rendering template; these will
     * be temporarily added to the global view context, then reverted after the
     * template is rendered (default = empty).
     * @param string $directory the template directory to search in.
     *
     * @return string|bool
     */
    public function render($name, $context = null, $directory = "HelpTranslations")
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
        $tpl = "{$directory}/{$this->language}/{$safe_topic}.phtml";
        if ($resolver->resolve($tpl)) {
            $html = $this->getView()->render($tpl);
        } else {
            // language missing -- try default language
            $tplFallback = '{$directory}/' . $this->defaultLanguage . '/'
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
