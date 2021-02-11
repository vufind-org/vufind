<?php
namespace IxTheo\Db\Row;

/**
 * Row Definition for session
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Oliver Obenland <oliver.obenland@uni-tuebingen.de>
 * @author   Johannes Riedl <johannes.riedl@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PDASubscription extends \VuFind\Db\Row\RowGateway
{
    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'ixtheo_pda_subscriptions', $adapter);
    }
}
