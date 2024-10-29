<?php

namespace Experro\Connect\Controller\Adminhtml\Experro;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Experro\Connect\Model\ResourceModel\Status\CollectionFactory;
use Experro\Connect\Model\StatusFactory;

class ViewDetails extends Action
{
    protected $resultJsonFactory;
    protected $connectStatusCollectionFactory;
    protected $statusFactory;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CollectionFactory $connectStatusCollectionFactory,
        StatusFactory $statusFactory
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->connectStatusCollectionFactory = $connectStatusCollectionFactory;
        $this->statusFactory = $statusFactory;
        parent::__construct($context);
    }

    public function execute()
    { 
        $result = $this->resultJsonFactory->create();
        $request = $this->getRequest();

        // Get the token from the AJAX request
        $rowId = $request->getParam('rowId');
        
        $statusDetails = $this->getStatusDetails($rowId);

        // Return the selected data as a JSON response
        return $result->setData([
            'success' => true,
            'data' => $statusDetails
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
            'connection_name' => $statusData->getData('connection_name'),
            'experro_token' => $statusData->getData('experro_token'),
            'client_id' => $statusData->getData('client_id'),
            'client_secret' => $statusData->getData('client_secret'),
            'access_token' => $statusData->getData('access_token'),
            'access_token_secret' => $statusData->getData('access_token_secret'),
        ];
    }
}
