<?php
/**
 * AEI plugin for Craft CMS 3.x
 *
 * Firebelly plugin for custom AEI functionality
 *
 * @link      https://www.firebellydesign.com/
 * @copyright Copyright (c) 2018 Firebelly Design
 */

namespace firebelly\aei;

use firebelly\aei\services\FindProjectColor as FindProjectColorService;
use firebelly\aei\services\DeltekImport as DeltekImportService;
use firebelly\aei\models\Settings;
use firebelly\aei\fields\ColorSwatches as ColorSwatchesField;
use firebelly\aei\widgets\DeltekImport as DeltekImportWidget;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\console\Application as ConsoleApplication;
use craft\web\UrlManager;
use craft\services\Fields;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;

use yii\base\Event;

/**
 * @author    Firebelly Design
 * @package   AEI
 * @since     1.0.0
 *
 * @property  FindProjectColorService $findProjectColor
 * @property  DeltekImportService $deltekImport
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class AEI extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * AEI::$plugin
     *
     * @var AEI
     */
    public static $plugin;
    public static $deltekSections = [
                'Offices',
                'People',
                'Awards',
                'Projects',
                'Impact',
            ];

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema version.
     *
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * AEI::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Add in our console commands
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'firebelly\aei\console\controllers';
        }

        // Add Refresh from Deltek button on admin sidebar if entry is project/impact and is disabled/draft
        Craft::$app->getView()->hook('cp.entries.edit.details', function(array &$context) {
            // @var EntryModel $entry
            $entry = $context['entry'];

            if (in_array($entry->type, ['projects', 'impact'])) {
                if (!$entry->enabled || !empty($entry->draftId)) {
                    $deltek_id = ($entry->type == 'projects') ? $entry->projectNumber : $entry->impactKey;
                    $url = UrlHelper::actionUrl('aei/deltek-import/import-records?sections-to-import[]='.$entry->type.'&deltek-ids='.$deltek_id.'&import-mode=refresh&referrer='.urlencode(Craft::$app->getRequest()->getUrl()));
                    return '<div class="meta"><a href="'.$url.'" class="btn">Refresh from Deltek</a></div>';
                }
            }
        });

        // Before saving project or impact, set deltek_ids field for future imports to avoid duplicate mediaBlocks
        Event::on(Elements::class, Elements::EVENT_BEFORE_SAVE_ELEMENT, function(Event $event) {
            if ($event->element instanceof \craft\elements\Entry) {
                if (!$event->element->propagating) {
                    if (in_array($event->element->type, ['projects', 'impact'])) {
                        $element = $event->element;
                        $deltekIds = AEI::$plugin->deltekImport->getDeltekIds($element);
                        $element->deltekIdsImported = implode(',', $deltekIds);
                        Craft::info('Elements::EVENT_BEFORE_SAVE_ELEMENT / AEI plugin setting deltek_ids field for element '.$element->title.' / deltek_ids: '.(implode(',', $deltekIds)), __METHOD__);
                    }
                }
            }
        });

        // Register our site routes
        // Event::on(
        //     UrlManager::class,
        //     UrlManager::EVENT_REGISTER_SITE_URL_RULES,
        //     function (RegisterUrlRulesEvent $event) {
        //         $event->rules['siteActionTrigger1'] = 'aei/deltek-import';
        //     }
        // );

        // Register our CP routes
        // Event::on(
        //     UrlManager::class,
        //     UrlManager::EVENT_REGISTER_CP_URL_RULES,
        //     function (RegisterUrlRulesEvent $event) {
        //         $event->rules['aei/deltek/logs'] = 'aei/deltek-import/logs';
        //     }
        // );

        // Register our fields
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = ColorSwatchesField::class;
            }
        );

        // Register our widgets
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = DeltekImportWidget::class;
            }
        );

        // Do something after we're installed
        // Event::on(
        //     Plugins::class,
        //     Plugins::EVENT_AFTER_INSTALL_PLUGIN,
        //     function (PluginEvent $event) {
        //         if ($event->plugin === $this) {
        //             // We were just installed
        //         }
        //     }
        // );

/**
 * Logging in Craft involves using one of the following methods:
 *
 * Craft::trace(): record a message to trace how a piece of code runs. This is mainly for development use.
 * Craft::info(): record a message that conveys some useful information.
 * Craft::warning(): record a warning message that indicates something unexpected has happened.
 * Craft::error(): record a fatal error that should be investigated as soon as possible.
 *
 * Unless `devMode` is on, only Craft::warning() & Craft::error() will log to `craft/storage/logs/web.log`
 *
 * It's recommended that you pass in the magic constant `__METHOD__` as the second parameter, which sets
 * the category to the method (prefixed with the fully qualified class name) where the constant appears.
 *
 * To enable the Yii debug toolbar, go to your user account in the AdminCP and check the
 * [] Show the debug toolbar on the front end & [] Show the debug toolbar on the Control Panel
 *
 * http://www.yiiframework.com/doc-2.0/guide-runtime-logging.html
 */
        Craft::info(
            Craft::t(
                'aei',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    /**
     * Return static array of Deltek Sections handled by importer
     * @return array
     */
    public function getDeltekSections()
    {
        return self::$deltekSections;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return \craft\base\Model|null
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * Returns the rendered settings HTML, which will be inserted into the content
     * block on the settings page.
     *
     * @return string The rendered settings HTML
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->getView()->renderTemplate(
            'aei/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
