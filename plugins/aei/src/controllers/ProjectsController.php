<?php
/**
 * AEI plugin for Craft CMS 3.x
 *
 * Firebelly plugin AEI project-related behavior
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
class ProjectsController extends Controller
{
    /**
     * Reorder projects request
     * actions/aei/projects/reorder-projects
     *
     * @return mixed
     */
    public function actionReorderProjects()
    {
        try {
            // Import all sections specified in sections-to-import[] param
            $market = Craft::$app->getRequest()->get('market');
            $projectIds = Craft::$app->getRequest()->get('project-ids');
            $importResult = AEI::$plugin->projects->reorderProjects($market, $projectIds);
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
     * Update all market projectIds fields checking for removed/added projects
     */
    public function actionUpdateMarketProjects() {
        AEI::$plugin->projects->updateMarketProjects();
    }
}
