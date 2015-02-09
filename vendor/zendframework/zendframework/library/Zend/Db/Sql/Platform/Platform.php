<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Db\Sql\Platform;

use Zend\Db\Adapter\AdapterInterface;

class Platform extends AbstractPlatform
{
    /**
     * @var AdapterInterface
     */
    protected $adapter = null;

    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        $platform = $adapter->getPlatform();
        switch (strtolower($platform->getName())) {
            case 'mysql':
                $platform = new Mysql\Mysql();
                $this->decorators = $platform->decorators;
                break;
            case 'sqlserver':
                $platform = new SqlServer\SqlServer();
                $this->decorators = $platform->decorators;
                break;
            case 'oracle':
                $platform = new Oracle\Oracle();
                $this->decorators = $platform->decorators;
                break;
            case 'ibm db2':
            case 'ibm_db2':
            case 'ibmdb2':
                $platform = new IbmDb2\IbmDb2();
                $this->decorators = $platform->decorators;
            default:
        }
    }
}
