<?php
/**
 * Shibboleth Logout Notification API Controller
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Controller;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Stdlib\ResponseInterface as Response;

/**
 * Handles Shibboleth back-channel logout notifications.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ShibbolethLogoutNotificationController extends AbstractBase
{
    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->accessPermission = 'access.api.ShibbolethLogoutNotification';
        $this->accessDeniedBehavior = 'exception';
        parent::__construct($sm);
    }

    /**
     * GET method handler for the logout handler
     *
     * @return Response
     */
    public function getAction()
    {
        $this->disableSessionWrites();
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine(
            'Content-Type', 'application/wsdl+xml'
        );
        $response->setContent($this->getWsdl());
        return $response;
    }

    /**
     * POST method handler for the logout handler
     *
     * @return Response
     */
    public function postAction()
    {
        $this->disableSessionWrites();
        $soapServer = new \Zend\Soap\Server(
            'data://text/plain;base64,' . base64_encode($this->getWsdl())
        );
        $soapServer->setReturnResponse(true);
        $soapServer->setObject($this);
        $soapResponse = $soapServer->handle();
        if ($soapResponse instanceof \SoapFault) {
            $soapResponse = (string)$soapResponse;
        }
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'text/xml');
        $response->setContent($soapResponse);
        return $response;
    }

    /**
     * Logout notification handler
     *
     * @param string $sessionId External session id
     *
     * @return void
     */
    public function logoutNotification($sessionId)
    {
        $table = $this->getTable('ExternalSession');
        $row = $table->getByExternalSessionId(trim($sessionId));
        if (empty($row)) {
            return;
        }
        $sessionManager = $this->serviceLocator->get('VuFind\SessionManager');
        $handler = $sessionManager->getSaveHandler();
        $handler->destroy($row['session_id']);
        return;
    }

    /**
     * Get WSDL for the service
     *
     * @return string
     */
    protected function getWsdl()
    {
        list($uri) = explode('?', $this->getRequest()->getUriString());
        return <<<EOT
<?xml version ="1.0" encoding ="UTF-8" ?>
<definitions name="LogoutNotification"
  targetNamespace="urn:mace:shibboleth:2.0:sp:notify"
  xmlns:notify="urn:mace:shibboleth:2.0:sp:notify"
  xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
  xmlns="http://schemas.xmlsoap.org/wsdl/">

    <types>
       <schema targetNamespace="urn:mace:shibboleth:2.0:sp:notify"
           xmlns="http://www.w3.org/2000/10/XMLSchema"
           xmlns:notify="urn:mace:shibboleth:2.0:sp:notify">

            <simpleType name="string">
                <restriction base="string">
                    <minLength value="1"/>
                </restriction>
            </simpleType>

            <element name="OK" type="notify:OKType"/>
            <complexType name="OKType">
                <sequence/>
            </complexType>

        </schema>
    </types>

    <message name="getLogoutNotificationRequest">
        <part name="SessionID" type="notify:string"/>
    </message>

    <message name="getLogoutNotificationResponse" >
        <part name="OK"/>
    </message>

    <portType name="LogoutNotificationPortType">
        <operation name="LogoutNotification">
            <input message="getLogoutNotificationRequest"/>
            <output message="getLogoutNotificationResponse"/>
        </operation>
    </portType>

    <binding name="LogoutNotificationBinding"
        type="notify:LogoutNotificationPortType">
        <soap:binding style="rpc" transport="http://schemas.xmlsoap.org/soap/http"/>
        <operation name="LogoutNotification">
            <soap:operation
                soapAction="urn:xmethods-logout-notification#LogoutNotification"/>
        </operation>
    </binding>

    <service name="LogoutNotificationService">
          <port name="LogoutNotificationPort"
            binding="notify:LogoutNotificationBinding">
            <soap:address location="$uri"/>
          </port>
    </service>
</definitions>
EOT;
    }
}
