<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Db\Adapter\Platform;

use Zend\Db\Adapter\Driver\DriverInterface;
use Zend\Db\Adapter\Driver\Pdo;
use Zend\Db\Adapter\Exception;

class SqlServer extends AbstractPlatform
{
    /**
     * {@inheritDoc}
     */
    protected $quoteIdentifier = array('[',']');

    /**
     * {@inheritDoc}
     */
    protected $quoteIdentifierTo = '\\';

    /**
     * @var resource|\PDO
     */
    protected $resource = null;

    /**
     * @param null|\Zend\Db\Adapter\Driver\Sqlsrv\Sqlsrv|\Zend\Db\Adapter\Driver\Pdo\Pdo|resource|\PDO $driver
     */
    public function __construct($driver = null)
    {
        if ($driver) {
            $this->setDriver($driver);
        }
    }

    /**
     * @param \Zend\Db\Adapter\Driver\Sqlsrv\Sqlsrv|\Zend\Db\Adapter\Driver\Pdo\Pdo|resource|\PDO $driver
     * @throws \Zend\Db\Adapter\Exception\InvalidArgumentException
     *
     * @return self
     */
    public function setDriver($driver)
    {
        // handle Zend\Db drivers
        if (($driver instanceof Pdo\Pdo && in_array($driver->getDatabasePlatformName(), array('SqlServer', 'Dblib')))
            || (($driver instanceof \PDO && in_array($driver->getAttribute(\PDO::ATTR_DRIVER_NAME), array('sqlsrv', 'dblib'))))
        ) {
            $this->resource = $driver;
            return $this;
        }

        throw new Exception\InvalidArgumentException('$driver must be a Sqlsrv PDO Zend\Db\Adapter\Driver or Sqlsrv PDO instance');
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'SQLServer';
    }

    /**
     * {@inheritDoc}
     */
    public function getQuoteIdentifierSymbol()
    {
        return $this->quoteIdentifier;
    }

    /**
     * {@inheritDoc}
     */
    public function quoteIdentifierChain($identifierChain)
    {
        return '[' . implode('].[', (array) $identifierChain) . ']';
    }

    /**
     * {@inheritDoc}
     */
    public function quoteValue($value)
    {
        if ($this->resource instanceof DriverInterface) {
            $this->resource = $this->resource->getConnection()->getResource();
        }
        if ($this->resource instanceof \PDO) {
            return $this->resource->quote($value);
        }
        trigger_error(
            'Attempting to quote a value in ' . __CLASS__ . ' without extension/driver support '
                . 'can introduce security vulnerabilities in a production environment.'
        );

        return '\'' . str_replace('\'', '\'\'', addcslashes($value, "\000\032")) . '\'';
    }

    /**
     * {@inheritDoc}
     */
    public function quoteTrustedValue($value)
    {
        if ($this->resource instanceof DriverInterface) {
            $this->resource = $this->resource->getConnection()->getResource();
        }
        if ($this->resource instanceof \PDO) {
            return $this->resource->quote($value);
        }
        return '\'' . str_replace('\'', '\'\'', $value) . '\'';
    }
}
