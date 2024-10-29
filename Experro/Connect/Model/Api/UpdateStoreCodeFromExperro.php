<?php

namespace Experro\Connect\Model\Api;

use Experro\Connect\Api\UpdateStoreCodeFromExperroInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\DataObject;
use Experro\Connect\Model\ResourceModel\Status\CollectionFactory;
use Experro\Connect\Model\StatusFactory;

class UpdateStoreCodeFromExperro implements UpdateStoreCodeFromExperroInterface
{
    protected $connectStatusCollectionFactory;
    protected $statusFactory;

    public function __construct(
        CollectionFactory $connectStatusCollectionFactory,
        StatusFactory $statusFactory
    ) {
        $this->connectStatusCollectionFactory = $connectStatusCollectionFactory;
        $this->statusFactory = $statusFactory;
    }

    /**
     * Verifies the connection based on tenant_id, workspace_id, and environment_id.
     *
     * @param string $id
     * @param string $store_code
     * @return \Magento\Framework\DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateStoreCodeFromExperro($connection_id,$store_code)
    {   
        // Initialize an array to collect missing parameters
        $missingParams = [];

        // Check which parameters are missing
        if (!$connection_id) {
            $missingParams[] = 'connection_id';
        }

        if (!$store_code) {
            $missingParams[] = 'store_code';
        }

        // If there are missing parameters, throw an exception with a detailed message
        if (!empty($missingParams)) {
            throw new LocalizedException(
                __('Missing required parameters: %1.', implode(', ', $missingParams))
            );
        }
        // Assume you're verifying with some external service or database

        $id = $this->UpdateDatabaseDetails($connection_id,$store_code);

        if (!$id) {
            throw new LocalizedException(__('No matching entry found.'));
        }

        return $id;
    }


    public function UpdateDatabaseDetails($connection_id,$store_code)
    {
        $model = $this->statusFactory->create()->load($connection_id); // Load the model by ID

        if ($model->getId()) {
            // Update the existing record
            $model->setStoreCode($store_code);
            $model->setStatus('Connected');

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
