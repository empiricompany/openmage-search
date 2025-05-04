<?php

declare(strict_types=1);

interface MM_Search_Model_Api_Contract
{    
    public function create(int $storeId): CmsIg\Seal\Adapter\AdapterInterface;
}