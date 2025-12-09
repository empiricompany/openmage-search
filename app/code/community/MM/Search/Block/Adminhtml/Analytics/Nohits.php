<?php
/**
 * No Results Queries Container Block
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Block_Adminhtml_Analytics_Nohits extends MM_Search_Block_Adminhtml_Analytics_Abstract
{
    /**
     * @var string Redirect parameter for create rules action
     */
    protected $_redirectParam = 'nohits';
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_blockGroup = 'mm_search';
        $this->_controller = 'adminhtml_analytics_nohits';
        $this->_headerText = Mage::helper('mm_search')->__('Search Queries With No Results');
        
        parent::__construct();
    }
}