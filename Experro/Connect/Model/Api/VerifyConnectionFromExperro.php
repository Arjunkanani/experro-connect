<?php

namespace Experro\Connect\Model\Api;

use Experro\Connect\Api\VerifyConnectionFromExperroInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\DataObject;
use Experro\Connect\Model\ResourceModel\Status\CollectionFactory;

class VerifyConnectionFromExperro implements VerifyConnectionFromExperroInterface
{
    protected $connectStatusCollectionFactory;

    public function __construct(
        CollectionFactory $connectStatusCollectionFactory
    ) {
        $this->connectStatusCollectionFactory = $connectStatusCollectionFactory;
    }

    /**
     * Verifies the connection based on tenant_id, workspace_id, and environment_id.
     *
     * @param string $tenantId
     * @param string $workspaceId
     * @param string $environmentId
     * @return \Magento\Framework\DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function verifyConnectionFromExperro($tenantId, $workspaceId, $environmentId,$experro_store_hash,$experro_token)
    {   
        // Initialize an array to collect missing parameters
        $missingParams = [];

        // Check which parameters are missing
        if (!$tenantId) {
            $missingParams[] = 'tenant_id';
        }
        if (!$workspaceId) {
            $missingParams[] = 'workspace_id';
        }
        if (!$environmentId) {
            $missingParams[] = 'environment_id';
        }

        if (!$experro_store_hash) {
            $missingParams[] = 'experro_store_hash';
        }

        if (!$experro_token) {
            $missingParams[] = 'experro_token';
        }

        // If there are missing parameters, throw an exception with a detailed message
        if (!empty($missingParams)) {
            throw new LocalizedException(
                __('Missing required parameters: %1.', implode(', ', $missingParams))
            );
        }
        // Assume you're verifying with some external service or database

        $id = $this->getStatusDetails($tenantId,$workspaceId,$environmentId,$experro_store_hash,$experro_token);

        if (!$id) {
            throw new LocalizedException(__('No matching entry found.'));
        }

        return $id;
    }


    public function getStatusDetails($tenantId,$workspaceId,$environmentId,$experro_store_hash,$experro_token)
    {
        $collection = $this->connectStatusCollectionFactory->create();
    
        // Apply filter to fetch the record where 'id' matches the $rowId
        $collection->addFieldToFilter('tenant_id', $tenantId);
        $collection->addFieldToFilter('workspace_id', $workspaceId);
        $collection->addFieldToFilter('environment_id', $environmentId);
        $collection->addFieldToFilter('experro_store_hash', $experro_store_hash);
        $collection->addFieldToFilter('experro_token', $experro_token);
        
        // Fetch the data of the first matching record
        $statusData = $collection->getFirstItem();
        
        // Check if the item exists and return its ID
        if ($statusData->getId()) {
            return $statusData->getId(); // Return the ID if found
        }
        
        // Return null or a default value if not found
        return null;
    }
}
