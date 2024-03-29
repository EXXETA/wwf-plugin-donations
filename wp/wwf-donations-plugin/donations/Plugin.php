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


namespace donations;


use exxeta\wwf\banner\Banner;
use exxeta\wwf\banner\DonationPlugin;
use exxeta\wwf\banner\DonationPluginInterface;
use exxeta\wwf\banner\MiniBanner;
use exxeta\wwf\banner\model\CharityProduct;
use exxeta\wwf\banner\model\ReportGenerationModel;
use exxeta\wwf\banner\ReportGenerator;
use exxeta\wwf\banner\TextProvider;
use WC_Product_Simple;
use WP_Term;

/**
 * this class is a wrapper around the wordpress plugin integration logic
 */
class Plugin
{
    /**
     * path to the main plugin file
     *
     * @var string
     */
    private static $pluginFile;
    // shortcode of this plugin's banner (large)
    public static $bannerShortCode = 'wwf_donations_banner';
    // shortcode of this plugin's banner (small)
    public static $miniBannerShortCode = 'wwf_donations_banner_small';
    // block name of this plugin
    public static $blockTypeName = 'wwf-donations-plugin/checkout-banner';
    public static $blockTypeNameMini = 'wwf-donations-plugin/banner-mini';
    // parent menu slug of this plugin
    public static $menuSlug = 'wwf-donations-plugin';
    // plugin slug used by options
    public static $pluginSlug = 'wwf_donations_plugin';
    // custom post type for report
    public static $customPostType = 'donation_report';

    /**
     * @var DonationPluginInterface
     */
    private static $donationPlugin;

    /**
     * Plugin constructor.
     * @param string $pluginFile
     */
    public function __construct(string $pluginFile)
    {
        self::$pluginFile = $pluginFile;
        self::$donationPlugin = new DonationPlugin($pluginFile, new CharityProductManager(), new SettingsManager(), null);
    }

    /**
     * Get generic instance of a donation plugin mostly to use it in combination with the generic core library
     *
     * @return DonationPluginInterface
     */
    public static function getDonationPlugin(): DonationPluginInterface
    {
        return static::$donationPlugin;
    }

    private static function uploadImage(CharityProduct $singleProduct): ?int
    {
        // source: https://gist.github.com/hissy/7352933
        $file = plugin_dir_path(self::$pluginFile) . 'images/' . $singleProduct->getImagePath();
        $filename = basename($file);

        $upload_file = @wp_upload_bits($filename, null, file_get_contents($file));
        if (!$upload_file['error']) {
            $wp_filetype = wp_check_filetype($filename, null);
            $attachment = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_parent' => 0,
                'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            $attachment_id = wp_insert_attachment($attachment, $upload_file['file'], 0);
            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . "wp-admin" . '/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_file['file']);
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                return $attachment_id;
            }
        }
        return null;
    }

    /**
     * @param CharityProduct $singleProduct
     * @param \WC_Product $product
     * @return void
     */
    public static function createAndHandleProductImage(CharityProduct $singleProduct, \WC_Product $product)
    {
        $imageId = self::uploadImage($singleProduct);
        if ($imageId) {
            update_option($singleProduct->getImageIdSettingKey(), $imageId);
            $product->set_image_id($imageId);
            $product->save();
        } else {
            error_log(sprintf('%s: problem uploading image "%s"', self::$pluginFile,
                $singleProduct->getImagePath()));
        }
    }

    /**
     * @return string
     */
    public static function getPluginFile(): string
    {
        return self::$pluginFile;
    }

    /**
     * checking if dependent plugins (= woocommerce) are activated and present on class path
     */
    public function check(): void
    {
        $allActivePlugins = apply_filters('active_plugins', get_option('active_plugins'));
        if (!in_array('woocommerce/woocommerce.php', $allActivePlugins)) {
            die('Missing required plugin woocommerce');
        }
    }

    public function registerPluginHooks(): void
    {
        // plugin lifecycle hooks
        register_activation_hook(self::getPluginFile(), [Plugin::class, 'activate']);
        register_deactivation_hook(self::getPluginFile(), [Plugin::class, 'deactivate']);
        register_uninstall_hook(self::getPluginFile(), [Plugin::class, 'uninstall']);
        // register report custom post type
        add_action('init', [Plugin::class, 'setup_report_post_type'], 0);
        // register gutenberg block
        add_action('init', [Plugin::class, 'setup_banner_block']);
        // register shortcode (large banner)
        add_shortcode(self::$bannerShortCode, [Plugin::class, 'setup_banner_shortcode']);
        // register shortcode (small banner)
        add_shortcode(self::$miniBannerShortCode, [Plugin::class, 'setup_banner_shortcode_small']);

        // register styles
        add_action('wp_enqueue_scripts', [Plugin::class, 'handle_styles']);

        if (is_admin()) {
            // add menu to wp admin section
            add_action('admin_menu', [Plugin::class, 'setup_menu']);
            // register settings
            add_action('admin_init', [Plugin::class, 'setup_settings']);
        }

        // register cron hook as action
        if (!has_action('wwf_donations_report_check')) {
            add_action('wwf_donations_report_check', [Plugin::class, 'do_report_check']);
        }

        // uncomment the following line for debugging and force-register the scheduled event
        // wp_clear_scheduled_hook('wwf_donations_report_check');
        if (wp_next_scheduled('wwf_donations_report_check') === false) {
            $result = wp_schedule_event(time(), 'daily', 'wwf_donations_report_check');
            if (!$result) {
                error_log(sprintf('%s: Error registering check job for report generation', self::$pluginFile));
            }
        }

        // handle "mini-cart" addon
        $closure = function () {
            // the {@link MiniBanner} class will select its default based on user settings in wp
            echo do_shortcode('[' . self::$miniBannerShortCode . ']');
        };

        if (static::getDonationPlugin()->getSettingsManagerInstance()->getMiniBannerIsShownInMiniCart()) {
            add_action('woocommerce_after_mini_cart', $closure);
        } else {
            if (has_action('woocommerce_after_mini_cart', $closure) > 0) {
                remove_action('woocommerce_after_mini_cart', $closure);
            }
        }
    }

    // (de-)activation and (un-)install logic

    /**
     * this is called by wordpress if the plugin is activated
     * This is the place to init all products at once and store their WooCommerce product IDs in wordpress options.
     */
    static function activate(): void
    {
        // init all known products and store their IDs
        $charityProductManager = static::getDonationPlugin()->getCharityProductManagerInstance();
        $result = $charityProductManager->getCharityProductCategory();
        $termId = null;
        if (!$result instanceof WP_Term) {
            // create it
            $result = wp_insert_term("Spendemünzen", CharityProductManager::getWooProductCategoryTaxonomy(), [
                'description' => 'Kategorie für Charity Coins',
                'parent' => 0,
                'slug' => $charityProductManager->getCategoryId(),
            ]);
            $termId = $result['term_id'];
        } else {
            $termId = $result->term_id;
        }
        if (!$termId) {
            error_log("Could not create default coin category");
        }
        // add default products
        foreach ($charityProductManager->getAllProducts() as $singleProduct) {
            /*@var $singleProduct CharityProduct */

            $productId = get_option($singleProduct->getProductIdSettingKey());
            $isDeleted = false;
            // check if product was deleted
            if (!empty($productId)) {
                $product = wc_get_product($productId);
                if ($product instanceof \WC_Product && $product->get_status() == 'trash') {
                    $isDeleted = true;
                }
            }
            if (empty($productId) || $isDeleted) {
                $product = new WC_Product_Simple();
                $product->set_defaults();
                $product->set_name($singleProduct->getName());
                $product->set_slug($singleProduct->getSlug());
                $product->set_description($singleProduct->getDescription());
                $product->set_short_description($singleProduct->getDescription());
                $product->set_weight(0);
                // prices
                $product->set_regular_price($singleProduct->getPrice());
                $product->set_sale_price($singleProduct->getPrice());
                // disable stock management
                $product->set_manage_stock(false);
                $product->set_sold_individually(false);
                $product->set_backorders(false);
                // disable reviews
                $product->set_reviews_allowed(false);
                // set category id
                $product->set_category_ids([$termId]);
                $product->set_tax_status('none');
                $product->set_catalog_visibility('hidden');
                $product->set_virtual(true);
                // TODO: make product private? does it harm anything?

                // upload image
                self::createAndHandleProductImage($singleProduct, $product);
                $product->save();

                $productId = $product->get_id();
                update_option($singleProduct->getProductIdSettingKey(), $productId);
            } else {
                // check if product image exists or it should be created newly
                $product = wc_get_product($productId);
                if ($product instanceof \WC_Product) {
                    // this should always be the case here!
                    $imageId = $product->get_image_id();
                    $attachmentMedia = wp_get_attachment_image($imageId);
                    // check if attachment exists
                    if (empty($attachmentMedia) || !wp_attachment_is_image($imageId)) {
                        // create it otherwise
                        self::createAndHandleProductImage($singleProduct, $product);
                    }
                }
            }
        }
    }

    /**
     * this is called if the plugin is disabled
     */
    static function deactivate(): void
    {
        // atm there is nothing to do here
        remove_shortcode(self::$bannerShortCode);
        remove_shortcode(self::$miniBannerShortCode);

        if (function_exists('unregister_block_type')) {
            // Gutenberg is active.
            unregister_block_type(self::$blockTypeName);
            unregister_block_type(self::$blockTypeNameMini);
        }
        // cron clear
        wp_clear_scheduled_hook('wwf_donations_report_check');
    }

    /**
     * this is called if the plugin is uninstalled
     */
    static function uninstall(): void
    {
        // remove products of this plugin during uninstallation
        // TODO atm deletion of products + custom category is skipped
//        $charityProductManager = static::getDonationPlugin()->getCharityProductManagerInstance();
//        foreach ($charityProductManager->getAllProducts() as $singleProduct) {
//            /* @var $singleProduct CharityProduct */
//            $productId = get_option($singleProduct->getProductIdSettingKey());
//            if (!empty($productId)) {
//                // delete product
//                $product = wc_get_product($productId);
//                $product->delete();
//            }
//            // delete option
//            delete_option($singleProduct->getProductIdSettingKey());
//        }
//
//        // remove woo commerce product category which is technically a wordpress term - but only if it's not empty!
//        $result = $charityProductManager->getCharityProductCategory();
//        if ($result instanceof WP_Term) {
//            $productsInCategory = wc_get_term_product_ids($result->term_id, $charityProductManager->getWooProductCategoryTaxonomy());
//            if (count($productsInCategory) === 0) {
//                // we do only delete the category if its empty!
//                wp_delete_term($result->term_id, $charityProductManager->getWooProductCategoryTaxonomy());
//            }
//        }
        SettingsManager::uninstall();
    }

    /** banner block logic */
    static function setup_banner_block(): void
    {
        if (!function_exists('register_block_type')) {
            // Gutenberg is not active.
            return;
        }

        // automatically load dependencies and version
        $assetFile = include(plugin_dir_path(self::getPluginFile()) . 'build/index.asset.php');
        wp_register_script('checkout-charity-banner', plugins_url('build/index.js', self::getPluginFile()),
            $assetFile['dependencies'],
            $assetFile['version']
        );
        // after wooCommerce setup this value is available
        if (strlen(trim(get_option('woocommerce_cart_page_id'))) > 0) {
            wp_localize_script('checkout-charity-banner', 'cart_page_id', get_option('woocommerce_cart_page_id'));
        }
        register_block_type(self::$blockTypeName, [
            'editor_script' => 'checkout-charity-banner',
            'render_callback' => [Plugin::class, 'render_cart_block']
        ]);
        register_block_type(self::$blockTypeNameMini, [
            'editor_script' => 'charity-banner-mini',
            'render_callback' => [Plugin::class, 'render_cart_block_mini']
        ]);
    }

    static function render_cart_block($attributes = [], $content = ''): string
    {
        if (!isset($attributes['donationMode'])) {
            $campaign = static::getDonationPlugin()->getCharityProductManagerInstance()->getAllCampaignTypes()[0];
        } else {
            $campaign = $attributes['donationMode'];
        }
        $bannerHandler = new WooBannerHandler(plugin_dir_url(self::getPluginFile()));
        $banner = new Banner($bannerHandler, static::getDonationPlugin(), $campaign);
        return $banner->render();
    }

    static function render_cart_block_mini($attributes = [], $content = ''): string
    {
        if (!isset($attributes['donationMode'])) {
            $campaign = null;
        } else {
            $campaign = $attributes['donationMode'];
        }
        $bannerHandler = new WooBannerHandler(plugin_dir_url(self::getPluginFile()));
        $banner = new MiniBanner($bannerHandler, static::getDonationPlugin(), $campaign);
        return $banner->render();
    }

    static function setup_banner_shortcode($atts): string
    {
        $shortCodeAtts = shortcode_atts([
            'campaign' => static::getDonationPlugin()->getCharityProductManagerInstance()->getAllCampaignTypes()[0],
        ], $atts, self::$bannerShortCode);
        return (new Banner(new WooBannerHandler(plugin_dir_url(self::getPluginFile())), static::getDonationPlugin(), $shortCodeAtts['campaign']))->render();
    }

    static function setup_banner_shortcode_small($atts): string
    {
        $shortCodeAtts = shortcode_atts([
            'campaign' => null,
        ], $atts, self::$miniBannerShortCode);
        return (new MiniBanner(new WooBannerHandler(plugin_dir_url(self::getPluginFile())), static::getDonationPlugin(), $shortCodeAtts['campaign']))->render();
    }

    static function handle_styles(): void
    {
        // no style inclusion on admin pages atm
        if (is_admin()) {
            return;
        }
        $post = get_post();
        $isStyleAndScriptIncluded = false;

        $settingsManager = static::getDonationPlugin()->getSettingsManagerInstance();
        // consider "force"-setting
        if ($settingsManager instanceof SettingsManager && $settingsManager->isCssInclusionForced()) {
            $isStyleAndScriptIncluded = true;
        }
        if (!$isStyleAndScriptIncluded && $settingsManager->getMiniBannerIsShownInMiniCart()) {
            $isStyleAndScriptIncluded = true;
        }
        if (!$isStyleAndScriptIncluded && $post !== null) {
            // only add banner styles if the block of this plugin is used
            if (has_blocks($post->post_content)) {
                $blocks = parse_blocks($post->post_content);
                foreach ($blocks as $singleBlock) {
                    if (in_array($singleBlock['blockName'], [self::$blockTypeName, self::$blockTypeNameMini])) {
                        $isStyleAndScriptIncluded = true;
                        break;
                    }
                }
            }
            // AND/OR only add banner styles if the shortcode is used
            if (has_shortcode($post->post_content, self::$bannerShortCode)
                || has_shortcode($post->post_content, self::$miniBannerShortCode)) {
                $isStyleAndScriptIncluded = true;
            }
        }
        if ($isStyleAndScriptIncluded) {
            wp_enqueue_style('wwf-donations-plugin-styles', plugin_dir_url(self::getPluginFile()) . 'banner.css');
            wp_enqueue_script('wwf-donations-mini-banner', plugin_dir_url(self::getPluginFile()) . 'scripts/banner.js', [], false, true);
        }
    }

    // menu related code
    static function setup_menu(): void
    {
        // add plugin pages
        add_menu_page('Spendenübersicht', 'Spendenübersicht', 'manage_options',
            self::$menuSlug, null, 'dashicons-cart');
        add_action('admin_notices', [Plugin::class, 'add_donation_info_banner']);

        // submenu page:
        add_submenu_page(self::$menuSlug, 'Aktuell', 'Aktuell', 'manage_options',
            'wwf-donations-current', [Plugin::class, 'handle_menu_current']);
        // add options menu
        add_options_page('Spenden', 'Spenden', 'manage_options',
            'wwf-donations-settings', [Plugin::class, 'handle_menu_settings']);
    }

    static function handle_menu_settings(): void
    {
        include plugin_dir_path(self::getPluginFile()) . 'donations/pages/settings.php';
    }

    static function handle_menu_current(): void
    {
        include plugin_dir_path(self::getPluginFile()) . 'donations/pages/current.php';
    }

    static function setup_report_post_type(): void
    {
        // TODO add translation labels
        register_post_type(self::$customPostType, [
            'public' => false,
            'label' => 'Spenden-Reports',
            'description' => 'Einträge über die Spendenaktivität im Shop',
            'hierarchical' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => self::$menuSlug,
            'show_in_admin_bar' => false,
            'show_in_rest' => false,
            'supports' => [
                'title',
                'editor',
            ],
            'has_archive' => false,
            'can_export' => true,
            'map_meta_cap' => false,
            'rewrite' => [
                ['slug' => self::$customPostType]
            ],
            'delete_with_user' => false,
            'query_var' => false,
            'capabilities' => [
                'create_posts' => 'do_not_allow',
                // you need admin rights to use the following capabilities
                'edit_post' => 'manage_options',
                'read_post' => 'manage_options',
                'delete_post' => 'do_not_allow',
                'edit_posts' => 'manage_options',
                'edit_others_posts' => 'do_not_allow',
                'publish_posts' => 'do_not_allow',
                'read_private_posts' => 'manage_options',
            ],
        ]);

        if (is_admin()) {
            // hide default edit features of wordpress
            add_filter('post_row_actions', [Plugin::class, 'remove_post_type_row_actions']);
            add_action('admin_head', [Plugin::class, 'remove_post_type_edit_button']);
        }
    }

    static function remove_post_type_row_actions(array $actions)
    {
        // hide "edit" and "quick edit" in rows of donation reports
        $screen = get_current_screen();
        if ($screen && $screen->post_type === self::$customPostType) {
            return [];
        }
        return $actions;
    }

    static function remove_post_type_edit_button()
    {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === self::$customPostType) {
            // disable edit button and edit form entirely via styles and script
            echo '<style>#publishing-action { display: none; }
                         #misc-publishing-actions .edit-post-status { display: none; }
                    </style>
                    <script lang="js">
                    (function() {
                        document.getElementById("#post").addEventListener("submit", e => {
                           e.preventDefault();
                        }) 
                    })();
                    </script>';
        }
    }

    /**
     * this banner is showing up on dashboard page of this plugin
     */
    static function add_donation_info_banner(): void
    {
        $screen = get_current_screen();
        if ($screen->post_type !== self::$customPostType
            || $screen->id !== 'edit-' . self::$customPostType) {
            return;
        }

        $allProductIds = [];
        foreach (static::getDonationPlugin()->getCharityProductManagerInstance()->getAllProducts() as $charityProduct) {
            /* @var $charityProduct CharityProduct */
            $allProductIds[] = get_option($charityProduct->getProductIdSettingKey());
        }

        $output = sprintf('<div class="notice notice-info">%s</div>', TextProvider::getGeneralInfoText($allProductIds));

        $settingsManager = static::getDonationPlugin()->getSettingsManagerInstance();

        $currentReportMode = $settingsManager->getCurrentReportingInterval();
        $interval = $settingsManager->getReportingIntervals()[$currentReportMode];
        $recipient = $settingsManager->getReportRecipientMail();

        $output .= '<div class="notice notice-info"><p>';
        $output .= '<strong>Automatisches Erzeugen von Spendenberichten:</strong> ' . $interval . '<br/>';
        $lastGenerationDate = $settingsManager->getReportLastGenerationDate();
        $output .= sprintf('<strong>Letztes Berichtsdatum:</strong> %s<br/>',
            $lastGenerationDate ? $lastGenerationDate->format('Y-m-d') : '-');
        $nextExecutionDate = ReportGenerator::calculateNextExecutionDate($currentReportMode, $lastGenerationDate);
        $output .= sprintf('<strong>Nächste Berichtserzeugung:</strong> %s<br/>',
            $nextExecutionDate ? $nextExecutionDate->format('Y-m-d') : '-');
        $lastCheckDate = $settingsManager->getReportLastCheck();
        $output .= sprintf('<strong>Letzte Überprüfung:</strong> %s<br/>',
            $lastCheckDate ? get_date_from_gmt(date('Y-m-d H:i:s', $lastCheckDate->getTimestamp()), 'F j, Y H:i:s') : '-');

        $output .= '<strong>Empfangsadresse:</strong> ';
        $output .= sprintf('<a href="mailto:%s">%s</a>', $recipient, esc_attr($recipient));
        $output .= '</p></div>';

        echo $output;
    }

    // settings related code
    static function setup_settings(): void
    {
        SettingsManager::init();
    }

    /**
     * @param \DateTime|null $timeRangeStart
     * @param \DateTime|null $timeRangeEnd
     * @param bool $isRegular
     * @throws \Exception
     */
    static function do_report_generate(\DateTime $timeRangeStart = null, \DateTime $timeRangeEnd = null,
                                                 $isRegular = false): void
    {
        $mode = static::getDonationPlugin()->getSettingsManagerInstance()->getCurrentReportingInterval();
        ReportGenerator::generateReport(new ReportGenerationModel($timeRangeStart, $timeRangeEnd, $mode,
            $isRegular, true), Plugin::getDonationPlugin(), new WooReportHandler());
    }

    static function do_report_check(): void
    {
        try {
            ReportGenerator::checkReportGeneration(Plugin::getDonationPlugin(), new WooReportHandler());
            static::getDonationPlugin()->getSettingsManagerInstance()->setReportLastCheck();
        } catch (\Exception $ex) {
            error_log(Plugin::getPluginFile() . ': error encountered during check for report generation');
            return;
        }
    }
}