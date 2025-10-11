<?php
class MM_Search_Block_Script extends Mage_Core_Block_Template
{
    public function getScriptUrl()
    {
        return Mage::getDesign()->getSkinUrl($this->getFile());
    }
}