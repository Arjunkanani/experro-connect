<?php
 
namespace Experro\Connect\Api;
 
interface DeleteConnectionInterface
{
   /**
     * Verifies the connection from Experro based on tenant_id, workspace_id, and environment_id.
     *
     * @param string $tenantId
     * @param string $workspaceId
     * @param string $environmentId
     * @param string $experro_store_hash
     * @param string $experroToken
     * @param string $storeUrl
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteConnection($tenantId, $workspaceId, $environmentId,$experro_store_hash,$experroToken,$storeUrl);
}