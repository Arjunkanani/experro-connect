<?php
namespace Experro\Connect\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Integration\Model\IntegrationFactory;
use Experro\Connect\Logger\Logger;
use Experro\Connect\Model\ResourceModel\Status\CollectionFactory;

class Data extends AbstractHelper
{
    protected $storeManager;
    /**
     * @var IntegrationServiceInterface
     */
    protected $integrationService;
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;
    /**
     * @var IntegrationFactory
     */
    protected $integrationFactory;
    protected $customLogger;
    protected $config;
    protected $connectStatusCollectionFactory;

    public function __construct(
        StoreManagerInterface $storeManager,
        IntegrationServiceInterface $integrationService,
        ResourceConnection $resourceConnection,
        IntegrationFactory $integrationFactory,
        Logger $customLogger,
        CollectionFactory $connectStatusCollectionFactory
    )
    {
        $this->storeManager = $storeManager;
        $this->integrationService = $integrationService;
        $this->resourceConnection = $resourceConnection;
        $this->integrationFactory = $integrationFactory;
        $this->customLogger = $customLogger;
        $this->connectStatusCollectionFactory = $connectStatusCollectionFactory;
        $this->loadConfig();
    }

    protected function loadConfig()
    {
        $filePath = __DIR__ . '/../config/third_party.php';
        if (file_exists($filePath)) {
            $this->config = include $filePath;
        } else {
            $this->config = [];
        }
    }

    public function getMode()
    {
        return $this->config['mode'] ?? 'development'; // Default to 'development'
    }

    public function getApiUrl()
    {
        $mode = $this->getMode();
        return $this->config['urls'][$mode] ?? null;
    }

    public function makeCallCurl($url, $token = null)
    {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Adjust based on your SSL configuration

        if ($token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token
            ]);
        }

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        } else {
            //echo $response;
        }

        curl_close($ch);
        return $response;
    }

    public function makePostCallCurl($url, $token = null)
    { 
        // Initialize a cURL session
        $ch = curl_init();

        // Set the cURL options
        curl_setopt($ch, CURLOPT_URL, $url); // Set the URL
        curl_setopt($ch, CURLOPT_POST, true); // Set the request method to POST
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token, // Add the Authorization header
        ]);

        // Execute the cURL request
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        } else {
            //echo $response;
        }

        // Close the cURL session
        curl_close($ch);
        return $response;
    }

    public function getAuthTokenOfMagentoConnect()
    {
        $name = 'Magento Connector';
        $token = '';
        try {
            // Load the integration by name
            $integration = $this->integrationFactory->create()->load($name, 'name')->getData();

            // Check if integration exists and status is 1 (active)
            if (!empty($integration) && isset($integration['status']) && $integration['status'] == 1) {
                $token = $this->getTokenByIntegrationId($integration['integration_id']);

                if ($token) {
                    //echo "Token: " . $token;
                    return $token;
                } else {
                    echo "Token not found or integration does not exist.";
                }
            }

        } catch (\Exception $e) {
            $this->customLogger->error('Error checking integration: ' . $e->getMessage());
            return false;
        }
    }

    public function getBaseUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl();
    }

    /**
     * Get token by integration ID.
     *
     * @param int $integrationId
     * @return string|null
     */
    public function getTokenByIntegrationId($integrationId)
    {
        try {
            $integration = $this->integrationService->get($integrationId);

            if ($integration && $integration->getConsumerId()) {
                 try {
                    // Get the connection object
                    $connection = $this->resourceConnection->getConnection();

                    // Define the table name with a prefix
                    $tableName = $this->resourceConnection->getTableName('oauth_token');

                    // Prepare the SQL query
                    $select = $connection->select()
                        ->from($tableName, ['token'])
                        ->where('consumer_id = :consumer_id');
                        

                    // Execute the query and fetch the result
                    $token = $connection->fetchOne($select, ['consumer_id' => $integration->getConsumerId()]);

                    // Return the token if found
                    if ($token) {
                        return $token;
                    } else {
                        return 'No active token found for Consumer ID: ' . $consumerId;
                    }
                } catch (\Exception $e) {
                    $this->customLogger->error('Failed to retrieve token: ' . $e->getMessage());
                    return 'Error retrieving token: ' . $e->getMessage();
                }
            }
        } catch (\Exception $e) {
            $this->customLogger->error('Failed to retrieve token: ' . $e->getMessage());
        }

        return null;
    }


    public function experroEndPoint($storeCode,$eventType,$eventAction,$id)
    {
        $experroUrl = $this->getApiUrl();

        // Define the payload
        $data = [
            'store_code' => $storeCode,
            'event_type' => $eventType,
            'event_action' => $eventAction,
            'ids' => $id,
            'url' => $this->getCurrentStoreURL($storeCode)
        ];

        
        // Convert payload to JSON
        $jsonData = json_encode($data);


        // Get headers
        $headers = $this->getHeaderData($storeCode);

        // Check if headers are null (i.e., store code connection does not exist)
        if ($headers === null) {
            $this->customLogger->info(' -------!! Experro Request Skipped !!------- ');
            $this->customLogger->error('Store code connection does not exist for: ' . $storeCode);
            return 'error'; // Early return
        }

        // Log the request payload
        $this->customLogger->info(' -------!! Experro Request Start !!------- ');
        $this->customLogger->info('Experro Request URL: ' . $experroUrl);
        $this->customLogger->info('Experro Request Payload: ' . $jsonData);



        // Log the header values
        $this->customLogger->info('Experro Request Headers: ' . json_encode($headers));

        // Initialize a cURL session
        $ch = curl_init();

        // Set the cURL options
        curl_setopt($ch, CURLOPT_URL, $experroUrl); // Set the URL
        curl_setopt($ch, CURLOPT_POST, true); // Set the request method to POST
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Attach headers
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData); // Attach the JSON payload

        // Execute the cURL request
        $response = curl_exec($ch);

        // Get the HTTP status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Log the response and HTTP status code
        $this->customLogger->info('Response HTTP Status Code: ' . $httpCode);
        $this->customLogger->info('Response: ' . $response);
        //$httpCode = 500;
        // Check for errors
        if (curl_errno($ch)) {
            //echo 'Error: ' . curl_error($ch);die();
            $this->customLogger->error(curl_error($ch));
            $this->customLogger->info(' -------!! Experro Request Ends !!------- ');
            return 'error';
        } else {
                 // Check if the HTTP status code is 200
                if ($httpCode == 200) {

                    // Decode the JSON response
                    $responseData = json_decode($response, true);

                    // Check if the response status is 'success'
                    if (isset($responseData['Status']) && $responseData['Status'] == 'success') {
                        //echo 'Request was successful. Response: ' . $response;die;
                        $this->customLogger->info(' -------!! Experro Request Ends !!------- ');
                        return 'success';
                    } else {
                        //echo 'Request failed. Response: ' . $response;
                        $this->customLogger->error('Experro End Point Request failed. Response: ' . $response);
                        $this->customLogger->info(' -------!! Experro Request Ends !!------- ');
                        return 'error';
                    }
                } else {
                    //echo 'HTTP Error Code: ' . $httpCode . '. Response: ' . $response;
                    $this->customLogger->error('HTTP Error Code: ' . $httpCode . '. Response: ' . $response);
                    $this->customLogger->info(' -------!! Experro Request Ends !!------- ');
                    return 'error';
                }
        }

        $this->customLogger->info(' -------!! Experro Request Ends !!------- ');

        // Close the cURL session
        curl_close($ch);
        return $response;
    }

    public function experroEndPointForInventory($storeCode,$eventType,$eventAction,$items)
    {
        $experroUrl = $this->getApiUrl();

        // Define the payload
        $data = [
            'store_code' => $storeCode,
            'event_type' => $eventType,
            'event_action' => $eventAction,
            'items' => $items,
            'url' => $this->getCurrentStoreURL($storeCode)
        ];

        // Convert payload to JSON
        $jsonData = json_encode($data);

        $headers = $this->getHeaderData($storeCode);

        // Check if headers are null (i.e., store code connection does not exist)
        if ($headers === null) {
            $this->customLogger->info(' -------!! Experro Request Skipped !!------- ');
            $this->customLogger->error('Store code connection does not exist for: ' . $storeCode);
            return 'error'; // Early return
        }

        // Log the request payload
        $this->customLogger->info(' -------!! Experro Request Start !!------- ');
        $this->customLogger->info('Experro Request URL: ' . $experroUrl);
        $this->customLogger->info('Experro Request Payload: ' . $jsonData);

        // Log the header values
        $this->customLogger->info('Experro Request Headers: ' . json_encode($headers));

        // Initialize a cURL session
        $ch = curl_init();

        // Set the cURL options
        curl_setopt($ch, CURLOPT_URL, $experroUrl); // Set the URL
        curl_setopt($ch, CURLOPT_POST, true); // Set the request method to POST
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Attach headers
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData); // Attach the JSON payload

        // Execute the cURL request
        $response = curl_exec($ch);

        // Get the HTTP status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Log the response and HTTP status code
        $this->customLogger->info('Response HTTP Status Code: ' . $httpCode);
        $this->customLogger->info('Response: ' . $response);
        $httpCode = 500;
        // Check for errors
        if (curl_errno($ch)) {
            //echo 'Error: ' . curl_error($ch);
            $this->customLogger->error(curl_error($ch));
            $this->customLogger->info(' -------!! Experro Request Ends !!------- ');
            return 'error';
        } else {
                 // Check if the HTTP status code is 200
                if ($httpCode == 200) {

                    // Decode the JSON response
                    $responseData = json_decode($response, true);

                    // Check if the response status is 'success'
                    if (isset($responseData['Status']) && $responseData['Status'] == 'success') {
                        //echo 'Request was successful. Response: ' . $response;die;
                        $this->customLogger->info(' -------!! Experro Request Ends !!------- ');
                        return 'success';
                    } else {
                        //echo 'Request failed. Response: ' . $response;
                        $this->customLogger->error('Experro End Point Request failed. Response: ' . $response);
                        $this->customLogger->info(' -------!! Experro Request Ends !!------- ');
                        return 'error';
                    }
                } else {
                    //echo 'HTTP Error Code: ' . $httpCode . '. Response: ' . $response;
                    $this->customLogger->error('HTTP Error Code: ' . $httpCode . '. Response: ' . $response);
                    $this->customLogger->info(' -------!! Experro Request Ends !!------- ');
                    return 'error';
                }
        }

        $this->customLogger->info(' -------!! Experro Request Ends !!------- ');

        // Close the cURL session
        curl_close($ch);
        return $response;
    }

    public function getCurrentStoreURL($storeCode)
    {
        $store = $this->storeManager->getStore($storeCode);
        return $store ? $store->getBaseUrl() : null;
    }

    public function getHeaderData($storeCode)
    {
        
        $collection = $this->connectStatusCollectionFactory->create();
        $collection->addFieldToFilter('store_code', $storeCode);
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
            ];

            return $statusData->getId(); // Return the ID if found
        }
        
        // Return null or a default value if not found
        return null;
    }
}
