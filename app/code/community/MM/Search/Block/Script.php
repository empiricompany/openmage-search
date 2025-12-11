<?php
class MM_Search_Block_Script extends Mage_Core_Block_Template
{
    /**
     * Cache lifetime in seconds (1 day)
     * Note: Cache must be cleared manually when JS files change
     */
    const CACHE_LIFETIME = 86400;
    
    /**
     * Get cache key info for block caching
     *
     * @return array
     */
    public function getCacheKeyInfo()
    {
        return array(
            'MM_SEARCH_SCRIPT',
            Mage::app()->getStore()->getId(),
            Mage::getDesign()->getPackageName(),
            Mage::getDesign()->getTheme('skin'),
            $this->getFile(),
            $this->getScriptType(),
        );
    }
    
    /**
     * Get cache lifetime
     *
     * @return int
     */
    public function getCacheLifetime()
    {
        return self::CACHE_LIFETIME;
    }
    
    /**
     * Get cache tags
     *
     * @return array
     */
    public function getCacheTags()
    {
        return array(
            Mage_Core_Block_Abstract::CACHE_GROUP,
            'MM_SEARCH',
        );
    }

    /**
     * Get the full URL to the script file
     *
     * @return string
     */
    public function getScriptUrl()
    {
        return Mage::getDesign()->getSkinUrl($this->getFile());
    }

    /**
     * Get the script version based on file modification time
     *
     * This provides automatic cache busting when the file is modified.
     * Falls back to the Vite manifest hash if available, or current timestamp if file not found.
     *
     * @return string
     */
    public function getScriptVersion()
    {
        $file = $this->getFile();
        
        // Try to get the actual file path through the theme fallback
        $filePath = Mage::getDesign()->getFilename($file, array('_type' => 'skin'));
        
        if ($filePath && file_exists($filePath)) {
            return filemtime($filePath);
        }
        
        // Fallback: try to get version from Vite manifest if this is a bundled file
        $helper = Mage::helper('mm_search');
        $manifestVersion = $this->_getManifestVersion();
        if ($manifestVersion) {
            return $manifestVersion;
        }
        
        // Last resort fallback
        return time();
    }
    
    /**
     * Get version hash from Vite manifest
     *
     * @return string|null
     */
    protected function _getManifestVersion()
    {
        $manifestPath = Mage::getBaseDir('skin') . DS . 'frontend' . DS . 'base' . DS . 'default'
            . DS . 'js' . DS . 'mm_search' . DS . 'dist' . DS . '.vite' . DS . 'manifest.json';
        
        if (!file_exists($manifestPath)) {
            return null;
        }
        
        // Use manifest file modification time as version
        return filemtime($manifestPath);
    }

    /**
     * Get the full script URL with version parameter for cache busting
     *
     * @return string
     */
    public function getScriptUrlWithVersion()
    {
        return $this->getScriptUrl() . '?v=' . $this->getScriptVersion();
    }
}