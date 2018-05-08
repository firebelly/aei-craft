<?php
/**
 * AEI plugin for Craft CMS 3.x
 *
 * Firebelly plugin for custom AEI functionality
 *
 * @link      https://www.firebellydesign.com/
 * @copyright Copyright (c) 2018 Firebelly Design
 */

namespace firebelly\aei\console\controllers;

use firebelly\aei\AEI;

use Craft;
use yii\console\Controller;
use yii\helpers\Console;

/**
 * ClearCache Command
 *
 * The first line of this class docblock is displayed as the description
 * of the Console Command in ./craft help
 *
 * Craft can be invoked via commandline console by using the `./craft` command
 * from the project root.
 *
 * Console Commands are just controllers that are invoked to handle console
 * actions. The segment routing is plugin-name/controller-name/action-name
 *
 * The actionIndex() method is what is executed if no sub-commands are supplied, e.g.:
 *
 * ./craft aei/clear-cache
 *
 * Actions must be in 'kebab-case' so actionDoSomething() maps to 'do-something',
 * and would be invoked via:
 *
 * ./craft aei/clear-cache/do-something
 *
 * @author    Firebelly Design
 * @package   AEI
 * @since     1.0.0
 */
class ClearCacheController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Handle aei/clear-cache console commands
     *
     * The first line of this method docblock is displayed as the description
     * of the Console Command in ./craft help
     *
     * @return mixed
     */
    public function actionIndex()
    {
        $result = 'something';

        echo "Welcome to the console ClearCacheController actionIndex() method\n";

        return $result;
    }

    /**
     * Handle aei/clear-cache/do-something console commands
     *
     * The first line of this method docblock is displayed as the description
     * of the Console Command in ./craft help
     *
     * @return mixed
     */
    public function actionDoSomething()
    {
        $result = 'something';

        echo "Welcome to the console ClearCacheController actionDoSomething() method\n";

        return $result;
    }
}
