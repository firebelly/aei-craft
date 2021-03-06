<?php
/**
 * AEI plugin for Craft CMS 3.x
 *
 * Firebelly plugin for custom AEI functionality
 *
 * @link      https://www.firebellydesign.com/
 * @copyright Copyright (c) 2018 Firebelly Design
 */

namespace firebelly\aei\models;

use firebelly\aei\AEI;

use Craft;
use craft\base\Model;

/**
 * AEI Settings Model
 *
 * This is a model used to define the plugin's settings.
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    Firebelly Design
 * @package   AEI
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * Some field model attribute
     *
     * @var string
     */
    public $deltekActiveImportSections = [
        'Offices' => true,
        'People' => true,
        'Awards' => true,
        'Projects' => true,
        'Impact' => true,
    ];

    // Public Methods
    // =========================================================================

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules()
    {
        return [
            ['deltekActiveImportSections', 'default', 'value' => [
                'Offices' => true,
                'People' => true,
                'Awards' => true,
                'Projects' => true,
                'Impact' => true,
            ]],
        ];
    }
}
