<?php

namespace Experro\Connect\Controller\Adminhtml\Experro;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Experro\Connect\Model\ResourceModel\Status\CollectionFactory;
use Experro\Connect\Model\StatusFactory;

class VerifyToken extends Action
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
        $experroToken = $request->getParam('experroToken');
        $connectionName = $request->getParam('connectionName');
        
        if (!$experroToken) {
            return $result->setData(['success' => false, 'message' => 'Experro Token is required.']);
        }

        // URL of the third-party service to verify the token
        $url = 'https://admin.experro-dev.app/apis/magento-service/v1/public/stores/connection/validate';

        try {
            // Set up the cURL request to send the Experro token
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(['experro_token' => $experroToken]));
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);

            $response = curl_exec($curl);
            curl_close($curl);

            $responseData = json_decode($response, true);

            // echo "<pre>";
            // print_r($responseData);die();

            $responseData['Status'] = 'success';
            // Check the response from the third-party service
            if ($responseData['Status'] === 'success') {

                $data = $responseData['Data'];

                // Save the status details in your custom table
                $saveResult = $this->saveStatusDetails($data,$connectionName,$experroToken);

                if (!$saveResult['success']) {
                    return $result->setData(['success' => false, 'message' => 'Error saving status details.']);
                }

                return $result->setData(['success' => true, 'message' => 'Token verified successfully.', 'id'=>$saveResult['id']]);
            } else {
                return $result->setData(['success' => false, 'message' => $responseData['Error']['message'] ]);
            }
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function saveStatusDetails($responseData,$connectionName,$experroToken)
    {   
        $model = $this->statusFactory->create();
        $model->setExperroToken($experroToken);
        $model->setConnectionName($connectionName);
        $model->setTenantId($responseData['tenant_id']);
        $model->setWorkspaceId($responseData['workspace_id']);
        $model->setEnvironmentId(implode(', ', $responseData['environment_ids']));
        $model->setChannelName(implode(', ', $responseData['channel_names']));
        $model->setLanguages(implode(', ', $responseData['languages']));
        $model->setJwtSecret($responseData['jwt_secret']);
        $model->setExperroStoreHash($responseData['exp_store_hash']);
        $model->setLanguageIds(implode(', ',$responseData['language_ids']));
        $model->setChannelIds(implode(', ',$responseData['channel_ids']));
        $model->setStatus('Pending');
        

        try {
            // Save the model to the database
            $model->save();
            $entityId = $model->getId();
            return ['success' => true, 'message' => 'Data saved successfully.','id' => $entityId];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
