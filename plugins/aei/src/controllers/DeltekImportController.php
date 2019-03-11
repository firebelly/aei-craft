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
            $sectionsToImport = Craft::$app->getRequest()->get('sections-to-import');
            $deltekIds = Craft::$app->getRequest()->get('deltek-ids');
            $referrer = Craft::$app->getRequest()->get('referrer');
            $importMode = Craft::$app->getRequest()->get('import-mode') ?? 'basic';
            $importResult = AEI::$plugin->deltekImport->importRecords($sectionsToImport, $deltekIds, $importMode);
            $response = [
                'status'  => 1,
                'log'     => $importResult->log,
                'summary' => $importResult->summary,
                'message' => 'Success! '.$importResult->summary,
            ];

        } catch (\Exception $e) {
            $response = [
                'status'  => 0,
                'message' => 'Error! '.$e->getMessage()
            ];
        }
        if (!empty($referrer)) {
            Craft::$app->getSession()->setNotice($response['message']);
            return $this->redirect(urldecode($referrer));
        } else {
            return json_encode($response);
        }
    }

    /**
     * Reorder projects request
     * actions/aei/deltek-import/reorder-projects
     *
     * @return mixed
     */
    public function actionReorderProjects()
    {
        try {
            // Import all sections specified in sections-to-import[] param
            $market = Craft::$app->getRequest()->get('market');
            $projectIds = Craft::$app->getRequest()->get('project-ids');
            $category = \craft\elements\Category::find()
                ->group('markets')
                ->slug($market)
                ->one();
            $category->setFieldValues([
                'projectIds' => implode(',', $projectIds)
            ]);
            Craft::$app->elements->saveElement($category);
            // $importResult = AEI::$plugin->deltekImport->reorderProjects($market, $projectIds);
            $response = [
                'status'  => 1,
                'message' => 'Success!',
            ];

        } catch (\Exception $e) {
            $response = [
                'status'  => 0,
                'message' => 'Error! '.$e->getMessage()
            ];
        }
        return json_encode($response);
    }

    /**
     * Update all deltekId fields for projects/impact
     */
    public function actionUpdateAllDeltekIds() {
        $type = Craft::$app->getRequest()->get('type') ?? 'projects';
        return AEI::$plugin->deltekImport->updateAllDeltekIds($type);
    }

    /**
     * Index new images from text file (this is also available as a console command)
     */
    public function actionIndexNewImagesFromFile() {
        $file = Craft::$app->getRequest()->get('file') ?? 'newImages.txt';
        return AEI::$plugin->deltekImport->indexNewImagesFromFile($file);
    }

}
