<?php
/**
 * AJAX handler for getting organisation info.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2018.
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
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use Finna\OrganisationInfo\OrganisationInfo;
use VuFind\Cookie\CookieManager;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Session\Settings as SessionSettings;
use Zend\Mvc\Controller\Plugin\Params;

/**
 * AJAX handler for getting organisation info.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetOrganisationInfo extends \VuFind\AjaxHandler\AbstractBase
    implements TranslatorAwareInterface, \Zend\Log\LoggerAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Cookie manager
     *
     * @var CookieManager
     */
    protected $cookieManager;

    /**
     * Organisation info
     *
     * @var OrganisationInfo
     */
    protected $organisationInfo;

    /**
     * Constructor
     *
     * @param SessionSettings  $ss               Session settings
     * @param CookieManager    $cookieManager    ILS connection
     * @param OrganisationInfo $organisationInfo Organisation info
     */
    public function __construct(SessionSettings $ss, CookieManager $cookieManager,
        OrganisationInfo $organisationInfo
    ) {
        $this->sessionSettings = $ss;
        $this->cookieManager = $cookieManager;
        $this->organisationInfo = $organisationInfo;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        $parent = $params->fromPost('parent', $params->fromQuery('parent'));
        if (empty($parent)) {
            return $this->handleError('getOrganisationInfo: missing parent');
        }
        $parent = is_array($parent) ? implode(',', $parent) : $parent;

        $reqParams = $params->fromPost('params', $params->fromQuery('params'));

        if (empty($reqParams['action'])) {
            return $this->handleError('getOrganisationInfo: missing action');
        }

        $cookieName = 'organisationInfoId';
        $cookie = $this->cookieManager->get($cookieName);
        $reqParams['orgType'] = 'library';
        $museumSource = [
            'museo', 'museum', 'kansallisgalleria', 'ateneum', 'musee',
            'nationalgalleri', 'gallery'
        ];
        foreach ($museumSource as $source) {
            $checkName = $this->translate("source_" . strtolower($parent));
            if (stripos($checkName, $source)) {
                $reqParams['orgType'] = 'museum';
                break;
            }
        }
        $action = $reqParams['action'];
        $buildings = isset($reqParams['buildings'])
            ? explode(',', $reqParams['buildings']) : null;

        $key = $parent;
        if ('details' === $action) {
            if (!isset($reqParams['id'])) {
                return $this->handleError('getOrganisationInfo: missing id');
            }
            if (isset($reqParams['id'])) {
                $id = $reqParams['id'];
                $expire = time() + 365 * 60 * 60 * 24; // 1 year
                $this->cookieManager->set($cookieName, $id, $expire);
            }
        }

        if (!isset($reqParams['id']) && $cookie) {
            $reqParams['id'] = $cookie;
        }

        if ('lookup' === $action) {
            $reqParams['link'] = $params->fromPost(
                'link', $params->fromQuery('link', false)
            );
            $reqParams['parentName'] = $params->fromPost(
                'parentName', $params->fromQuery('parentName', null)
            );
        }

        $lang = $this->translator->getLocale();
        $map = ['en-gb' => 'en'];

        if (isset($map[$lang])) {
            $lang = $map[$lang];
        }
        if (!in_array($lang, ['fi', 'sv', 'en'])) {
            $lang = 'fi';
        }

        try {
            $response = $this->organisationInfo->query(
                $parent, $reqParams, $buildings, $action
            );
        } catch (\Exception $e) {
            return $this->handleError(
                'getOrganisationInfo: '
                    . "error reading organisation info (parent $parent)",
                $e->getMessage()
            );
        }

        return $this->formatResponse($response);
    }

    /**
     * Return an error response in JSON format and log the error message.
     *
     * @param string $outputMsg  Message to include in the JSON response.
     * @param string $logMsg     Message to output to the error log.
     * @param int    $httpStatus HTTPs status of the JSOn response.
     *
     * @return \Zend\Http\Response
     */
    protected function handleError($outputMsg, $logMsg = '', $httpStatus = 400)
    {
        $this->logError(
            $outputMsg . ($logMsg ? " ({$logMsg})" : null)
        );

        return $this->formatResponse($outputMsg, $httpStatus);
    }
}
