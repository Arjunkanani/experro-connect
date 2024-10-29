<?php
 
namespace Experro\Connect\Api;
 
interface VerifyConnectionFromExperroInterface
{
   /**
     * Verifies the connection from Experro based on tenant_id, workspace_id, and environment_id,experro_store_hash, experro_token
     *
     * @param string $tenantId
     * @param string $workspaceId
     * @param string $environmentId
     * @param string $experro_store_hash
     * @param string $experro_token
     * @return \Magento\Framework\DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function verifyConnectionFromExperro($tenantId, $workspaceId, $environmentId,$experro_store_hash,$experro_token);
}