<?php

namespace Experro\Connect\Controller\Adminhtml\Experro;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Experro\Connect\Helper\Data;
use Magento\Integration\Api\AuthorizationServiceInterface;
use Magento\Integration\Model\Oauth\TokenFactory;

class GetTokenDetails extends Action
{
    protected $oauthService;
    protected $integrationService;
    protected $tokenFactory;
    protected $integrationFactory;
    protected $helperData;
    protected $authorizationService;

    public function __construct(
        Context $context,
        OauthServiceInterface $oauthService,
        IntegrationServiceInterface $integrationService,
        Data $helperData,
        AuthorizationServiceInterface $authorizationService,
        TokenFactory $tokenFactory // Inject the token factory
    ) {
        $this->oauthService = $oauthService;
        $this->integrationService = $integrationService;
        $this->helperData = $helperData;
        $this->authorizationService = $authorizationService;
        $this->tokenFactory = $tokenFactory;
        parent::__construct($context);
    }

    public function execute()
    { 
        try {
            // Assume the integration name is known or passed
            $integrationName = 'Magento Connector'; 
            $integration = $this->integrationService->findByName($integrationName);

            if ($integration && $integration->getId()) {
                // If the integration exists, fetch OAuth token details using the helper function
                $response = $this->getTokenResponse($integration->getConsumerId());
            } else {
                // If the integration is not found, generate a new one using helper method
                $this->generateIntegrationToken();

                // Re-fetch the newly created integration
                $newIntegration = $this->integrationService->findByName($integrationName);

                if ($newIntegration && $newIntegration->getId()) {
                    // Fetch new OAuth token details using the helper function
                    $response = $this->getTokenResponse($newIntegration->getConsumerId());
                } else {
                    // Handle case if new integration creation fails
                    $response = [
                        'success' => false,
                        'message' => 'Failed to create a new integration.',
                    ];
                }
            }
        } catch (\Exception $e) {
            $response = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }


        // Return the response in JSON format
        $this->getResponse()->representJson(
            json_encode($response)
        );
    }

    private function getTokenResponse($consumerId) {
        // Fetch OAuth token details
        $tokenDetails = $this->oauthService->getAccessToken($consumerId);

        // Fetch Consumer Key and Secret
        $consumerDetails = $this->oauthService->loadConsumer($consumerId);

        // Return the response structure
        return [
            'success' => true,
            'clientId' => $consumerDetails->getKey(),
            'clientSecret' => $consumerDetails->getSecret(),
            'accessToken' => $tokenDetails->getToken(),
            'accessTokenSecret' => $tokenDetails->getSecret(),
        ];
    }


    private function generateIntegrationToken()
    { 
        $name = 'Magento Connector';
        $email = '';
        $endpoint = $this->helperData->getBaseUrl();

        // Check whether the integration already exists
        $integrationExists = $this->integrationService->findByName($name);

        if ($integrationExists && $integrationExists->getId()) {
            return [
                'success' => false,
                'message' => 'Integration already exists',
            ];
        } else {
            $integrationData = [
                'name' => $name,
                'email' => $email,
                'status' => '1',
                'endpoint' => $endpoint,
                'setup_type' => '0'
            ];

            try {
                // Create Integration
                $integration = $this->integrationService->create($integrationData);
                $integrationId = $integration->getId();
                $consumerName = 'Integration' . $integrationId;

                // Create Consumer
                $consumer = $this->oauthService->createConsumer(['name' => $consumerName]);
                if (!$consumer) {
                    throw new \Exception('Failed to create consumer.');
                }
                $consumerId = $consumer->getId();

                // Prepare data for update
                $integrationDataToUpdate = [
                    'integration_id' => $integrationId,
                    'consumer_id' => $consumerId,
                    'name' => $name, 
                    'email' => $email,
                    'status' => '1', 
                    'endpoint' => $endpoint, 
                    'setup_type' => '0'
                ];
                
                // Update the integration with the new consumer ID
                $this->integrationService->update($integrationDataToUpdate);

                // Grant Permissions
                $this->authorizationService->grantAllPermissions($integrationId);

                // Create and Save Access Token using the token factory
                        /** @var \Magento\Integration\Model\Oauth\Token $token */
                        $token = $this->tokenFactory->create(); // Use factory to create token instance
                        $uri = $token->createVerifierToken($consumerId);
                        $token->setType('access'); // Set token type
                        $token->save(); // Save the token

                        return [
                            'success' => true,
                            'integrationId' => $integrationId,
                            'consumerId' => $consumerId,
                        ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }
    }

}
