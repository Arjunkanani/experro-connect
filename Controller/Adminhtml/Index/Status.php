<?php
namespace Experro\Connect\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Status extends Action implements HttpGetActionInterface
{
    protected $resultPageFactory;
    
    public function __construct(
        Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->resultPageFactory->create();
        $page->getConfig()->getTitle()->prepend(__('Connect To Experro'));

        return $page;
    }

}
