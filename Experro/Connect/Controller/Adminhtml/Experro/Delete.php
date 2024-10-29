<?php

namespace Experro\Connect\Controller\Adminhtml\Experro;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Experro\Connect\Model\ResourceModel\Status\CollectionFactory;
use Experro\Connect\Model\StatusFactory;
use Magento\Store\Model\StoreManagerInterface;
use Experro\Connect\Helper\Data;
use Experro\Connect\Logger\Logger;

class Delete extends Action
{
    protected $resultJsonFactory;
    protected $connectStatusCollectionFactory;
    protected $statusFactory;
    protected $storeManager;
    protected $helperData;
    protected $customLogger;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CollectionFactory $connectStatusCollectionFactory,
        StatusFactory $statusFactory,
        StoreManagerInterface $storeManager,
        Data $helperData,
        Logger $customLogger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->connectStatusCollectionFactory = $connectStatusCollectionFactory;
        $this->statusFactory = $statusFactory;
        $this->storeManager = $storeManager;
        $this->helperData = $helperData;
        $this->customLogger = $customLogger;
        parent::__construct($context);
    }

    public function execute()
    { 
        $result = $this->resultJsonFactory->create();
        $request = $this->getRequest();

        // Get the token from the AJAX request
        $rowId = $request->getParam('rowId');
        
        $statusDetails = $this->getStatusDetails($rowId);

        $apiUrl = $this->helperData->getApiUrl();
        // Make the cURL request
        $response = $this->sendCurlRequest($apiUrl, $statusDetails,$rowId);

        // Delete the record associated with $rowId
        $this->deleteRecordById($rowId);

        // Return response from the cURL request
        return $result->setData([
            'success' => true,
            'message' => json_decode($response, true) // Assuming the response is JSON
        ]);
        
       
    }

    public function getStatusDetails($rowId)
    {
        $collection = $this->connectStatusCollectionFactory->create();
    
        // Apply filter to fetch the record where 'id' matches the $rowId
        $collection->addFieldToFilter('id', $rowId);
        
        // Fetch the data of the first matching record
        $statusData = $collection->getFirstItem();
        
        // Return only the specific fields you need
        return [
            'tenant_id' => $statusData->getData('tenant_id'),
            'workspace_id' => $statusData->getData('workspace_id'),
            'environment_id' => $statusData->getData('environment_id'),
            'experro_store_hash' => $statusData->getData('experro_store_hash'),
            'experroToken' => $statusData->getData('experroToken'),
            'storeUrl' => $this->getCurrentStoreURL($statusData->getData('store_code')),
        ];
    }

    public function getCurrentStoreURL($storeCode)
    {
        $store = $this->storeManager->getStore($storeCode);
        return $store ? $store->getBaseUrl() : null;
    }


    protected function sendCurlRequest($url, $data, $rowId)
    {

        $headers = $this->getHeaderData($rowId);

        // Check if headers are null (i.e., store code connection does not exist)
        if ($headers === null) {
            $this->customLogger->info(' -------!! Experro Request Skipped !!------- ');
            $this->customLogger->error('Store code connection does not exist for: ' . $storeCode);
            return 'error'; // Early return
        }

        // Log the request payload
        $this->customLogger->info(' -------!! Delete Connection Start !!------- ');
        $this->customLogger->info(' -------!! Experro Request Start !!------- ');
        $this->customLogger->info('Experro Request URL: ' . $url);
        $this->customLogger->info('Experro Request Payload: ' . json_encode($data));

        // Log the header values
        $this->customLogger->info('Experro Request Headers: ' . json_encode($headers));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Attach headers

        // Execute the cURL request
        $response = curl_exec($ch);

        // Get the HTTP status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Log the response and HTTP status code
        $this->customLogger->info('Response HTTP Status Code: ' . $httpCode);
        $this->customLogger->info('Response: ' . $response);

        // Check for cURL errors
        if (curl_errno($ch)) {
            // Handle the error as needed, you can log it or throw an exception
            return json_encode(['success' => false, 'message' => 'cURL error: ' . curl_error($ch)]);
        }

        $this->customLogger->info(' -------!! Experro Request Ends !!------- ');
        $this->customLogger->info(' -------!! Delete Connection Ends !!------- ');

        // Close cURL session
        curl_close($ch);

        // Return the response from the cURL request
        return $response;
    }

    protected function deleteRecordById($rowId)
    {
        // Load the model using the ID
        $model = $this->statusFactory->create(); // Assuming you have a factory for your model
        $model->load($rowId); // Load the record by ID

        // Check if the record exists
        if ($model->getId()) {
            try {
                $model->delete(); // Delete the record
            } catch (\Exception $e) {
                // Handle any exception that might occur during deletion
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Could not delete the record: %1', $e->getMessage())
                );
            }
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('Record not found.'));
        }
    }

    public function getHeaderData($rowId)
    {
        
        $collection = $this->connectStatusCollectionFactory->create();
        $collection->addFieldToFilter('id', $rowId);
        // Define the additional header values

        // Fetch the data of the first matching record
        $statusData = $collection->getFirstItem();
        
        // Check if the item exists and return its ID
        if ($statusData->getId()) {

            $tenantId = $statusData->getTenantId();
            $workspaceId = $statusData->getWorkspaceId();
            $exp_store_hash = $statusData->getExperroStoreHash();
            $experro_token = $statusData->getExperroToken();

            // Create the headers array
            return [
                'Content-Type: application/json', // Set Content-Type header
                'tenant_id: ' . $tenantId, // Add tenant_id header
                'workspace_id: ' . $workspaceId, // Add workspace_id header
                'exp_store_hash: ' . $exp_store_hash, // Add exp_store_hash header
                'experro_token: ' . $experro_token, // Add experro_token header
            ]; // Return the ID if found
        }
        
        // Return null or a default value if not found
        return null;
    }


}
