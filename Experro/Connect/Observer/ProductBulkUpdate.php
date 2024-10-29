<?php

namespace Experro\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Experro\Connect\Helper\Data as CustomHelper;
use Psr\Log\LoggerInterface;
use Experro\Connect\Logger\Logger;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Experro\Connect\Model\AttemptFactory;
use Experro\Connect\Model\ResourceModel\Attempt\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;


class ProductBulkUpdate implements ObserverInterface
{
    /**
     * @var CustomHelper
     */
    protected $customHelper;
    /**
     * @var Logger
     */
    protected $customLogger;
    protected $productRepository;
    /**
     * @var AttemptFactory
     */
    protected $attemptFactory;
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;
    protected $storeManager;
    public function __construct(
        CustomHelper $customHelper,
        Logger $customLogger,
        ProductRepositoryInterface $productRepository,
        AttemptFactory $attemptFactory,
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->customHelper = $customHelper;
        $this->customLogger = $customLogger;
        $this->productRepository = $productRepository;
        $this->attemptFactory = $attemptFactory;
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
    }

    public function execute(Observer $observer)
    {

        // Here conditions that are checked are
        // If Qty exists in CSV or not + if it has value or not
        // If Store view code exists or not & if yes move data to specific store
        // If is_in_stock exists or not & if is_in_stock & Qty both exists

        $products = $observer->getEvent()->getBunch();

        // Check if 'store_view_code' exists in the products
        $hasStoreViewCode = !empty($products) && isset($products[0]['store_view_code']);

        // If 'store_view_code' does not exist, handle the situation
        if (!$hasStoreViewCode) {

            // Handle the case where 'store_view_code' is missing
            // Only default store data will be changed
            // First, create a new array to hold the updated data

            // Retrieve store ID associated with the product

            $skus = array_column($products, 'sku');
            $storeViewCodes = array_column($products, 'store_view_code');

            // Combine them into an associative array
            $combined = array_map(function($sku, $storeViewCode) {
                return ['sku' => $sku, 'store_view_code' => 'default'];
            }, $skus, $storeViewCodes);

            $updatedCombined = [];

            // Iterate over each entry in the combined array
            foreach ($combined as $entry) {
                $sku = $entry['sku'];

                // Fetch the product using the SKU
                $product = $this->productRepository->get($sku);

                // Retrieve the product ID
                $productId = $product->getId();

                $storeId = $product->getStoreId();

                // Fallback to default store view if necessary
                $store = $this->storeManager->getStore($storeId) ?: $this->storeManager->getStore(Store::DEFAULT_STORE_ID);
                $storeCode = $store->getCode();

                // Add the updated entry with product ID to the new array
                $updatedCombined[] = [
                    'id' => $productId,
                    'store_view_code' => $storeCode
                ];
            }

            $this->postProductAPI($updatedCombined,$product);
            $this->postInventoryAPI($updatedCombined,$product);
            $this->customLogger->info('Default Store view data changed only as not store_view_code was provided in CSV.');
        } else {
            // Extract SKUs and store view codes
            $skus = array_column($products, 'sku');
            $storeViewCodes = array_column($products, 'store_view_code');

            // Combine them into an associative array
            $combined = array_map(function($sku, $storeViewCode) {
                return ['sku' => $sku, 'store_view_code' => $storeViewCode];
            }, $skus, $storeViewCodes);

            // First, create a new array to hold the updated data
            $updatedCombined = [];

            // Iterate over each entry in the combined array
            foreach ($combined as $entry) {
                $sku = $entry['sku'];

                // Fetch the product using the SKU
                $product = $this->productRepository->get($sku);

                // Retrieve the product ID
                $productId = $product->getId();

                // Add the updated entry with product ID to the new array
                $updatedCombined[] = [
                    'id' => $productId,
                    'store_view_code' => $entry['store_view_code']
                ];
            }

            $this->postProductAPI($updatedCombined,$product);
            $this->postInventoryAPI($updatedCombined,$product);
        }        

    }


    public function postProductAPI($updatedCombined,$product)
    {
        try {

                // Retrieve store ID associated with the product
                $storeId = $product->getStoreId();

                // Fallback to default store view if necessary
                $store = $this->storeManager->getStore($storeId) ?: $this->storeManager->getStore(Store::DEFAULT_STORE_ID);
                $storeCode = $store->getCode();

                // Here when we save product in admin then for ALL store views we receive admin as store code, in this case we will pass default store code

                if ($storeCode == 'admin') {
                    $storeCode = $this->storeManager->getDefaultStoreView()->getCode();
                }

                // Initialize an array to group IDs by store_view_code
                $groupedByStoreViewCode = [];

                // Iterate over each entry in the updatedCombined array
                foreach ($updatedCombined as $entry) {
                    $storeViewCode = $entry['store_view_code'];
                    $productId = $entry['id'];
                    
                    // Initialize the store view code group if not already set
                    if (!isset($groupedByStoreViewCode[$storeViewCode])) {
                        $groupedByStoreViewCode[$storeViewCode] = [];
                    }
                    
                    // Add the product ID to the appropriate store view code group
                    $groupedByStoreViewCode[$storeViewCode][] = $productId;
                }

                // Initialize an array to hold the final results
                $finalResults = [];

                // Convert each group of IDs into a comma-separated string
                foreach ($groupedByStoreViewCode as $storeViewCode => $ids) {
                    $commaSeparatedIds = implode(',', $ids);
                    $finalResults[$storeViewCode] = $commaSeparatedIds;
                }

                //echo "<pre>";print_r($finalResults);die();
                
                $eventType = 'Product';
                $eventAction = 'Updated';

                foreach ($finalResults as $storeCode => $Ids) {
                    $experroEndPointResponse = $this->customHelper->experroEndPoint($storeCode,$eventType,$eventAction,$Ids);
                    $experroApiResponse = 200;
                    if ($experroEndPointResponse == 'success') {
                        $experroApiResponse = 200;
                    }else{
                        $experroApiResponse = 500;
                    }
                    // Log a custom message indicating successful execution
                    $this->customLogger->info('Product '.$Ids.' got updated.');

                    // Before entering attempt need to check if the product data is already present in table If yes then only update attempt to 0
                    // Start code to send data to attempt 

                    foreach (explode(',', $Ids) as $id) {

                        if ($experroApiResponse == 500) {
                            try {

                                // Create an instance of the model
                                $attempt = $this->attemptFactory->create();

                                // Load the existing attempt by related_id and type
                                $attemptCollection = $this->collectionFactory->create();
                                $existingAttempt = $attemptCollection
                                    ->addFieldToFilter('related_id', $id)
                                    ->addFieldToFilter('type', 'bulkproduct')
                                    ->addFieldToFilter('store_code', $storeCode)
                                    ->getFirstItem(); // Assuming there will be only one record with this combination

                                if ($existingAttempt->getId()) {
                                    // Record exists, update it
                                    $existingAttempt->setAttemptNo(0); // Increment the attempt number
                                    $existingAttempt->save();
                                } else {

                                    // Record does not exist, create a new one
                                    $attempt->setData([
                                        'related_id' => $id,
                                        'type' => 'bulkproduct',
                                        'attempt_no' => 0,
                                        'store_code' => $storeCode
                                    ]);
                                    $attempt->save();
                                }

                                // Save the data
                                $attempt->save();

                                // Success message
                                $this->customLogger->info('Data has been successfully inserted in attempt table for bulkproduct not sent to experro.');
                            } catch (\Exception $e) {
                                // Error message
                                $this->customLogger->info('Unable to insert data in attempt table: ' . $e->getMessage());
                            }
                        }
                        
                    }
                }

            } catch (\Exception $e) {
                // Log the exception message and trace to help with debugging
                $this->customLogger->error('An error occurred: ' . $e->getMessage());
                $this->customLogger->error($e->getTraceAsString());
            }
    }

    public function postInventoryAPI($updatedCombined,$product)
    {
        try {

                // Retrieve store ID associated with the product
                $storeId = $product->getStoreId();

                // Fallback to default store view if necessary
                $store = $this->storeManager->getStore($storeId) ?: $this->storeManager->getStore(Store::DEFAULT_STORE_ID);
                $storeCode = $store->getCode();

                // Here when we save product in admin then for ALL store views we receive admin as store code, in this case we will pass default store code

                if ($storeCode == 'admin') {
                    $storeCode = $this->storeManager->getDefaultStoreView()->getCode();
                }

                // Initialize an array to group IDs by store_view_code
                $groupedByStoreViewCode = [];

                // Iterate over each entry in the updatedCombined array
                foreach ($updatedCombined as $entry) {
                    $storeViewCode = $entry['store_view_code'];
                    $productId = $entry['id'];
                    
                    // Initialize the store view code group if not already set
                    if (!isset($groupedByStoreViewCode[$storeViewCode])) {
                        $groupedByStoreViewCode[$storeViewCode] = [];
                    }
                    
                    // Add the product ID to the appropriate store view code group
                    $groupedByStoreViewCode[$storeViewCode][] = $productId;
                }

                // Initialize an array to hold the final results
                $finalResults = [];

                // Convert each group of IDs into a comma-separated string
                foreach ($groupedByStoreViewCode as $storeViewCode => $ids) {
                    $commaSeparatedIds = implode(',', $ids);
                    $finalResults[$storeViewCode] = $commaSeparatedIds;
                }

                //echo "<pre>";print_r($finalResults);die();
                
                $eventType = 'Product';
                $eventAction = 'InventoryUpdate';

                foreach ($finalResults as $storeCode => $Ids) {
                    $experroEndPointResponse = $this->customHelper->experroEndPoint($storeCode,$eventType,$eventAction,$Ids);
                    $experroApiResponse = 200;
                    if ($experroEndPointResponse == 'success') {
                        $experroApiResponse = 200;
                    }else{
                        $experroApiResponse = 500;
                    }
                    // Log a custom message indicating successful execution
                    $this->customLogger->info('Product '.$Ids.' got updated.');

                    // Before entering attempt need to check if the product data is already present in table If yes then only update attempt to 0
                    // Start code to send data to attempt 

                    foreach (explode(',', $Ids) as $id) {

                        if ($experroApiResponse == 500) {
                            try {

                                // Create an instance of the model
                                $attempt = $this->attemptFactory->create();

                                // Load the existing attempt by related_id and type
                                $attemptCollection = $this->collectionFactory->create();
                                $existingAttempt = $attemptCollection
                                    ->addFieldToFilter('related_id', $id)
                                    ->addFieldToFilter('type', 'bulkproduct')
                                    ->addFieldToFilter('store_code', $storeCode)
                                    ->getFirstItem(); // Assuming there will be only one record with this combination

                                if ($existingAttempt->getId()) {
                                    // Record exists, update it
                                    $existingAttempt->setAttemptNo(0); // Increment the attempt number
                                    $existingAttempt->save();
                                } else {

                                    // Record does not exist, create a new one
                                    $attempt->setData([
                                        'related_id' => $id,
                                        'type' => 'bulkproduct',
                                        'attempt_no' => 0,
                                        'store_code' => $storeCode
                                    ]);
                                    $attempt->save();
                                }

                                // Save the data
                                $attempt->save();

                                // Success message
                                $this->customLogger->info('Data has been successfully inserted in attempt table for bulkproduct not sent to experro.');
                            } catch (\Exception $e) {
                                // Error message
                                $this->customLogger->info('Unable to insert data in attempt table: ' . $e->getMessage());
                            }
                        }
                        
                    }
                }

            } catch (\Exception $e) {
                // Log the exception message and trace to help with debugging
                $this->customLogger->error('An error occurred: ' . $e->getMessage());
                $this->customLogger->error($e->getTraceAsString());
            }
    }
}