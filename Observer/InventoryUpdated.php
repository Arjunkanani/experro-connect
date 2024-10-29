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
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;
use Magento\CatalogInventory\Api\StockRegistryInterface as StockItem;
use Magento\Catalog\Model\Product as Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Sales\Model\OrderFactory;
use Magento\GroupedProduct\Model\ResourceModel\Product\Link as ProductLink;


class InventoryUpdated implements ObserverInterface
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
    protected $getSalableQuantityDataBySku;
    protected $stockItem;
    protected $product;
    private $getProductTypeBySku;
    /**
     * @var ProductFactory
     */
    private $productFactory;
    /**
     * @var OrderFactory
     */
    private $orderFactory;
    /**
     * @var ProductLink
     */
    private $productLink;

    public function __construct(
        CustomHelper $customHelper,
        Logger $customLogger,
        AttemptFactory $attemptFactory,
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager,
        GetSalableQuantityDataBySku $getSalableQuantityDataBySku,
        StockItem $stockItem,
        Product $product,
        \Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface $getProductTypeBySku,
        ProductRepository $productFactory,
        OrderFactory $orderFactory,
        ProductLink $productLink
    ) {
        $this->customHelper = $customHelper;
        $this->customLogger = $customLogger;
        $this->attemptFactory = $attemptFactory;
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
        $this->getSalableQuantityDataBySku = $getSalableQuantityDataBySku;
        $this->stockItem = $stockItem;
        $this->product = $product;
        $this->getProductTypeBySku = $getProductTypeBySku;
        $this->productFactory = $productFactory;
        $this->orderFactory = $orderFactory;
        $this->productLink = $productLink;

    }

    public function execute(Observer $observer)
    {
        $orderItem = $observer->getEvent()->getItem();
        $storeId = $orderItem->getStoreId();
        $productId = $orderItem->getProductId();
        $productSku = $orderItem->getSku();
        
        $orderItem->getProductType();
        
        $items = [];
        if (($orderItem->getProductType() != 'configurable') && ($orderItem->getProductType() != 'bundle')) {

            $parentItemId = $orderItem->getParentItemId();

            if ($parentItemId) {
                // The item has a parent

                $salebleQuantity = $this->getProductSalableQty($productSku);
                $defaultQuantity = $this->getProductDefaultQty($productSku);
                $childStockStatus = $this->getParentStockStatus($productSku);

                foreach ($this->getProductType($productSku) as $key => $value) {
                    $productType = $value;
                }

                $order = $this->orderFactory->create()->load($orderItem->getOrderId());
                $parentItem = $order->getItemById($parentItemId);
                $parentproductId = $parentItem->getProductId();

                $parentItem = $this->productFactory->getById($parentproductId); // Or use other methods to load the parent item details

                // Assuming $parentItem is an instance of \Magento\Sales\Model\Order\Item
                
                $parentProductType = $parentItem->getTypeId();
                $parentProductSku = $parentItem->getSku();
                $parentstockstatus = $this->getParentStockStatus($parentProductSku);
                
                // Set parent details
                $parentDetails = [
                    'product_id' => $parentItemId,
                    'type' => $parentProductType,
                    'sku' => $parentProductSku,
                    'is_in_stock' => $parentstockstatus
                ];

                // Prepare item data
                $itemData = [
                    'product_id' => $productId,
                    'type' => $productType,
                    'saleble_quantity' => $salebleQuantity,
                    'default_quantity' => $defaultQuantity,
                    'sku' => $productSku,
                    'is_in_stock' => $childStockStatus,
                    'parent_details' => $parentDetails
                ];
            
                // Add item data to items array
                $items[] = $itemData;

            } else {


                if (($orderItem->getProductType() == 'grouped')) {

                    $connection = $this->productLink->getConnection();
                    $select = $connection->select()
                        ->from($this->productLink->getMainTable(), ['product_id'])
                        ->where('linked_product_id = ?', $productId)
                        ->where('link_type_id = ?', \Magento\GroupedProduct\Model\ResourceModel\Product\Link::LINK_TYPE_GROUPED);

                        // Fetch the result
                        $results = $connection->fetchCol($select);

                        $parentIDs = array_map('intval', $results);
                        

                    
                        // The item does not have a parent
                        //echo "This item is not a child item.";

                        $salebleQuantity = $this->getProductSalableQty($productSku);
                        $defaultQuantity = $this->getProductDefaultQty($productSku);
                        $simpleStockStatus = $this->getParentStockStatus($productSku);

                        foreach ($this->getProductType($productSku) as $key => $value) {
                            $productType = $value;
                        }


                        foreach ($parentIDs as $parentId) {
                            $parentItem = $this->productFactory->getById($parentId); // Or use other methods to load the parent item details

                            // Assuming $parentItem is an instance of \Magento\Sales\Model\Order\Item
                            
                            $parentProductType = $parentItem->getTypeId();
                            $parentProductSku = $parentItem->getSku();
                            $parentstockstatus = $this->getParentStockStatus($parentProductSku);
                            
                            // Set parent details
                            $parentDetails[] = [
                                'product_id' => $parentId,
                                'type' => $parentProductType,
                                'sku' => $parentProductSku,
                                'is_in_stock' => $parentstockstatus
                            ];
                        }

                            // Prepare item data
                            $itemData = [
                                'product_id' => $productId,
                                'type' => $productType,
                                'saleble_quantity' => $salebleQuantity,
                                'default_quantity' => $defaultQuantity,
                                'is_in_stock' => $simpleStockStatus,
                                'sku' => $productSku,
                                'parent_details' => $parentDetails
                            ];
                        
                            // Add item data to items array
                            $items[] = $itemData;
                        
                        

                }else{
                    // The item does not have a parent
                    //echo "This item is not a child item.";

                    $salebleQuantity = $this->getProductSalableQty($productSku);
                    $defaultQuantity = $this->getProductDefaultQty($productSku);
                    $simpleStockStatus = $this->getParentStockStatus($productSku);

                    foreach ($this->getProductType($productSku) as $key => $value) {
                        $productType = $value;
                    }

                    // Prepare item data
                    $itemData = [
                        'product_id' => $productId,
                        'type' => $productType,
                        'saleble_quantity' => $salebleQuantity,
                        'default_quantity' => $defaultQuantity,
                        'is_in_stock' => $simpleStockStatus,
                        'sku' => $productSku,
                    ];
                
                    // Add item data to items array
                    $items[] = $itemData;
                }


            }


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

        // Log or handle changes
        
        $this->customLogger->info('Arjjjun');
    }

    public function getProductSalableQty($productSku)
    {
        $salable = $this->getSalableQuantityDataBySku->execute($productSku);
        return $salable[0]['qty'];
    }

    public function getProductDefaultQty($productSku)
    {
        return $this->stockItem->getStockItemBySku($productSku)->getQty();
    }

    public function getVariantId($productSku)
    {
        return $this->product->getIdBySku($productSku);
    }

    public function getProductType($sku)
    {
        return $this->getProductTypeBySku->execute([$sku]);
    }


    public function getParentStockStatus($sku)
    {
        $stockItem = $this->stockItem->getStockItemBySku($sku);

        // Return the stock status
        return (bool)$stockItem->getIsInStock();
    }


    
}