<?php
/**
 * No Results Queries Grid Block
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Block_Adminhtml_Analytics_Nohits_Grid extends MM_Search_Block_Adminhtml_Analytics_Grid_Abstract
{
    /**
     * @var string Method name to call on engine for getting data
     */
    protected $_engineMethod = 'getNoHitsQueries';
    
    /**
     * @var string Grid action name for URL generation
     */
    protected $_gridAction = 'nohitsGrid';
    
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('nohitsQueriesGrid');
    }
}