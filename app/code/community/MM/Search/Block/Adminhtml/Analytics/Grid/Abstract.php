<?php
/**
 * Abstract Analytics Grid Block
 * 
 * Base class for analytics grid blocks to avoid code duplication
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
abstract class MM_Search_Block_Adminhtml_Analytics_Grid_Abstract extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * @var string Method name to call on engine for getting data
     */
    protected $_engineMethod = 'getPopularQueries';
    
    /**
     * @var string Grid action name for URL generation
     */
    protected $_gridAction = 'queriesGrid';

    /**
     * Constructor - called by child classes after setting properties
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setDefaultSort('count');
        $this->setDefaultDir('desc');
        $this->setSaveParametersInSession(false);
        $this->setUseAjax(false);
        $this->setPagerVisibility(false);
        $this->setFilterVisibility(false);
    }

    /**
     * Prepare collection from Typesense analytics
     *
     * @return MM_Search_Block_Adminhtml_Analytics_Grid_Abstract
     */
    protected function _prepareCollection()
    {
        $storeId = $this->getRequest()->getParam('store', 0);
        $helper = Mage::helper('mm_search');
        
        // Create a Varien_Data_Collection to hold our data
        $collection = new Varien_Data_Collection();
        
        try {
            $collectionName = $helper->getCollectionName($storeId);
            
            if (!empty($collectionName)) {
                $factory = Mage::getSingleton('mm_search/api_factory');
                $engine = $factory->createEngine($storeId);
                
                if ($engine->supportsAnalytics()) {
                    // Call the appropriate method (getPopularQueries or getNoHitsQueries)
                    $method = $this->_engineMethod;
                    $queries = $engine->$method($collectionName);
                    
                    $id = 1;
                    foreach ($queries as $query) {
                        $item = new Varien_Object();
                        $item->setId($id++);
                        $item->setQuery($query['q']);
                        $item->setCount($query['count']);
                        $collection->addItem($item);
                    }
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
        
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Prepare columns
     *
     * @return MM_Search_Block_Adminhtml_Analytics_Grid_Abstract
     */
    protected function _prepareColumns()
    {
        $helper = Mage::helper('mm_search');
        
        $this->addColumn('query', array(
            'header'    => $helper->__('Search Query'),
            'index'     => 'query',
            'type'      => 'text',
            'filter'    => false,
            'sortable'  => false,
        ));

        $this->addColumn('count', array(
            'header'    => $helper->__('Search Count'),
            'index'     => 'count',
            'type'      => 'number',
            'filter'    => false,
            'sortable'  => false,
            'width'     => '100px',
        ));

        return parent::_prepareColumns();
    }

    /**
     * Get grid URL for AJAX
     *
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/' . $this->_gridAction, array('_current' => true));
    }

    /**
     * Disable row click URL
     *
     * @param Varien_Object $row
     * @return string
     */
    public function getRowUrl($row)
    {
        return '';
    }
}