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
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    // todo: remove this when done developing
    protected $allowAnonymous = ['import-records'];

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
            $importResult = AEI::$plugin->deltekImport->importRecords($sections_to_import);
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
