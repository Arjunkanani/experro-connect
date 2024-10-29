<?php

namespace Experro\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Experro\Connect\Helper\Data as CustomHelper;
use Experro\Connect\Logger\Logger;
use Experro\Connect\Model\AttemptFactory;
use Experro\Connect\Model\ResourceModel\Attempt\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Store;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Bundle\Model\Product\Type as BundleType;

class ProductSaveAfter implements ObserverInterface
{
    /**
     * @var CustomHelper
     */
    protected $customHelper;
    /**
     * @var Logger
     */
    protected $customLogger;
    /**
     * @var AttemptFactory
     */
    protected $attemptFactory;
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;
    protected $storeManager;
    protected $stockItemRepository;
    protected $productRepository;
    protected $stockRegistry;
    protected $getSalableQuantityDataBySku;
    protected $configurable;
    /**
     * @var Type
     */
    private $bundleType;
    protected $grouped;
    protected $sourceItemRepository;
    protected $searchCriteriaBuilder;
    protected $getSourceItemsBySku;
    protected $sourceRepository;
    

    public function __construct(
        CustomHelper $customHelper,
        Logger $customLogger,
        AttemptFactory $attemptFactory,
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager,
        StockItemRepositoryInterface $stockItemRepository,
        ProductRepositoryInterface $productRepository,
        StockRegistryInterface $stockRegistry,
        GetSalableQuantityDataBySku $getSalableQuantityDataBySku,
        Configurable $configurable,
        BundleType $bundleType,
        Grouped $grouped

    ) {
        $this->customHelper = $customHelper;
        $this->customLogger = $customLogger;
        $this->attemptFactory = $attemptFactory;
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
        $this->stockItemRepository = $stockItemRepository;
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
        $this->getSalableQuantityDataBySku = $getSalableQuantityDataBySku;
        $this->configurable = $configurable;
        $this->bundleType = $bundleType;
        $this->grouped = $grouped;
    }

    public function execute(Observer $observer)
    {   
        $product = $observer->getEvent()->getProduct();
        $productId = $product->getId();

        
        // Load the original product data from the repository
        $originalData = $product->getOrigData();
        $newData = $product->getData();

        // The below array is created because the associated products of config is returning 1 in $newData['quantity_and_stock_status']
        $stockItem = $this->stockRegistry->getStockItem($productId);
        $newIsInStock = $stockItem->getIsInStock();
        $newQty = $stockItem->getQty();
        

        if ($newData['quantity_and_stock_status'] == 1) {
            $newData['quantity_and_stock_status'] = [
                'is_in_stock' => $newIsInStock,
                'qty' => $newQty
            ];
        }


        // First time when product is created at that time original data is not present
        if (isset($originalData['quantity_and_stock_status']) && !empty($originalData['quantity_and_stock_status'])) {
                    
                    // Extract the quantity_and_stock_status arrays
                    $originalQuantity = isset($originalData['quantity_and_stock_status']) ? $originalData['quantity_and_stock_status'] : array();
                    $newQuantity = isset($newData['quantity_and_stock_status']) ? $newData['quantity_and_stock_status'] : array();


                    // Check if 'qty' is present
                    if (isset($originalQuantity['qty']) && !empty($originalQuantity['qty']) && 
                        isset($newQuantity['qty']) && !empty($newQuantity['qty'])) {
                        // Compare the arrays
                        if ($originalQuantity['qty'] != $newQuantity['qty']) {
                            // If they are the same, do nothing
                            $this->sendInventoryData($productId,$product->getTypeId(),$product->getSku(),$newQuantity['qty'],$product->getStoreId(),$newQuantity['is_in_stock']);
                            
                        }
                    } 

                    // Check if 'is_in_stock' is present
                    if (isset($originalQuantity['is_in_stock']) && isset($newQuantity['is_in_stock'])) {
                        // Compare the arrays
                        if ($originalQuantity['is_in_stock'] != $newQuantity['is_in_stock']) {
                            // If they are the same, do nothing
                            
                            $newQuantity = isset($newData['quantity_and_stock_status']) ? $newData['quantity_and_stock_status'] : array();
                            $originalQuantity = isset($originalData['quantity_and_stock_status']) ? $originalData['quantity_and_stock_status'] : array();

                            if (isset($newQuantity['qty']) && !empty($newQuantity['qty'])) {
                                // The 'qty' key exists and is not empty
                                $quantity = $newQuantity['qty'];
                                
                            } else {
                                // The 'qty' key is either not set or is empty
                                $quantity = $originalQuantity['qty'];
                            }

                            $this->sendInventoryData($productId,$product->getTypeId(),$product->getSku(),$quantity,$product->getStoreId(),$newQuantity['is_in_stock']);
                        } 
                    }   
        } else {
            // The value is either not set or empty
            $newQuantity = isset($newData['quantity_and_stock_status']) ? $newData['quantity_and_stock_status'] : array();
            $this->sendInventoryData($productId,$product->getTypeId(),$product->getSku(),$newQuantity['qty'],$product->getStoreId(),$newQuantity['is_in_stock']);
        }

        $product = $observer->getProduct();  // you will get product object
        $id = $product->getId();

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
            
            $eventType = 'Product';
            $eventAction = 'Updated';
            $experroEndPointResponse = $this->customHelper->experroEndPoint($storeCode,$eventType,$eventAction,$id);

            $experroApiResponse = 200;
            if ($experroEndPointResponse == 'success') {
                $experroApiResponse = 200;
            }else{
                $experroApiResponse = 500;
            }
            // Log a custom message indicating successful execution
            $this->customLogger->info('Product '.$id.' got updated.');

            // Before entering attempt need to check if the product data is already present in table If yes then only update attempt to 0
            // Start code to send data to attempt 
            
            if ($experroApiResponse == 500) {
                try {

                    // Create an instance of the model
                    $attempt = $this->attemptFactory->create();

                    // Load the existing attempt by related_id and type
                    $attemptCollection = $this->collectionFactory->create();
                    $existingAttempt = $attemptCollection
                        ->addFieldToFilter('related_id', $id)
                        ->addFieldToFilter('type', 'product')
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
                            'type' => 'product',
                            'attempt_no' => 0,
                            'store_code' => $storeCode
                        ]);
                        $attempt->save();
                    }


                    // Save the data
                    $attempt->save();

                    // Success message
                    $this->customLogger->info('Data has been successfully inserted in attempt table for product not sent to experro.');
                } catch (\Exception $e) {
                    // Error message
                    $this->customLogger->info('Unable to insert data in attempt table: ' . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            // Log the exception message and trace to help with debugging
            $this->customLogger->error('An error occurred: ' . $e->getMessage());
            $this->customLogger->error($e->getTraceAsString());
        }
    }

    public function sendInventoryData($productId,$type,$productSku,$qtyNew,$storeId,$stockStatus)
    {                     
            $items = [];


            // Determine the item data based on product type
            if (in_array($type, ['configurable', 'grouped', 'bundle'])) {

                    // Prepare item data with saleble quantity and default quantity
                    $itemData = [
                        'product_id' => $productId,
                        'type' => $type,
                        'sku' => $productSku,
                        'is_in_stock' => $this->getParentStockStatus($productSku)
                    ];
            } else {
                    
                    $parentIds = [];
                    $configurableParentIds = $this->configurable->getParentIdsByChild($productId);
                    if (!empty($configurableParentIds)) {
                        $parentIds = array_merge($parentIds, $configurableParentIds);
                    }

                    // Check for grouped parent IDs
                    $groupedParentIds = $this->grouped->getParentIdsByChild($productId);
                    if (!empty($groupedParentIds)) {
                        $parentIds = array_merge($parentIds, $groupedParentIds);
                    }

                    // Check for bundle parent IDs
                    $bundleParentIds = $this->bundleType->getParentIdsByChild($productId);
                    if (!empty($bundleParentIds)) {
                        $parentIds = array_merge($parentIds, $bundleParentIds);
                    }

                    $parentDetails = [];
                    foreach ($parentIds as $parentId) {
                        $parentItem = $this->productRepository->getById($parentId); // Or use other methods to load the parent item details
                        $parentProductType = $parentItem->getTypeId();
                        $parentProductSku = $parentItem->getSku();
                        $parentstockstatus = $this->getParentStockStatus($parentItem->getSku());
                        
                        // Set parent details
                        $parentDetails[] = [
                            'product_id' => $parentId,
                            'type' => $parentProductType,
                            'sku' => $parentProductSku,
                            'is_in_stock' => $parentstockstatus
                        ];
                    }

                    // Fetch salable quantity for the product
                    $salebleQuantity = $this->getProductSalableQty($productSku);

                    // Prepare item data without saleble quantity and default quantity for other types
                    $itemData = [
                        'product_id' => $productId,
                        'type' => $type,
                        'saleble_quantity' => $salebleQuantity,
                        'default_quantity' => $qtyNew,
                        'is_in_stock' => $stockStatus,
                        'sku' => $productSku
                    ];

                    if (!empty($parentDetails)) {
                        $itemData['parent_details'] = $parentDetails;
                    }
            }

            // Add item data to items array
            $items[] = $itemData;

            try {

                // Fallback to default store view if necessary
                $store = $this->storeManager->getStore($storeId) ?: $this->storeManager->getStore(Store::DEFAULT_STORE_ID);
                $storeCode = $store->getCode();

                // Here when we save product in admin then for ALL store views we receive admin as store code, in this case we will pass default store code

                if ($storeCode == 'admin') {
                    $storeCode = $this->storeManager->getDefaultStoreView()->getCode();
                }
                
                $eventType = 'Product';
                $eventAction = 'InventoryUpdate';
                $experroEndPointResponse = $this->customHelper->experroEndPointForInventory($storeCode,$eventType,$eventAction,$items);

                $experroApiResponse = 200;
                if ($experroEndPointResponse == 'success') {
                    $experroApiResponse = 200;
                }else{
                    $experroApiResponse = 500;
                }
                // Log a custom message indicating successful execution
                $this->customLogger->info('Product '.$productId.' got updated.');

                // Before entering attempt need to check if the product data is already present in table If yes then only update attempt to 0
                // Start code to send data to attempt 
                
                if ($experroApiResponse == 500) {
                    try {

                        // Create an instance of the model
                        $attempt = $this->attemptFactory->create();

                        // Load the existing attempt by related_id and type
                        $attemptCollection = $this->collectionFactory->create();
                        $existingAttempt = $attemptCollection
                            ->addFieldToFilter('related_id', $productId)
                            ->addFieldToFilter('type', 'product')
                            ->addFieldToFilter('store_code', $storeCode)
                            ->getFirstItem(); // Assuming there will be only one record with this combination

                        if ($existingAttempt->getId()) {
                            // Record exists, update it
                            $existingAttempt->setAttemptNo(0); // Increment the attempt number
                            $existingAttempt->save();
                        } else {

                            // Record does not exist, create a new one
                            $attempt->setData([
                                'related_id' => $productId,
                                'type' => 'product',
                                'attempt_no' => 0,
                                'store_code' => $storeCode
                            ]);
                            $attempt->save();
                        }


                        // Save the data
                        $attempt->save();

                        // Success message
                        $this->customLogger->info('Data has been successfully inserted in attempt table for product not sent to experro.');
                    } catch (\Exception $e) {
                        // Error message
                        $this->customLogger->info('Unable to insert data in attempt table: ' . $e->getMessage());
                    }
                }

            } catch (\Exception $e) {
                // Log the exception message and trace to help with debugging
                $this->customLogger->error('An error occurred: ' . $e->getMessage());
                $this->customLogger->error($e->getTraceAsString());
            }        
    }

    public function getProductSalableQty($productSku)
    {
        $salable = $this->getSalableQuantityDataBySku->execute($productSku);
        return $salable[0]['qty'];
    }

    public function getParentStockStatus($sku)
    {
        $stockItem = $this->stockRegistry->getStockItemBySku($sku);

        if ($stockItem->getIsInStock()) {
            return $stockItem->getIsInStock();
        }else{
            return false;
        }
        // Return the stock status
        
    }
    
}