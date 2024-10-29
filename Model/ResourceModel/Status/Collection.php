<?php
namespace Experro\Connect\Model\ResourceModel\Status;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Experro\Connect\Model\Status as StatusModel;
use Experro\Connect\Model\ResourceModel\Status as StatusResourceModel;

class Collection extends AbstractCollection
{
    /**
     * Define model and resource model
     */
    protected function _construct()
    {
        $this->_init(StatusModel::class, StatusResourceModel::class);
    }
}
