<?php
namespace Experro\Connect\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Status extends AbstractDb
{
    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('experro_connect_status', 'id'); // 'experro_connect_status' is the table name, 'id' is the primary key
    }
}
