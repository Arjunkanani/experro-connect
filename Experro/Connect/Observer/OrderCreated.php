<?php

namespace Experro\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Experro\Connect\Helper\Data as CustomHelper;
use Experro\Connect\Logger\Logger;
use Experro\Connect\Model\AttemptFactory;
use Experro\Connect\Model\ResourceModel\Attempt\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;


class OrderCreated implements ObserverInterface
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

    public function __construct(
        CustomHelper $customHelper,
        Logger $customLogger,
        AttemptFactory $attemptFactory,
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->customHelper = $customHelper;
        $this->customLogger = $customLogger;
        $this->attemptFactory = $attemptFactory;
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
    }

    public function execute(Observer $observer)
    { 
        $order = $observer->getEvent()->getOrder();
        $incrementId = $order->getIncrementId();

        try {

            // Get the current store ID
            $storeId = $order->getStoreId();


            // Fallback to default store view if necessary
            $store = $this->storeManager->getStore($storeId) ?: $this->storeManager->getStore(Store::DEFAULT_STORE_ID);
            $storeCode = $store->getCode();

            // Here when we save product in admin then for ALL store views we receive admin as store code, in this case we will pass default store code

            if ($storeCode == 'admin') {
                $storeCode = $this->storeManager->getDefaultStoreView()->getCode();
            }
            
            $eventType = 'Order';
            $eventAction = 'Updated';
            $experroEndPointResponse = $this->customHelper->experroEndPoint($storeCode,$eventType,$eventAction,$incrementId);

            $experroApiResponse = 200;
            if ($experroEndPointResponse == 'success') {
                $experroApiResponse = 200;
            }else{
                $experroApiResponse = 500;
            }
            // Log a custom message indicating successful execution
            $this->customLogger->info('Order '.$incrementId.' got updated.');

            // Before entering attempt need to check if the product data is already present in table If yes then only update attempt to 0
            // Start code to send data to attempt 
            
            if ($experroApiResponse == 500) {
                try {

                    // Create an instance of the model
                    $attempt = $this->attemptFactory->create();

                    // Load the existing attempt by related_id and type
                    $attemptCollection = $this->collectionFactory->create();
                    $existingAttempt = $attemptCollection
                        ->addFieldToFilter('related_id', $incrementId)
                        ->addFieldToFilter('store_code', $storeCode)
                        ->addFieldToFilter('type', 'order')
                        ->getFirstItem(); // Assuming there will be only one record with this combination

                    if ($existingAttempt->getId()) {
                        // Record exists, update it
                        $existingAttempt->setAttemptNo(0); // Increment the attempt number
                        $existingAttempt->save();
                    } else {

                        // Record does not exist, create a new one
                        $attempt->setData([
                            'related_id' => $incrementId,
                            'type' => 'order',
                            'attempt_no' => 0,
                            'store_code' => $storeCode
                        ]);
                        $attempt->save();
                    }


                    // Save the data
                    $attempt->save();

                    // Success message
                    $this->customLogger->info('Data has been successfully inserted in attempt table for order not sent to experro.');
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
}