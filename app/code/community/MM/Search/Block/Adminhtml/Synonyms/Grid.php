<?php
/**
 * Synonyms Grid Block
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Block_Adminhtml_Synonyms_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('synonymsGrid');
        $this->setDefaultSort('id');
        $this->setDefaultDir('asc');
        $this->setSaveParametersInSession(false);
        $this->setUseAjax(false);
        $this->setPagerVisibility(false);
        $this->setFilterVisibility(false);
    }

    /**
     * Prepare collection from Typesense
     *
     * @return MM_Search_Block_Adminhtml_Synonyms_Grid
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
                
                if ($engine->supportsSynonyms()) {
                    $synonyms = $engine->getSynonyms($collectionName);
                    
                    foreach ($synonyms as $synonym) {
                        $item = new Varien_Object();
                        $item->setId($synonym['id']);
                        $item->setSynonymId($synonym['id']);
                        $item->setRoot($synonym['root']);
                        $item->setSynonymTerms(implode(', ', $synonym['synonyms']));
                        $item->setSynonymType(empty($synonym['root']) ? 'Multi-way' : 'One-way');
                        $item->setSynonymCount(count($synonym['synonyms']));
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
     * @return MM_Search_Block_Adminhtml_Synonyms_Grid
     */
    protected function _prepareColumns()
    {
        $helper = Mage::helper('mm_search');
        
        $this->addColumn('synonym_id', array(
            'header'    => $helper->__('ID'),
            'index'     => 'synonym_id',
            'type'      => 'text',
            'width'     => '200px',
            'filter'    => false,
            'sortable'  => false,
        ));
        
        $this->addColumn('synonym_type', array(
            'header'    => $helper->__('Type'),
            'index'     => 'synonym_type',
            'type'      => 'text',
            'width'     => '100px',
            'filter'    => false,
            'sortable'  => false,
        ));
        
        $this->addColumn('root', array(
            'header'    => $helper->__('Root Term'),
            'index'     => 'root',
            'type'      => 'text',
            'width'     => '150px',
            'filter'    => false,
            'sortable'  => false,
            'frame_callback' => array($this, 'decorateRoot'),
        ));
        
        $this->addColumn('synonym_terms', array(
            'header'    => $helper->__('Synonyms'),
            'index'     => 'synonym_terms',
            'type'      => 'text',
            'filter'    => false,
            'sortable'  => false,
        ));
        
        $this->addColumn('synonym_count', array(
            'header'    => $helper->__('Terms'),
            'index'     => 'synonym_count',
            'type'      => 'number',
            'width'     => '60px',
            'filter'    => false,
            'sortable'  => false,
        ));
        
        $this->addColumn('action', array(
            'header'    => $helper->__('Action'),
            'width'     => '100px',
            'type'      => 'action',
            'getter'    => 'getSynonymId',
            'actions'   => array(
                array(
                    'caption' => $helper->__('Edit'),
                    'url'     => array(
                        'base' => '*/*/edit',
                        'params' => array('store' => $this->getRequest()->getParam('store', 0))
                    ),
                    'field'   => 'id'
                ),
                array(
                    'caption' => $helper->__('Delete'),
                    'url'     => array(
                        'base' => '*/*/delete',
                        'params' => array('store' => $this->getRequest()->getParam('store', 0))
                    ),
                    'field'   => 'id',
                    'confirm' => $helper->__('Are you sure you want to delete this synonym?')
                )
            ),
            'filter'    => false,
            'sortable'  => false,
            'is_system' => true,
        ));

        return parent::_prepareColumns();
    }

    /**
     * Prepare mass action
     *
     * @return MM_Search_Block_Adminhtml_Synonyms_Grid
     */
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('synonym_id');
        $this->getMassactionBlock()->setFormFieldName('synonym_ids');
        
        $this->getMassactionBlock()->addItem('delete', array(
            'label'   => Mage::helper('mm_search')->__('Delete'),
            'url'     => $this->getUrl('*/*/massDelete', array('store' => $this->getRequest()->getParam('store', 0))),
            'confirm' => Mage::helper('mm_search')->__('Are you sure you want to delete selected synonyms?')
        ));
        
        return $this;
    }

    /**
     * Get row URL
     *
     * @param Varien_Object $row
     * @return string
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', array(
            'id' => $row->getSynonymId(),
            'store' => $this->getRequest()->getParam('store', 0)
        ));
    }

    /**
     * Decorate root column - show dash if empty
     *
     * @param string $value
     * @param Varien_Object $row
     * @param Mage_Adminhtml_Block_Widget_Grid_Column $column
     * @param bool $isExport
     * @return string
     */
    public function decorateRoot($value, $row, $column, $isExport)
    {
        if (empty($value)) {
            return '<span style="color: #999;">-</span>';
        }
        return $this->escapeHtml($value);
    }
}