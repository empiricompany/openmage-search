<?php
/**
 * No Results Queries Grid Block
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Block_Adminhtml_Analytics_Nohits_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('nohitsQueriesGrid');
        $this->setDefaultSort('count');
        $this->setDefaultDir('desc');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    /**
     * Prepare collection
     *
     * @return MM_Search_Block_Adminhtml_Analytics_Nohits_Grid
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
                $api = Mage::getModel('mm_search/api', $storeId);
                $engine = $api->getEngine();
                
                if ($engine->supportsAnalytics()) {
                    $queries = $engine->getNoHitsQueries($collectionName, 1000);
                    
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
     * @return MM_Search_Block_Adminhtml_Analytics_Nohits_Grid
     */
    protected function _prepareColumns()
    {
        $this->addColumn('query', array(
            'header'    => Mage::helper('mm_search')->__('Search Query'),
            'index'     => 'query',
            'type'      => 'text',
            'filter'    => false,
            'sortable'  => false,
        ));

        $this->addColumn('count', array(
            'header'    => Mage::helper('mm_search')->__('Search Count'),
            'index'     => 'count',
            'type'      => 'number',
            'filter'    => false,
            'sortable'  => false,
            'width'     => '100px',
        ));

        return parent::_prepareColumns();
    }

    /**
     * Get grid URL
     *
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/nohitsGrid', array('_current' => true));
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