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
use firebelly\aei\records\DeltekLog;

use Craft;
use craft\web\Controller;

/**
 * DeltekImport Controller
 *
 * Generally speaking, controllers are the middlemen between the front end of
 * the CP/website and your plugin’s services. They contain action methods which
 * handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering
 * post data, saving it on a model, passing the model off to a service, and then
 * responding to the request appropriately depending on the service method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what
 * the method does (for example, actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Firebelly Design
 * @package   AEI
 * @since     1.0.0
 */
class DeltekImportController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    // todo: remove this when done developing
    protected $allowAnonymous = ['import-records'];

    /**
     * Handle a request going to our plugin's actionDoSomething URL,
     * e.g.: actions/aei/deltek-import/import-records
     *
     * @return mixed
     */
    public function actionImportRecords()
    {
        try {
            $sections_to_import = Craft::$app->getRequest()->get('sections-to-import');
            $importResult = AEI::$plugin->deltekImport->importRecords($sections_to_import);
            $response = [
                'status'  => 1,
                'log'     => $importResult->log,
                'summary' => $importResult->summary,
            ];

            // Store import summary + log in aei_deltek_log table
            $deltekLog = new DeltekLog();
            $deltekLog->log = $importResult->log;
            $deltekLog->summary = $importResult->summary;
            $deltekLog->save();
        } catch (Exception $e) {
            // todo: handle errors
        }

        if (Craft::$app->getRequest()->getIsAjax()) {
            return json_encode($response);
        } else {
            return print_r($response);
        }
        // $this->returnErrorJson($e->getMessage());
    }
}
