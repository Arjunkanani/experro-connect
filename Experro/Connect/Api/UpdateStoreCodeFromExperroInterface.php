<?php
 
namespace Experro\Connect\Api;
 
interface UpdateStoreCodeFromExperroInterface
{
   /**
     * Verifies the connection from Experro based on id
     *
     * @param string $connection_id
     * @param string $store_code
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateStoreCodeFromExperro($connection_id,$store_code);
}