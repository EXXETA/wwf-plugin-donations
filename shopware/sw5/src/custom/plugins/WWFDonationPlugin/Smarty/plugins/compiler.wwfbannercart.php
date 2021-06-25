<?php

/**
 * Smarty Compiler function to get the wwf banner markup
 *
 * File: compiler.wwfbanner.php
 * Type: compiler
 * Name: wwfbanner
 * Purpose: get wwf banner markup
 *
 * @param array $params
 * @param Smarty $smarty
 * @return string
 */
function smarty_compiler_wwfbannercart(array $params, Smarty &$smarty)
{
    $mediaService = Shopware()->Container()->get(\WWFDonationPlugin\Service\MediaService::class);
    $productService = Shopware()->Container()->get(\WWFDonationPlugin\Service\ProductService::class);
    $csrfTokenManager = Shopware()->Container()->get('shopware.csrftoken_validator');
    $pluginLogger = Shopware()->Container()->get('pluginlogger');
    $donationPluginInstance = Shopware()->Container()->get(\WWFDonationPlugin\Service\DonationPluginInstance::class);
    /* @var $donationPluginInstance \WWFDonationPlugin\Service\DonationPluginInstance */

    // get configured campaign
    $charitySettingsManager = $donationPluginInstance->getSettingsManagerInstance();
    $campaign = $charitySettingsManager->getSetting(\WWFDonationPlugin\Service\CharitySettingsManager::wwfCartCampaignSettingKey, 'protect_species_coin');
    $isMiniBanner = $charitySettingsManager->getSetting(\WWFDonationPlugin\Service\CharitySettingsManager::wwfCartCampaignIsMiniBanner, false);
    $miniBannerTargetPage = $charitySettingsManager->getSetting(\WWFDonationPlugin\Service\CharitySettingsManager::WWF_DONATIONS_MINI_BANNER_CAMPAIGN_TARGET_PAGE, '');

    $bannerHandler = new \WWFDonationPlugin\Service\ShopwareBannerHandler(
        $mediaService, $csrfTokenManager,
        $productService, $miniBannerTargetPage, $pluginLogger
    );

    if ($isMiniBanner) {
        $banner = new \exxeta\wwf\banner\MiniBanner($bannerHandler, $donationPluginInstance, $campaign);
    } else {
        $banner = new \exxeta\wwf\banner\Banner($bannerHandler, $donationPluginInstance, $campaign);
    }
    return $banner->render();
}