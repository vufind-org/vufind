<?php
/**
 * Alma controller
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien für Sozialwissenschaften 2018.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFind\Controller;

use \VuFind\Controller\AbstractBase;
use \ZfcRbac\Service\AuthorizationServiceAwareInterface;
use \ZfcRbac\Service\AuthorizationServiceAwareTrait;
use \Zend\ServiceManager\ServiceLocatorInterface;
use \Zend\Http\Response as HttpResponse;
use \Zend\Http\Request as HttpRequest;
use \Zend\Mail as Mail;


class AlmaController extends AbstractBase implements AuthorizationServiceAwareInterface {
    
    use AuthorizationServiceAwareTrait;
    
    /**
     * Http service
     * @var \VuFindHttp\HttpService
     */
    protected $httpService;
    
    protected $httpResponse;
    protected $httpHeaders;
    
    public function __construct(ServiceLocatorInterface $sm) {
        parent::__construct($sm);
        $this->httpResponse = new HttpResponse();
        $this->httpHeaders = $this->httpResponse->getHeaders();
    }
    
    public function webhookAction() {
        $returnArray = [];
        $returnArray[] = 'Test Alma Webhook';
        $returnJson = json_encode($returnArray, JSON_PRETTY_PRINT);
        $this->httpHeaders->addHeaderLine('Content-type', 'application/json');
        $this->httpResponse->setStatusCode(200); // Set HTTP status code to Bad Request (400)
        $this->httpResponse->setContent($returnJson);
        return $this->httpResponse;
    }
}
?>