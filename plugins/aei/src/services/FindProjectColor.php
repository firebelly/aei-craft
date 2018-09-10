<?php
/**
 * AEI plugin for Craft CMS 3.x
 *
 * Firebelly plugin for custom AEI functionality
 *
 * @link      https://www.firebellydesign.com/
 * @copyright Copyright (c) 2018 Firebelly Design
 */

namespace firebelly\aei\services;

use firebelly\aei\AEI;

use Craft;
use craft\base\Component;

/**
 * FindProjectColor Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Firebelly Design
 * @package   AEI
 * @since     1.0.0
 */
class FindProjectColor extends Component
{
    public $colorSwatches = [
        'Black'  => '#282826',
        'Copper' => '#664747',
        'Brass'  => '#725e4f',
        'Green'  => '#545b44',
        'Blue'   => '#3d4460',
        'Teal'   => '#475b66',
        'Violet' => '#56425b',
        'Gray'   => '#595954',
    ];

    // Public Methods
    // =========================================================================

    /**
     * This function can literally be anything you want, and you can have as many service
     * functions as you want
     *
     * From any other plugin file, call it like this:
     *
     *     AEI::$plugin->findProjectColor->randomColor()
     *
     * @return mixed
     */
    public function randomSwatch()
    {
        $key = array_rand($this->colorSwatches);
        return ['label' => $key, 'color' => $this->colorSwatches[$key]];
    }
}
