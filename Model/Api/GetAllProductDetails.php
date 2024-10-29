<?php
 
namespace Experro\Connect\Model\Api;

use Experro\Connect\Logger\Logger;
use Experro\Connect\Helper\Data as CustomHelper;
use Magento\Catalog\Model\ProductRepository as ProductRepository;
use Magento\Framework\Api\SearchCriteriaInterface;
 
class GetAllProductDetails implements \Experro\Connect\Api\GetAllProductDetailsInterface
{
    protected $customLogger;
    /**
     * @var CustomHelper
     */
    protected $customHelper;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
 
    public function __construct(
        Logger $customLogger,
        CustomHelper $customHelper,
        ProductRepository $productRepository
    )
    {
        $this->customLogger = $customLogger;
        $this->customHelper = $customHelper;
        $this->productRepository = $productRepository;

    }
 
    /**
     * @inheritdoc
     */
 
    public function getAllProductDetails(SearchCriteriaInterface $searchCriteria)
    { 

        $collection = $this->productRepository->getList($searchCriteria);
        return $collection;

    }


}