<?php
/**
 * Proxy Controller Module
 *
 * @category    KrimDok
 * @author      Johannes Ruscheinski <johannes.ruscheinski@uni-tuebingen.de>
 * @copyright   2015-2017 Universtitätsbibliothek Tübingen
 */
namespace KrimDok\Controller;

use VuFind\Exception\Forbidden as ForbiddenException;

/**
 * This controller handles global web proxy functionality.
 *
 * @package  Controller
 * @author   Johannes Ruscheinski <johannes.ruscheinski@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class StaticPagesController extends \VuFind\Controller\AbstractBase {
    public function catalogsAction() {
        return $this->createViewModel();
    }
}
?>