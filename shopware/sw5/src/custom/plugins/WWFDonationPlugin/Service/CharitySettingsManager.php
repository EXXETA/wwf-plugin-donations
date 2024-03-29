<?php
/*
 * Copyright 2020-2021 EXXETA AG, Marius Schuppert
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace WWFDonationPlugin\Service;

use exxeta\wwf\banner\AbstractSettingsManager;

/**
 * Class CharitySettingsManager
 *
 * @package WWFDonationPlugin\Service
 */
class CharitySettingsManager extends AbstractSettingsManager
{
    const wwfCartCampaignSettingKey = 'wwfDonationCartCampaign';

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * only used in static install context!
     *
     * @var SystemConfigService
     */
    private static $systemConfigServiceStatic;

    /**
     * CharitySettingsManager constructor.
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public static function init(): void
    {
        foreach (static::$settings as $settingKey => $defaultValue) {
            static::$systemConfigServiceStatic->set(static::convertSettingKey($settingKey), $defaultValue);
        }
    }

    public static function uninstall()
    {
        // NOTE: the shopware 5 system will remove all the plugin configuration items
    }

    public static function getPluginName(): string
    {
        return 'WWFDonationPlugin';
    }

    public function isCartIntegrationEnabled(): bool
    {
        $setting = $this->getSetting('wwfDonationsIsCartIntegrationEnabled', false);
        return boolval($setting);
    }

    public function getSetting(string $settingKey, $defaultValue)
    {
        $value = $this->systemConfigService->get(static::convertSettingKey($settingKey));
        if ($value === null) {
            return $defaultValue;
        }
        return $value;
    }

    public function updateSetting(string $settingKey, $value): void
    {
        $this->systemConfigService->set(static::convertSettingKey($settingKey), $value);
    }

    /**
     * we need to convert the setting keys here from snake_case to lowerCamelCase.
     * Only the latter is supported by shopware 6.
     *
     * @param string $settingKey
     * @return string
     */
    public static function convertSettingKey(string $settingKey): string
    {
        $output = str_replace('_', ' ', trim($settingKey));
        $output = lcfirst(ucwords($output)); // lower camel case
        $output = str_replace(' ', '', $output);
        return sprintf('%s', $output);
    }

    /**
     * used in (un-)install context only
     *
     * @param SystemConfigService $systemConfigService
     */
    public static function setSystemConfigServiceStatic(SystemConfigService $systemConfigService): void
    {
        self::$systemConfigServiceStatic = $systemConfigService;
    }
}