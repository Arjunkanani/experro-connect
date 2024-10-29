<?php
namespace Experro\Connect\Cron;

use Experro\Connect\Model\ResourceModel\Attempt\CollectionFactory;
use Experro\Connect\Logger\Logger;
use Experro\Connect\Model\AttemptFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Experro\Connect\Helper\Data as CustomHelper;

class ProcessAttempts
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;
    /**
     * @var Logger
     */
    protected $customLogger;
    /**
     * @var AttemptFactory
     */
    protected $attemptFactory;
    protected $productRepository;
    protected $storeManager;
    /**
     * @var CustomHelper
     */
    protected $customHelper;

    /**
     * Constructor
     *
     * @param CollectionFactory $collectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        Logger $customLogger,
        AttemptFactory $attemptFactory,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        CustomHelper $customHelper
    )
    {
        $this->collectionFactory = $collectionFactory;
        $this->customLogger = $customLogger;
        $this->attemptFactory = $attemptFactory;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->customHelper = $customHelper;
    }

    /**
     * Execute method that will be called by the CRON job
     */
    public function execute()
    {

        // Attempt Logic is set as below
        // First is the Natural attempt that is via observer & event, in case it fails then update the table experro_connect_attempt with attempt no 0.
        // Then CRON will pickup first attempt from database & if it fails then will increase the attempt no to 1
        // Then CRON will pickup first attempt from database & if it fails then will increase the attempt no to 2
        // Then CRON will pickup first attempt from database & if it fails then will increase the attempt no to 3
        // If the attempt get passes then that row will be deleted from table experro_connect_attempt
        // If the attempt passes total 3 times then stop attempt & send details to log API

        try {
            // Fetch attempts from the table
            $attemptCollection = $this->collectionFactory->create();

            // Load all data from the collection
            $attempts = $attemptCollection->getItems();

            // Iterate through the data and log it or use it as needed
            foreach ($attempts as $attempt) {

                $type = $attempt->getType();
                $relatedId = $attempt->getRelatedId();
                $storeCode = $attempt->getStoreCode();
                $attemptNo = $attempt->getAttemptNo();

                if ($attemptNo >= 3) {
                    $this->logCompletion($type, $relatedId);
                } else {
                    $this->processData($type, $relatedId, $storeCode);
                }
            }

        } catch (\Exception $e) {
            // Log any errors
            $this->customLogger->error('Error processing attempts: ' . $e->getMessage());
        }
    }

    protected function logCompletion($type, $relatedId)
    {
        $message = sprintf(
            'All 3 attempts are completed to send data to Experro. Following is the %s ID: %s',
            ucfirst($type),
            $relatedId
        );
        $this->customLogger->info($message);
    }

    protected function processData($type, $relatedId, $storeCode)
    {
        try {
            switch ($type) {
                case 'product':
                    $this->productData($relatedId, $storeCode);
                    break;
                case 'order':
                    $this->orderData($relatedId, $storeCode);
                    break;
                case 'customer':
                    $this->customerData($relatedId, $storeCode);
                    break;
                case 'bulkproduct':
                    $this->bulkproductData($relatedId, $storeCode);
                    break;
                case 'category':
                    $this->categoryData($relatedId, $storeCode);
                    break;
                default:
                    throw new \Exception("Unknown type: $type");
            }
        } catch (\Exception $e) {
            $this->customLogger->error('An error occurred in execute(): ' . $e->getMessage());
            $this->customLogger->error($e->getTraceAsString());
        }
    }

    public function productData($relatedid,$storeCode)
    {
        
        $eventType = 'Product';
        $eventAction = 'Updated';
        $experroEndPointResponse = $this->customHelper->experroEndPoint($storeCode,$eventType,$eventAction,$relatedid);

        if ($experroEndPointResponse != 'success') {
            
            try {

                    // Load the existing attempt by related_id, store code and type
                    $attemptCollection = $this->collectionFactory->create();
                    $existingAttempt = $attemptCollection
                        ->addFieldToFilter('related_id', $relatedid)
                        ->addFieldToFilter('type', 'product')
                        ->addFieldToFilter('store_code', $storeCode)
                        ->getFirstItem(); // Assuming there will be only one record with this combination

                    if ($existingAttempt->getId()) {

                        // Record exists, update it
                        $existingAttempt->setAttemptNo($existingAttempt->getAttemptNo() + 1); // Increment the attempt number
                        $existingAttempt->save();
                        // Success message
                        $this->customLogger->info($existingAttempt->getAttemptNo().' Attempt : Data has been successfully inserted in attempt table for product not sent to experro.');

                    } else {
                        $this->customLogger->info('Attempt cron issue. No such product with ID '.$relatedid.' exists in attempt column.');
                    }

                    
                } catch (\Exception $e) {
                    // Error message
                    $this->customLogger->info('Unable to insert data in attempt table: ' . $e->getMessage());
                }
        }else{

            // If successful attempt then delete the row from database

            // Use CollectionFactory to load the records based on type and related_id
            $attemptCollection = $this->collectionFactory->create();
            $attemptCollection->addFieldToFilter('related_id', $relatedid)
                            ->addFieldToFilter('store_code', $storeCode)
                              ->addFieldToFilter('type', 'product');

            // Loop through each item in the collection and delete it
            foreach ($attemptCollection as $attempt) {
                try {
                    $attempt->delete();
                    $this->customLogger->info('Deleted attempt with ID: ' . $attempt->getId());
                } catch (\Exception $e) {
                    $this->customLogger->error('Error deleting attempt with ID: ' . $attempt->getId() . '. ' . $e->getMessage());
                }
            }

        }
    }

    public function orderData($relatedid,$storeCode)
    {
        
        $eventType = 'Order';
        $eventAction = 'Updated';
        $experroEndPointResponse = $this->customHelper->experroEndPoint($storeCode,$eventType,$eventAction,$relatedid);

        if ($experroEndPointResponse != 'success') {
            
            try {
                    // Load the existing attempt by related_id and type
                    $attemptCollection = $this->collectionFactory->create();
                    $existingAttempt = $attemptCollection
                        ->addFieldToFilter('related_id', $relatedid)
                        ->addFieldToFilter('type', 'order')
                        ->addFieldToFilter('store_code', $storeCode)
                        ->getFirstItem(); // Assuming there will be only one record with this combination

                    if ($existingAttempt->getId()) {

                        // Record exists, update it
                        $existingAttempt->setAttemptNo($existingAttempt->getAttemptNo() + 1); // Increment the attempt number
                        $existingAttempt->save();

                        // Success message
                        $this->customLogger->info($existingAttempt->getAttemptNo().' Attempt : Data has been successfully inserted in attempt table for order not sent to experro.');

                    } else {

                        $this->customLogger->info('Attempt cron issue. No such order with ID '.$relatedid.' exists in attempt column.');
                    }

                    
                } catch (\Exception $e) {
                    // Error message
                    $this->customLogger->info('Unable to insert data in attempt table: ' . $e->getMessage());
                }
        }else{

            // If successful attempt then delete the row from database

            // Use CollectionFactory to load the records based on type and related_id
            $attemptCollection = $this->collectionFactory->create();
            $attemptCollection->addFieldToFilter('related_id', $relatedid)
            ->addFieldToFilter('store_code', $storeCode)
            ->addFieldToFilter('type', 'order');

            // Loop through each item in the collection and delete it
            foreach ($attemptCollection as $attempt) {
                try {
                    $attempt->delete();
                    $this->customLogger->info('Deleted attempt with ID: ' . $attempt->getId());
                } catch (\Exception $e) {
                    $this->customLogger->error('Error deleting attempt with ID: ' . $attempt->getId() . '. ' . $e->getMessage());
                }
            }

        }
     
    }

    public function customerData($relatedid,$storeCode)
    {   
        $eventType = 'Customer';
        $eventAction = 'Updated';
        $experroEndPointResponse = $this->customHelper->experroEndPoint($storeCode,$eventType,$eventAction,$relatedid);

        if ($experroEndPointResponse != 'success') {
            
            try {
                    // Load the existing attempt by related_id and type
                    $attemptCollection = $this->collectionFactory->create();
                    $existingAttempt = $attemptCollection
                        ->addFieldToFilter('related_id', $relatedid)
                        ->addFieldToFilter('type', 'customer')
                        ->addFieldToFilter('store_code', $storeCode)
                        ->getFirstItem(); // Assuming there will be only one record with this combination

                    if ($existingAttempt->getId()) {

                        // Record exists, update it
                        $existingAttempt->setAttemptNo($existingAttempt->getAttemptNo() + 1); // Increment the attempt number
                        $existingAttempt->save();

                        // Success message
                        $this->customLogger->info($existingAttempt->getAttemptNo().' Attempt : Data has been successfully inserted in attempt table for customer not sent to experro.');

                    } else {

                        $this->customLogger->info('Attempt cron issue. No such customer with ID '.$relatedid.' exists in attempt column.');
                    }

                    
                } catch (\Exception $e) {
                    // Error message
                    $this->customLogger->info('Unable to insert data in attempt table: ' . $e->getMessage());
                }
        }else{

            // If successful attempt then delete the row from database

            // Use CollectionFactory to load the records based on type and related_id
            $attemptCollection = $this->collectionFactory->create();
            $attemptCollection->addFieldToFilter('related_id', $relatedid)
            ->addFieldToFilter('store_code', $storeCode)
            ->addFieldToFilter('type', 'customer');

            // Loop through each item in the collection and delete it
            foreach ($attemptCollection as $attempt) {
                try {
                    $attempt->delete();
                    $this->customLogger->info('Deleted attempt with ID: ' . $attempt->getId());
                } catch (\Exception $e) {
                    $this->customLogger->error('Error deleting attempt with ID: ' . $attempt->getId() . '. ' . $e->getMessage());
                }
            }

        }
        
    }

    public function bulkproductData($relatedid,$storeCode)
    {        

        // Here when we save product in admin then for ALL store views we receive admin as store code, in this case we will pass default store code

        if ($storeCode == 'admin') {
            $storeCode = $this->storeManager->getDefaultStoreView()->getCode();
        }
        
        $eventType = 'Product';
        $eventAction = 'Updated';
        $experroEndPointResponse = $this->customHelper->experroEndPoint($storeCode,$eventType,$eventAction,$relatedid);

        if ($experroEndPointResponse != 'success') {
            
            try {
                    // Load the existing attempt by related_id and type
                    $attemptCollection = $this->collectionFactory->create();
                    $existingAttempt = $attemptCollection
                        ->addFieldToFilter('related_id', $relatedid)
                        ->addFieldToFilter('type', 'bulkproduct')
                        ->addFieldToFilter('store_code', $storeCode)
                        ->getFirstItem(); // Assuming there will be only one record with this combination

                    if ($existingAttempt->getId()) {

                        // Record exists, update it
                        $existingAttempt->setAttemptNo($existingAttempt->getAttemptNo() + 1); // Increment the attempt number
                        $existingAttempt->save();

                        // Success message
                        $this->customLogger->info($existingAttempt->getAttemptNo().' Attempt : Data has been successfully inserted in attempt table for bulkproduct not sent to experro.');

                    } else {

                        $this->customLogger->info('Attempt cron issue. No such product with ID '.$relatedid.' exists in attempt column.');
                    }

                } catch (\Exception $e) {
                    // Error message
                    $this->customLogger->info('Unable to insert data in attempt table: ' . $e->getMessage());
                }
        }else{

            // If successful attempt then delete the row from database

            // Use CollectionFactory to load the records based on type and related_id
            $attemptCollection = $this->collectionFactory->create();
            $attemptCollection->addFieldToFilter('related_id', $relatedid)
            ->addFieldToFilter('store_code', $storeCode)
            ->addFieldToFilter('type', 'bulkproduct');

            // Loop through each item in the collection and delete it
            foreach ($attemptCollection as $attempt) {
                try {
                    $attempt->delete();
                    $this->customLogger->info('Deleted attempt with ID: ' . $attempt->getId());
                } catch (\Exception $e) {
                    $this->customLogger->error('Error deleting attempt with ID: ' . $attempt->getId() . '. ' . $e->getMessage());
                }
            }

        }
        
    }

    public function categoryData($relatedid,$storeCode)
    {   
        
        $eventType = 'Category';
        $eventAction = 'Updated';
        $experroEndPointResponse = $this->customHelper->experroEndPoint($storeCode,$eventType,$eventAction,$relatedid);

        if ($experroEndPointResponse != 'success') {
            
            try {
                    // Load the existing attempt by related_id and type
                    $attemptCollection = $this->collectionFactory->create();
                    $existingAttempt = $attemptCollection
                        ->addFieldToFilter('related_id', $relatedid)
                        ->addFieldToFilter('store_code', $storeCode)
                        ->addFieldToFilter('type', 'category')
                        ->getFirstItem(); // Assuming there will be only one record with this combination

                    if ($existingAttempt->getId()) {

                        // Record exists, update it
                        $existingAttempt->setAttemptNo($existingAttempt->getAttemptNo() + 1); // Increment the attempt number
                        $existingAttempt->save();

                        // Success message
                        $this->customLogger->info($existingAttempt->getAttemptNo().' Attempt : Data has been successfully inserted in attempt table for category not sent to experro.');

                    } else {

                        $this->customLogger->info('Attempt cron issue. No such category with ID '.$relatedid.' exists in attempt column.');
                    }

                    
                } catch (\Exception $e) {
                    // Error message
                    $this->customLogger->info('Unable to insert data in attempt table: ' . $e->getMessage());
                }
        }else{

            // If successful attempt then delete the row from database

            // Use CollectionFactory to load the records based on type and related_id
            $attemptCollection = $this->collectionFactory->create();
            $attemptCollection->addFieldToFilter('related_id', $relatedid)
                              ->addFieldToFilter('type', 'category')
                              ->addFieldToFilter('store_code', $storeCode);

            // Loop through each item in the collection and delete it
            foreach ($attemptCollection as $attempt) {
                try {
                    $attempt->delete();
                    $this->customLogger->info('Deleted attempt with ID: ' . $attempt->getId());
                } catch (\Exception $e) {
                    $this->customLogger->error('Error deleting attempt with ID: ' . $attempt->getId() . '. ' . $e->getMessage());
                }
            }

        }
        
    }
}
