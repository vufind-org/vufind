<?php
namespace TueFindApi\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'TueFindApi\Controller\MltApiController' => 'TueFindApi\Controller\ApiControllerFactory'
        ],
        'aliases' => [
            'MltApi' => 'TueFindApi\Controller\MltApiController'
        ],
     ],
     'router' => [
         'routes' => [
             'mltApiv1' => [
                 'type' => 'Laminas\Router\Http\Literal',
                 'verb' => 'get,post,options',
                 'options' => [
                      'route'    => '/api/v1/mlt',
                      'defaults' => [
                          'controller' => 'MltApi',
                          'action'     => 'similar',
                      ]
              ],
          ],
      ],
   ], 
];

return $config;
