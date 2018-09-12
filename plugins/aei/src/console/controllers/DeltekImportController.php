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
     * Run Deltek Importer for all sections (basic mode)
     *
     * @return mixed
     */
    public function actionIndex()
    {
        $sectionsToImport = [];
        $params = Craft::$app->getRequest()->getParams();
        // Specifying sections via param? e.g. `./craft aei/deltek-import awards people`
        if (count($params) > 1) {
            for ($i=1; $i < count($params); $i++) {
                $sectionsToImport[] = $params[$i];
            }
        } else {
            // Otherwise pull sections from settings
            $deltek_import_sections = AEI::$plugin->getSettings()->deltekActiveImportSections;
            foreach ($deltek_import_sections as $section => $active) {
                if ($active) {
                    $sectionsToImport[] = $section;
                }
            }
        }
        foreach ($sectionsToImport as $section) {
            if (in_array(ucfirst($section), AEI::$plugin->getDeltekSections())) {
                try {
                    echo "Running Deltek importer for: {$section}\n";
                    $importResult = AEI::$plugin->deltekImport->importRecords([strtolower($section)], '', 'basic via console');
                    echo $importResult->summary . ' (' . $importResult->exec_time . " seconds)\n";
                } catch (\Exception $e) {
                    echo 'Error: '.$e->getMessage() . "\n";
                }
            }
        }
        return "Done!\n";
    }

    /**
     * Index newly synced images from text file of absolute image paths, one per line
     *
     * @return mixed
     */
    public function actionIndexNewImages()
    {
        $params = Craft::$app->getRequest()->getParams();
        $file = !empty($params[1]) ? $params[1] : 'newImages.txt';
        try {
            echo "Running Deltek image indexer on {$file}...\n";
            $result = AEI::$plugin->deltekImport->indexNewImagesFromFile($file);
            echo strip_tags($result);
        } catch (\Exception $e) {
            echo 'Error: '.$e->getMessage() . "\n";
        }
        return "Done!\n";
    }
}
