<?php
namespace Experro\Connect\Model\ResourceModel\Attempt;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Experro\Connect\Model\Attempt as AttemptModel;
use Experro\Connect\Model\ResourceModel\Attempt as AttemptResourceModel;

class Collection extends AbstractCollection
{
    /**
     * Define model and resource model
     */
    protected function _construct()
    {
        $this->_init(AttemptModel::class, AttemptResourceModel::class);
    }
}
