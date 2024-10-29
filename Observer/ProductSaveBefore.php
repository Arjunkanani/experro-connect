<?php
namespace Experro\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;

class ProductSaveBefore implements ObserverInterface
{
    protected $logger;
    protected $stockRegistry;

    public function __construct(LoggerInterface $logger,StockRegistryInterface $stockRegistry)
    {
        $this->logger = $logger;
        $this->stockRegistry = $stockRegistry;
    }

    public function execute(Observer $observer)
    {
        
         $product = $observer->getProduct();
        
        // Load the stock item for the product
        $stockItem = $this->stockRegistry->getStockItem($product->getId());
        
        // Retrieve original quantity using stock item
        $oldQty = $stockItem->getOrigData('qty') !== null ? $stockItem->getOrigData('qty') : 'Not Set';
        $newQty = $stockItem->getQty();
        $this->logger->info($newQty);
        
        if ($oldQty != $newQty) {
            //$this->logger->info(sprintf('Product ID %s: Quantity changed from %s to %s', $product->getId(), $oldQty, $newQty));
        }

        // Log for stock status
        $oldStatus = $stockItem->getOrigData('is_in_stock') !== null ? $stockItem->getOrigData('is_in_stock') : 'Not Set';
        $newStatus = $stockItem->getIsInStock();
        $this->logger->info($newStatus);
        if ($oldStatus != $newStatus) {
            //$this->logger->info(sprintf('Product ID %s: Stock status changed from %s to %s', $product->getId(), $oldStatus ? 'In Stock' : 'Out of Stock', $newStatus ? 'In Stock' : 'Out of Stock'));
        }

    }
}
