<?php

namespace TueFind\Controller;

/**
 * This controller is used to redirect to a given URL and save it with a timestamp.
 * (e.g. to Track how many times an external service is used, without storing person-related data.)
 */
class RedirectController extends \VuFind\Controller\AbstractBase implements \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    /**
     * Decoder for URL in GET params
     * @var \TueFind\View\Helper\TueFind\TueFind
     */
    protected $decoder;

    /**
     * KfL service for license redirects
     * @var \TueFind\Service\KfL
     */
    protected $kfl;

    public function setDecoder(\TueFind\View\Helper\TueFind\TueFind $decoder) {
        $this->decoder = $decoder;
    }

    public function setKflService(\TueFind\Service\KfL $kfl)
    {
        $this->kfl = $kfl;
    }

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
            $url = $this->decoder->base64UrlDecode($url);
            $group = $this->params('group') ?? null;
            $this->getDbTable('redirect')->insertUrl($url, $group);
            $view = $this->createViewModel();
            $view->redirectTarget = $url;
            $view->redirectDelay = 0;
            return $view;
        }

        $this->getResponse()->setStatusCode(404);
    }

    public function licenseAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $id = $this->params()->fromRoute('id');
        $driver = $this->getRecordLoader()->load($id);
        $licenseUrl = $this->kfl->getUrl($driver);
        return $this->createViewModel(['driver' => $driver, 'licenseUrl' => $licenseUrl]);
    }
}
