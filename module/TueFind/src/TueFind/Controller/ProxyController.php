<?php
/**
 * Proxy Controller Module
 *
 * @category    TueFind
 * @author      Johannes Ruscheinski <johannes.ruscheinski@uni-tuebingen.de>
 * @copyright   2015-2017 Universtitätsbibliothek Tübingen
 */
namespace TueFind\Controller;

use VuFind\Exception\BadRequest as BadRequestException;
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
    const DNB_REGEX = '#^http(s)?://services\.dnb\.de/fize-service/gvr/#';
    const OPEN_STREETMAP_REGEX = '#^http(s)?://[a-z]+\.tile\.openstreetmap\.org#';
    const WHITE_LIST_REGEXES = [self::DNB_REGEX, self::OPEN_STREETMAP_REGEX];

    public function loadAction()
    {
        $requestUri = $this->getRequest()->getUri()->getQuery();
        $url = urldecode(strstr($requestUri, 'http'));
        if ($url == '')
            throw new BadRequestException('No valid target URL specified.');

        $matched = false;
        foreach (self::WHITE_LIST_REGEXES as $regex) {
            if (preg_match($regex, $url)) {
                $matched = true;
                break;
            }
        }

        if (!$matched)
            throw new ForbiddenException('The specified target URL is not allowed: ' . $url);

        $client = $this->serviceLocator->get('VuFind\Http')->createClient();
        return $client->setUri($url)->send();
    }
}
