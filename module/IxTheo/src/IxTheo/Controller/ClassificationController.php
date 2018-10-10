<?php

namespace IxTheo\Controller;
class ClassificationController extends \VuFind\Controller\AbstractBase
{
	/**
	 *
	 * @return \Zend\View\Model\ViewModel
	 */
	public function homeAction()
	{
             $notation = $this->params()->fromRoute('notation');
             if (empty($notation))
                 return $this->redirect()->toUrl('/Browse/IxTheoClassification?findby=alphabetical' .
                                                 '&query_field=ixtheo_notation_facet');
             if (preg_match("/^[a-zA-Z]$/", $notation))
                 return $this->redirect()->toUrl('/Browse/IxTheoClassification?findby=alphabetical&category=&query=' .
                                                 $notation . '%2A&facet_prefix=' . $notation . 
                                                 '&query_field=ixtheo_notation_facet&facet_field=ixtheo_notation_facet');
             else
                 return $this->redirect()->toUrl('/Search/Results?filter[]=ixtheo_notation_facet:' . $notation);
	}
}
?>
