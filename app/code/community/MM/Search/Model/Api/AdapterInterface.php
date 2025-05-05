<?php

declare(strict_types=1);

interface MM_Search_Model_Api_AdapterInterface 
{
    public function __construct(?int $storeId = null);
    /**
     * Create an adapter instance
     */
    public function create(): CmsIg\Seal\Adapter\AdapterInterface;
    
    /**
     * Get engine label
     */
    public static function getLabel(): string;

    /**
     * Get engine type
     */
    public static function getType(): string;
}