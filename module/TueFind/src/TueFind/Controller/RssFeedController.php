<?php

namespace TueFind\Controller;

use Zend\View\Model\ViewModel;

class RssFeedController extends \VuFind\Controller\AbstractBase {
    /**
     * Show RSS feed as HTML page.
     *
     * This Controller is simply used as redirect to rssfeed/full template.
     * We do not want a simple static page here, so we can put
     * the full and short templates in a separate folder.
     *
     * @return mixed
     */
    public function fullAction()
    {
        return new ViewModel();
    }
}
