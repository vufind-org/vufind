<?php
/**
 * Proxy Controller Module
 *
 * @category    TuFind
 * @author      Johannes Ruscheinski <johannes.ruscheinski@uni-tuebingen.de>
 * @copyright   2015-2017 Universtitätsbibliothek Tübingen
 */
namespace TuFind\Controller;

use VuFind\Exception\Forbidden as ForbiddenException;

/**
 * This controller handles global web proxy functionality.
 *
 * @package  Controller
 * @author   Johannes Ruscheinski <johannes.ruscheinski@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class ProxyController extends \VuFind\Controller\AbstractBase
{
    const DNB_REGEX = '#http://services.dnb.de/fize-service/gvr/.*#';
    const WHITE_LIST_REGEX = ProxyController::DNB_REGEX;

    public function loadAction()
    {
        $requestUri = $this->getRequest()->getUri()->getQuery();
        $url = urldecode(strstr($requestUri, 'http'));
        if (preg_match(ProxyController::WHITE_LIST_REGEX, $url)) {
            $client = $this->serviceLocator->get('VuFind\Http')->createClient();
            return $client->setUri($url)->send();
        } else {
            throw new ForbiddenException('Access denied.');
        }
    }
}
