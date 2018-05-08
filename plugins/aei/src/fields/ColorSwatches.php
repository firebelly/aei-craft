<?php
/**
 * AEI plugin for Craft CMS 3.x
 *
 * Firebelly plugin for custom AEI functionality
 *
 * @link      https://www.firebellydesign.com/
 * @copyright Copyright (c) 2018 Firebelly Design
 */

namespace firebelly\aei\fields;

use firebelly\aei\AEI;
use firebelly\aei\assetbundles\colorswatchesfield\ColorSwatchesFieldAsset;
use firebelly\aei\models\ColorSwatches as ColorSwatchesModel;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Db;
use yii\db\Schema;
use craft\helpers\Json;

class ColorSwatches extends Field
{
    // Public Properties
    // =========================================================================

    /**
     * Available options.
     *
     * @var string
     */
    public $options = [['color' => '']];

    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('aei', 'Color Swatches');
    }

    // Public Methods
    // =========================================================================

    public function rules()
    {
        $rules = parent::rules();
        $rules = array_merge($rules, [
            ['options', 'each', 'rule' => ['required']],
        ]);

        return $rules;
    }

    public function getContentColumnType(): string
    {
        return Schema::TYPE_TEXT;
    }

    public function normalizeValue($value, ElementInterface $element = null)
    {
        if ($value instanceof ColorSwatchesModel) {
            return $value;
        }

        return new ColorSwatchesModel($value);
    }

    public function serializeValue($value, ElementInterface $element = null)
    {
        if ($value instanceof ColorSwatchesModel) {
            return $value;
        }

        return new ColorSwatchesModel($value);
    }

    public function getSettingsHtml()
    {
        $config = [
            'instructions' => Craft::t('aei', 'Define the available colors.'),
            'id'           => 'options',
            'name'         => 'options',
            'addRowLabel'  => Craft::t('aei', 'Add a color'),
            'cols'         => [
                'label' => [
                    'heading' => Craft::t('aei', 'Label'),
                    'type'    => 'singleline',
                ],
                'color' => [
                    'heading' => Craft::t('aei', 'Hex Colors (comma separated)'),
                    'type'    => 'singleline',
                ],
                'default' => [
                    'heading'      => Craft::t('aei', 'Default?'),
                    'type'         => 'checkbox',
                    'class'        => 'thin',
                ],
            ],
            'rows' => $this->options,
        ];

        // Render the settings template
        return Craft::$app->getView()->renderTemplate(
            'aei/_components/fields/ColorSwatches_settings',
            [
                'field'  => $this,
                'config' => $config,
            ]
        );
    }

    public function getInputHtml($value, ElementInterface $element = null): string
    {
        // Register our asset bundle
        Craft::$app->getView()->registerAssetBundle(ColorSwatchesFieldAsset::class);

        // Get our id and namespace
        $id = Craft::$app->getView()->formatInputId($this->handle);
        $namespacedId = Craft::$app->getView()->namespaceInputId($id);

        // Render the input template
        return Craft::$app->getView()->renderTemplate(
            'aei/_components/fields/ColorSwatches_input',
            [
                'name'         => $this->handle,
                'fieldValue'   => $value,
                'field'        => $this,
                'id'           => $id,
                'namespacedId' => $namespacedId,
            ]
        );
    }
}
