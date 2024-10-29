<?php
namespace Experro\Connect\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Experro\Connect\Model\ResourceModel\Status\CollectionFactory;
use Magento\Framework\UrlInterface;

class Status extends Template
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    protected $connectStatusCollectionFactory;
    protected $_urlBuilder;

    /**
     * Constructor
     * 
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        CollectionFactory $connectStatusCollectionFactory,
        UrlInterface $urlBuilder,
        array $data = []
    ) {
        $this->connectStatusCollectionFactory = $connectStatusCollectionFactory;
        $this->_urlBuilder = $urlBuilder;
        parent::__construct($context, $data);
    }

    public function getStatusDetails()
    {
        $collection = $this->connectStatusCollectionFactory->create();
        return $collection->getData();
    }

     /**
     * Get admin URL for the custom route
     */
    public function getAdminUrl()
    {
        return $this->_urlBuilder->getUrl('checkprerequisites/index/system'); // Change to your route
    }

    public function getIconUrl()
    {
        return $this->getViewFileUrl('Experro_Connect::images/experro-logo.svg'); // Path to your icon
    }

    public function getMagentoIcon()
    {
        return $this->getViewFileUrl('Experro_Connect::images/Magento Icon.svg'); // Path to your icon
    }

    public function getExperroIcon()
    {
        return $this->getViewFileUrl('Experro_Connect::images/Experro Connect Icon 1.svg'); // Path to your icon
    }

    public function getArrowIcon()
    {
        return $this->getViewFileUrl('Experro_Connect::images/Two way arrows.svg'); // Path to your icon
    }


}
