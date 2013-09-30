<?php
/**
 * VuFind controller base class (defines some methods that can be shared by other
 * controllers).
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace VuDL\Controller;
use Zend\Mvc\Controller\AbstractActionController;

/**
 * VuFind controller base class (defines some methods that can be shared by other
 * controllers).
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class RedirectController extends \VuFind\Controller\AbstractBase
{
    /**
     *
     *
     *
     */
    public function redirectAction()
    {
        $collection = $this->params()->fromRoute('collection');
        $file = $this->params()->fromRoute('file');
        $data = array(
            'type'  => 'tuples',
            'flush' => false,
            'lang'  => 'itql',
            'format'=> 'Simple',
            'query' => 'select $object from <#ri> where $object <http://digital.library.villanova.edu/rdf/relations#hasLegacyURL> '.
                "'http://digital.library.villanova.edu/" . $collection . '/' . $file . "'"
        );
        $module_config = $this->getServiceLocator()->get('config');
        $client = new \Zend\Http\Client($module_config['vudl']['query_url']);
        $client->setMethod('POST');
        $client->setAuth('fedoraAdmin', 'fedoraAdmin');
        $client->setParameterPost($data);
        $response = $client->send();
        $id = array();
        preg_match('/info:fedora\/([^>]+)/', $response->getBody(), $id);
        if (count($id) < 2) {
            $parts = explode('/', $file);
            $file = array_pop($parts);
            array_unshift($parts, $collection);
            $data['query'] = 'select $object from <#ri> where $object <http://digital.library.villanova.edu/rdf/relations#hasLegacyURL> '.
                "'http://digital.library.villanova.edu/" . str_replace('%2F', '/', rawurlencode(implode('/', $parts))) . '/' . $file . "'";
            //var_dump($data['query']);
            $client->setParameterPost($data);
            $response = $client->send();
            preg_match('/info:fedora\/([^>]+)/', $response->getBody(), $id);
        }
        if (count($id) > 1) {
            return $this->redirect()->toRoute('vudl-record', array('id'=>$id[1]));
        } else {
            throw new \Exception('Could not map legacy URL to ID. Please search for your desired record above.');
        }
    }
    
    public function aboutAction()
    {
        return $this->redirect()->toRoute('vudl-about');        
    }
    
    public function collectionAction()
    {
        return $this->redirect()->toRoute('vudl-default-collection');        
    }
}
