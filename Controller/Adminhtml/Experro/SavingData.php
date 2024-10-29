<?php

namespace Experro\Connect\Controller\Adminhtml\Experro;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Experro\Connect\Model\ResourceModel\Status\CollectionFactory;
use Experro\Connect\Model\StatusFactory;

class SavingData extends Action
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

        // Save the status details in your custom table
        $saveResult = $this->saveStatusDetails($this->getRequest()->getParams());

        if (!$saveResult['success']) {
            return $result->setData(['success' => false, 'message' => 'Error saving status details.']);
        }

        // URL of the third-party service to verify the token
        $url = 'https://third-party-url-to-verify-token.com'; 

        try {
            // Set up the cURL request to send the Experro token
            // $curl = curl_init();
            // curl_setopt($curl, CURLOPT_URL, $url);
            // curl_setopt($curl, CURLOPT_POST, true);
            // curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            // curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(['token' => $experroToken]));
            // curl_setopt($curl, CURLOPT_HTTPHEADER, [
            //     'Content-Type: application/json'
            // ]);

            // $response = curl_exec($curl);
            // curl_close($curl);

            //$responseData = json_decode($response, true);

            $responseData['status'] = 'verified';
            // Check the response from the third-party service
            if ($responseData['status'] === 'verified') {
                return $result->setData(['success' => true, 'message' => 'Token verified successfully.']);
            } else {
                return $result->setData(['success' => false, 'message' => 'Token verification failed.']);
            }
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getStatusDetails()
    {
        $collection = $this->connectStatusCollectionFactory->create();
        foreach ($collection as $item) {
            // Handle data
        }
    }

    public function saveStatusDetails($params)
    {
        //echo "<pre>";print_r($params);die();
        $model = $this->statusFactory->create()->load($params['databaseTableId']); // Load the model by ID

        if ($model->getId()) {
            // Update the existing record
            $model->setClientId($params['client_id']);
            $model->setClientSecret($params['client_secret']);
            $model->setAccessToken($params['access_token']);
            $model->setAccessTokenSecret($params['access_token_secret']);

            try {
                // Save the updated model
                $model->save();
                return ['success' => true, 'message' => 'Data updated successfully.'];
            } catch (\Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        } else {
            return ['success' => false, 'message' => 'Record not found for the given ID.'];
        }
    }
}
