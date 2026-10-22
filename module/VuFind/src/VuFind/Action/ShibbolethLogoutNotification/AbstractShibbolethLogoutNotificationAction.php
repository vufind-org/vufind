<?php

/**
 * Abstract base class for Shibboleth logout notification actions.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\ShibbolethLogoutNotification;

use Laminas\Session\SessionManager;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractAction;
use VuFind\Db\Service\ExternalSessionServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Abstract base class for Shibboleth logout notification actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractShibbolethLogoutNotificationAction extends AbstractAction
{
    /**
     * Constructor.
     *
     * @param SessionManager                  $sessionManager         Session manager
     * @param ExternalSessionServiceInterface $externalSessionService External session database service
     */
    public function __construct(
        protected SessionManager $sessionManager,
        #[Autowire(container: DbServicePluginManager::class)]
        protected ExternalSessionServiceInterface $externalSessionService,
    ) {
        parent::__construct();
    }

    /**
     * Get WSDL for the service.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return string
     */
    protected function getWsdl(ServerRequestInterface $request): string
    {
        [$uri] = explode('?', (string)$request->getUri());
        return <<<EOT
            <?xml version="1.0" encoding="UTF-8" ?>
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
