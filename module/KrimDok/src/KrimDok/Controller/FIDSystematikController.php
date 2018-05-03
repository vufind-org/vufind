<?php
/**
 * FID Systematik Controller
 *
 * PHP version 5
 *
 * @category KrimDok
 * @package  Controller
 * @author   Dr. Johannes Ruscheinski <johannes.ruscheinski@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace KrimDok\Controller;

/**
 * FIDSystematikController Class
 *
 * Controls the Feedback
 *
 * @category VuFind2
 * @package  Controller
 * @author   Dr. Johannes Ruscheinski <johannes.ruscheinski@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class FIDSystematikController extends \VuFind\Controller\AbstractBase
{
    /**
     * Display Feedback home form.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        // no action needed
        return $this->createViewModel();
    }
}
