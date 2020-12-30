<?php
declare(strict_types=1);

namespace WWFDonationPlugin\Service;

use exxeta\wwf\banner\model\CharityCampaign;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Class ProductService
 *
 * @package WWFDonationPlugin\Service
 */
class ProductService
{
    const MANUFACTURER_NAME_WWF_GERMANY = 'WWF Deutschland';
    const WWF_PRODUCT_NUMBER_PREFIX = 'WWF-DE-';

    /**
     * @var CharityCampaignManager
     */
    protected $campaignManager;

    /**
     * @var EntityRepositoryInterface
     */
    protected $taxRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $productCategoryRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $manufacturerRepository;

    /**
     * ProductService constructor.
     *
     * @param CharityCampaignManager $campaignManager
     * @param EntityRepository $taxRepository
     * @param EntityRepository $productRepository
     * @param EntityRepository $productCategoryRepository
     * @param EntityRepository $manufacturerRepository
     */
    public function __construct(CharityCampaignManager $campaignManager,
                                EntityRepository $taxRepository,
                                EntityRepository $productRepository,
                                EntityRepository $productCategoryRepository,
                                EntityRepository $manufacturerRepository)
    {
        $this->campaignManager = $campaignManager;
        $this->taxRepository = $taxRepository;
        $this->productRepository = $productRepository;
        $this->productCategoryRepository = $productCategoryRepository;
        $this->manufacturerRepository = $manufacturerRepository;
    }

    public function createProducts(Context $context): void
    {
        $charityCampaigns = $this->campaignManager->getAllCampaigns();
        $productManufacturerId = $this->getOrCreateProductManufacturerId($context);
        $taxId = $this->getOrCreateZeroTaxRateEntityId($context);
        // TODO add category
        // TODO add product images
        // TODO add parent + children products?

        $productNumberCounter = 0;
        foreach ($charityCampaigns as $charityCampaign) {
            /* @var CharityCampaign $charityCampaign */
            $productId = Uuid::randomHex();
            $productNumber = self::WWF_PRODUCT_NUMBER_PREFIX . ++$productNumberCounter;

            $productCriteria = (new Criteria())->addFilter(new EqualsFilter('productNumber', $productNumber));
            $potentiallyExistingProduct = $this->productRepository->searchIds($productCriteria, $context);
            $isUpdate = !empty($potentiallyExistingProduct->firstId());

            if ($isUpdate) {
                // update
                $data = [
                    'id' => $potentiallyExistingProduct->firstId(),
                    'description' => $charityCampaign->getDescription(),
                    'stock' => 1,
                    'name' => 'WWF-Spende: ' . $charityCampaign->getName(),
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1.00, 'net' => 1.00, 'linked' => false]],
                    'active' => true,
                    'shipping_free' => true,
                    'min_purchase' => 1,
                    'max_purchase' => 1000,
                ];
                $this->productRepository->update([$data], $context);
            } else {
                // insert
                $data = [
                    'id' => $productId,
                    'productNumber' => $productNumber,
                    'description' => $charityCampaign->getDescription(),
                    'stock' => 1,
                    'name' => 'WWF-Spende: ' . $charityCampaign->getName(),
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1.00, 'net' => 1.00, 'linked' => false]],
                    'manufacturerId' => $productManufacturerId,
                    'taxId' => $taxId,
                    'active' => true,
                    'shippingFree' => true,
                    'minPurchase' => 1,
                    'maxPurchase' => 5000,
                    'weight' => 0,
                    'height' => 0,
                    'length' => 0,
                ];
                $this->productRepository->upsert([$data], $context);
            }
        }
    }

    public function getOrCreateProductManufacturerId(Context $context): string
    {
        $getProductManufacturer = function () use (&$context): ?string {
            $criteria = (new Criteria())->addFilter(new EqualsFilter('name', self::MANUFACTURER_NAME_WWF_GERMANY));
            return $this->manufacturerRepository->searchIds($criteria, $context)->firstId();
        };
        $productManufacturer = $getProductManufacturer();
        if (empty($productManufacturer)) {
            $data = [
                'name' => self::MANUFACTURER_NAME_WWF_GERMANY,
                'link' => 'https://www.wwf.de/',
                'description' => self::MANUFACTURER_NAME_WWF_GERMANY,
            ];
            $this->manufacturerRepository->create([$data], $context);
            $productManufacturer = $getProductManufacturer();
        }
        return $productManufacturer;
    }

    /**
     * Method to get the id of a tax rate with zero percent. If no one exists, it will be created.
     *
     * @param Context $context
     * @return string
     */
    public function getOrCreateZeroTaxRateEntityId(Context $context): string
    {
        $getTaxRecords = function () use (&$context): ?string {
            $criteria = (new Criteria())->addFilter(new EqualsFilter("taxRate", 0.0))->setLimit(1);
            return $this->taxRepository->searchIds($criteria, $context)->firstId();
        };
        $taxEntity = $getTaxRecords();
        if (empty($taxEntity)) {
            // create one
            $taxData = [
                'name' => 'Keine Steuer (0 %)',
                'taxRate' => 0.0,
            ];
            $this->taxRepository->create([$taxData], $context);
            $taxEntity = $getTaxRecords();
        }
        return $taxEntity;
    }
}