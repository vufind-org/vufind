<?php
namespace IxTheo\Db\Row;

/**
 * Row Definition for ixtheo_user
 *
 * @category VuFind
 * @package  Db_Row
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class IxTheoUser extends \VuFind\Db\Row\RowGateway
{
    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'ixtheo_user', $adapter);
    }
}
