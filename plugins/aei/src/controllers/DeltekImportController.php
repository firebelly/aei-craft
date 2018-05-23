<?php
/**
 * AEI plugin for Craft CMS 3.x
 *
 * Firebelly plugin for custom AEI functionality
 *
 * @link      https://www.firebellydesign.com/
 * @copyright Copyright (c) 2018 Firebelly Design
 */

namespace firebelly\aei\controllers;

use firebelly\aei\AEI;
use Craft;
use craft\web\Controller;

/**
 * DeltekImport Controller
 *
 * @author    Firebelly Design
 * @package   AEI
 * @since     1.0.0
 */
class DeltekImportController extends Controller
{
    /**
     * Import Records request
     * actions/aei/deltek-import/import-records
     *
     * @return mixed
     */
    public function actionImportRecords()
    {
        try {
            // Import all sections specified in sections-to-import[] param
            $sections_to_import = Craft::$app->getRequest()->get('sections-to-import');
            $deltek_ids = Craft::$app->getRequest()->get('deltek-ids');
            $importResult = AEI::$plugin->deltekImport->importRecords($sections_to_import, $deltek_ids);
            $response = [
                'status'  => 1,
                'log'     => $importResult->log,
                'summary' => $importResult->summary,
            ];

        } catch (\Exception $e) {
            $response = [
                'status'  => 0,
                'message' => $e->getMessage()
            ];
        }

        return json_encode($response);
    }
}
