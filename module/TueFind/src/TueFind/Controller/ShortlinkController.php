<?php

namespace TueFind\Controller;

use VuFind\UrlShortener\UrlShortenerInterface;

class ShortlinkController extends \VuFind\Controller\ShortlinkController
{
    /**
     * Override to use HTML Meta redirect page instead of HTTP header.
     * HTTP header redirect may fail when using php-fpm if the header
     * is larger than 8192 Bytes.
     *
     * See https://maxchadwick.xyz/blog/http-response-header-size-limit-with-mod-proxy-fcgi
     *
     * This override might not be necessary any longer in VuFind 6.1 and above
     */
    public function redirectAction()
    {
        if ($id = $this->params('id')) {
            $resolver = $this->serviceLocator->get(UrlShortenerInterface::class);
            if ($url = $resolver->resolve($id)) {
                $view = $this->createViewModel();
                $view->redirectTarget = $url;
                $view->redirectDelay = 3;
                return $view;
            }
        }

        $this->getResponse()->setStatusCode(404);
    }
}
