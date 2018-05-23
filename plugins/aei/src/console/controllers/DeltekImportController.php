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
 * Deltek Import
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
 * ./craft aei/deltek-import
 *
 * Actions must be in 'kebab-case' so actionDoSomething() maps to 'do-something',
 * and would be invoked via:
 *
 * ./craft aei/deltek-import/do-something
 *
 * @author    Firebelly Design
 * @package   AEI
 * @since     1.0.0
 */
class DeltekImportController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Handle aei/deltek-import console commands
     *
     * The first line of this method docblock is displayed as the description
     * of the Console Command in ./craft help
     *
     * @return mixed
     */
    public function actionIndex()
    {
        $deltek_import_sections = AEI::$plugin->getSettings()->deltekImportSections;
        foreach ($deltek_import_sections as $section => $active) {
            if ($active) {
                try {
                    echo "Running Deltek importer for: {$section}\n";
                    $importResult = AEI::$plugin->deltekImport->importRecords([strtolower($section)]);
                    echo $importResult->summary . ' (' . $importResult->exec_time . " seconds)\n";
                } catch (\Exception $e) {
                    echo 'Error: '.$e->getMessage() . "\n";
                }
            }
        }
        return 'Done!';
    }
}
