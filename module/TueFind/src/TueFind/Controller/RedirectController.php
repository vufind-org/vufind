<?php

namespace TueFind\Controller;

/**
 * This controller is used to redirect to a given URL and save it with a timestamp.
 * (e.g. to Track how many times an external service is used, without storing person-related data.)
 */
class RedirectController extends \VuFind\Controller\AbstractBase implements \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    public function redirectAction()
    {
        /**
        * Use HTML Meta redirect page instead of HTTP header.
        * HTTP header redirect may fail when using php-fpm if the header
        * is larger than 8192 Bytes.
        *
        * See https://maxchadwick.xyz/blog/http-response-header-size-limit-with-mod-proxy-fcgi
        */
        if ($url = $this->params('url')) {
            // URL needs to be base64, else we will have problems with slashes,
            // even if they are url encoded
            $url = base64_decode($url);
            $group = $this->params('group') ?? null;
            $this->getDbTable('redirect')->insertUrl($url, $group);
            $view = $this->createViewModel();
            $view->redirectTarget = $url;
            $view->redirectDelay = 3;
            return $view;
        }

        $this->getResponse()->setStatusCode(404);
    }
}
