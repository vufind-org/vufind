<?php

namespace TueFind\Controller;

class StaticPageController extends \VuFind\Controller\AbstractBase
{
    public function staticPageAction()
    {
        // Use HelpText View Helper
        $helper = $this->serviceLocator->get('ViewHelperManager')->get('HelpText');
        $template = $helper->getTemplate($this->params("page"), 'static');
        if ($template) {
            $view = $this->createViewModel();
            $view->setTemplate($template[1]);
            return $view;
        }
        return $this->notFoundAction();
    }
}
