<?php


namespace WWFDonationPlugin\Service;


use exxeta\wwf\banner\DonationPluginInterface;
use exxeta\wwf\banner\model\CharityProduct;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaFolderRepositoryDecorator;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaRepositoryDecorator;
use Shopware\Core\Content\Media\Exception\DuplicatedMediaFileNameException;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class MediaService
{
    const WWF_MEDIA_FOLDER_NAME = 'WWF Media';
    const ASSET_PATH_PREFIX = __DIR__ . '/../../images/';

    /**
     * @var DonationPluginInterface
     */
    protected $donationPlugin;

    /**
     * @var MediaRepositoryDecorator
     */
    protected $mediaRepository;

    /**
     * @var MediaFolderRepositoryDecorator
     */
    protected $mediaFolderRepository;

    /**
     * @var EntityRepository
     */
    protected $productMediaRepository;

    /**
     * @var FileSaver
     */
    protected $fileSaver;

    /**
     * MediaService constructor.
     *
     * @param DonationPluginInterface $donationPluginInstance
     * @param MediaRepositoryDecorator $mediaRepository
     * @param MediaFolderRepositoryDecorator $mediaFolderRepository
     * @param EntityRepository $productMediaRepository
     * @param FileSaver $fileSaver
     */
    public function __construct(DonationPluginInterface $donationPluginInstance,
                                MediaRepositoryDecorator $mediaRepository,
                                MediaFolderRepositoryDecorator $mediaFolderRepository,
                                EntityRepository $productMediaRepository,
                                FileSaver $fileSaver)
    {
        $this->donationPlugin = $donationPluginInstance;
        $this->mediaRepository = $mediaRepository;
        $this->mediaFolderRepository = $mediaFolderRepository;
        $this->productMediaRepository = $productMediaRepository;
        $this->fileSaver = $fileSaver;
    }

    public function install(): void
    {
        $this->createTopLevelDirectory();
        $this->importProductImages();
    }

    public function getMediaRecordBySlug(string $slug): ?MediaEntity
    {
        $charityProduct = $this->donationPlugin->getCharityProductManagerInstance()->getProductBySlug($slug);
        if ($charityProduct instanceof CharityProduct) {
            $baseMediaName = basename($charityProduct->getImagePath(), '.png');

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('fileName', $baseMediaName));
            $entitySearchResult = $this->mediaRepository->search($criteria, Context::createDefaultContext());
            if ($entitySearchResult->getTotal() > 0) {
                return $entitySearchResult->first();
            }
        }
        return null;
    }

    public function getProductMediaRecord(string $mediaId, string $productId): ?ProductMediaEntity
    {
        if (empty($mediaId) || empty($productId)) {
            return null;
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addFilter(new EqualsFilter('mediaId', $mediaId));

        $entitySearchResult = $this->productMediaRepository->search($criteria, Context::createDefaultContext());
        if ($entitySearchResult->getTotal() > 0) {
            return $entitySearchResult->first();
        }
        return null;
    }

    protected function createTopLevelDirectory(): void
    {
        $mediaFolderRecord = $this->getMediaFolder();
        if ($mediaFolderRecord != null) {
            // folder already exists
            return;
        }

        // create new top-level media folder
        $this->mediaFolderRepository->create([[
            'name' => self::WWF_MEDIA_FOLDER_NAME,
            'useParentConfiguration' => false,
            'configuration' => [],
        ]], Context::createDefaultContext());
    }

    protected function importProductImages(): void
    {
        $mediaFolderId = $this->getMediaFolder()->getId();
        if (!$mediaFolderId) {
            // FIXME error log!
            return;
        }
        // FIXME check if entry exist!
        $charityProducts = $this->donationPlugin->getCharityProductManagerInstance()->getAllProducts();
        foreach ($charityProducts as $charityProduct) {
            $productMediaRecord = $this->getMediaRecordBySlug($charityProduct->getSlug());
            if ($productMediaRecord != null) {
                continue;
            }
            $fileNameWithoutExt = str_replace(sprintf('.%s', 'png'), '', $charityProduct->getImagePath());
            $this->importProductImage($fileNameWithoutExt, $mediaFolderId, 'png');
        }

        foreach ($this->donationPlugin->getCharityProductManagerInstance()->getAllCampaignBannerFileNames() as $bannerImageFileName) {
            $fileType = 'jpg';
            $fileNameWithoutExt = str_replace(sprintf('.%s', $fileType), '', $bannerImageFileName);
            $this->importProductImage($fileNameWithoutExt, $mediaFolderId, $fileType);
        }
    }

    private function getMediaFolder(): ?MediaFolderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::WWF_MEDIA_FOLDER_NAME));

        $context = Context::createDefaultContext();
        $entitySearchResult = $this->mediaFolderRepository->search($criteria, $context);
        if ($entitySearchResult->getTotal() > 0) {
            // a directory with this name does already exist
            return $entitySearchResult->first();
        }
        return null;
    }

    /**
     * @param string $fileName
     * @param string $mediaFolderId
     * @param string $fileType e.g. 'png' - without leading dot
     */
    protected function importProductImage(string $fileName, string $mediaFolderId, string $fileType): void
    {
        $productImagePath = sprintf('%s%s.%s', self::ASSET_PATH_PREFIX, $fileName, $fileType);
        $tempFile = tempnam(sys_get_temp_dir(), '');
        copy($productImagePath, $tempFile);

        $fileSize = filesize($tempFile);
        $mediaFile = new MediaFile($tempFile, sprintf('image/%s', $fileType), $fileType, $fileSize);

        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->mediaRepository->create([[
            'id' => $mediaId,
            'mediaFolderId' => $mediaFolderId
        ]], $context);

        try {
            $fileName = basename($productImagePath, sprintf('.%s', $fileType));
            $this->fileSaver->persistFileToMedia($mediaFile, $fileName, $mediaId, $context);
        } catch (DuplicatedMediaFileNameException $ex) {
            // this is okay.
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
