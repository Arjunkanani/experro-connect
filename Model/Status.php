<?php
namespace Experro\Connect\Model;

use Magento\Framework\Model\AbstractModel;

class Status extends AbstractModel
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init('Experro\Connect\Model\ResourceModel\Status');
    }
}
