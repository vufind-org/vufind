<?php
namespace TueFindApi\Controller;

class ApiController extends \VuFindApi\Controller\ApiController
{
    public function indexAction()
    {
        // Disable session writes
        $this->disableSessionWrites();

        if (null === $this->getRequest()->getQuery('swagger')) {
            $urlHelper = $this->getViewRenderer()->plugin('url');
            $base = rtrim($urlHelper('home'), '/');
            $url = "$base/swagger-ui/?url="
                . urlencode("$base/api?swagger");
            return $this->redirect()->toUrl($url);
        }
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'application/json');
        $config = $this->getConfig();
        $params = [
            'config' => $config,
            'version' => \VuFind\Config\Version::getBuildVersion(),
            'specs' => $this->getApiSpecs()
        ];
        $json = $this->getViewRenderer()->render('api/swagger', $params);
        $response->setContent($json);
        return $response;
    }
}
?>
