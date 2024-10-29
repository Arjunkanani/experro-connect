<?php

namespace Experro\Connect\Model\Api;

use Experro\Connect\Api\DeleteConnectionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\DataObject;
use Experro\Connect\Model\ResourceModel\Status\CollectionFactory;

class DeleteConnection implements DeleteConnectionInterface
{
    protected $connectStatusCollectionFactory;
    protected $resultJsonFactory;

    public function __construct(
        CollectionFactory $connectStatusCollectionFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->connectStatusCollectionFactory = $connectStatusCollectionFactory;
        $this->resultJsonFactory = $resultJsonFactory;
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
    public function deleteConnection($tenantId, $workspaceId, $environmentId,$experro_store_hash,$experroToken,$storeUrl)
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
        if (!$experroToken) {
            $missingParams[] = 'experroToken';
        }
        if (!$storeUrl) {
            $missingParams[] = 'storeUrl';
        }
        

        // If there are missing parameters, throw an exception with a detailed message
        if (!empty($missingParams)) {
            throw new LocalizedException(
                __('Missing required parameters: %1.', implode(', ', $missingParams))
            );
        }
        // Prepare the response data
        $responseData = [
            'tenant_id' => $tenantId,
            'workspace_id' => $workspaceId,
            'environment_id' => $environmentId,
            'experro_store_hash' => $experro_store_hash,
            'experro_token' => $experroToken,
            'store_url' => $storeUrl
        ];

        // Create a JSON result and set the data
        //$resultJson = $this->resultJsonFactory->create();
        //return $resultJson->setData($responseData);

         $response = ['success' => false];
        try {
            // Your Code here
            
            $response = [ 'message' => $responseData];
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
            //$this->logger->info($e->getMessage());
        }
        //$returnArray = json_encode($response, JSON_UNESCAPED_SLASHES);
        return $response; 



        

    }


    public function getStatusDetails($tenantId,$workspaceId,$environmentId)
    {
        $collection = $this->connectStatusCollectionFactory->create();
    
        // Apply filter to fetch the record where 'id' matches the $rowId
        $collection->addFieldToFilter('tenant_id', $tenantId);
        $collection->addFieldToFilter('workspace_id', $workspaceId);
        $collection->addFieldToFilter('environment_id', $environmentId);
        
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
