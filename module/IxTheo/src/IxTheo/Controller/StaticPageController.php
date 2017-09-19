<?php

namespace IxTheo\Controller;

class StaticPageController extends \VuFind\Controller\AbstractBase
{
    private function getLanguage()
    {
        return $this->getServiceLocator()->has('VuFind\Translator')
            ? $this->getServiceLocator()->get('VuFind\Translator')->getLocale()
            : $this->getDefaultLanguage();
    }

    private function getDefaultLanguage()
    {
        return 'en';
    }

    private function getPage()
    {
        return $this->params("page");
    }

    private function existsTemplate($template)
    {
        $resolver = $this->getEvent()
            ->getApplication()
            ->getServiceManager()
            ->get('Zend\View\Resolver\TemplatePathStack');

        return ($resolver->resolve($template) !== false);
    }

    private function getTemplate()
    {
        $lang = $this->getLanguage();
        $page = $this->getPage();
        $template = $this->getTemplatePath($lang, $page);
        if (!$this->existsTemplate($template)) {
            $template = $this->getTemplatePath($this->getDefaultLanguage(), $page);
        }
        if (!$this->existsTemplate($template)) {
            return false;
        }
        return $template;
    }

    private function getTemplatePath($lang, $page)
    {
        return "static/{$lang}/{$page}";
    }

    public function staticPageAction()
    {
        if ($template = $this->getTemplate()) {
            $view = $this->createViewModel();
            $view->setTemplate($template);
            return $view;
        }
        return $this->notFoundAction();
    }
}