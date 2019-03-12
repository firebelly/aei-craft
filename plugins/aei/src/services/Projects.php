<?php
/**
 * AEI plugin for Craft CMS 3.x
 *
 * Firebelly plugin AEI project-related behavior
  *
 * @link      https://www.firebellydesign.com/
 * @copyright Copyright (c) 2018 Firebelly Design
 */

namespace firebelly\aei\services;

use firebelly\aei\AEI;
use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\elements\Category;

/**
 * Projects Service
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
class Projects extends Component
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

    /**
     * Returns random swatch from project colors
     * @return string
     */
    public function randomSwatch()
    {
        $key = array_rand($this->colorSwatches);
        return ['label' => $key, 'color' => $this->colorSwatches[$key]];
    }

    /**
     * Saves custom order of project IDs to a hidden field for a market
     * @return string
     */
    public function reorderProjects($market, $projectIds)
    {
        $category = Category::find()
            ->group('markets')
            ->slug($market)
            ->one();
        if (!empty($category)) {
            $category->setFieldValues([
                'projectIds' => implode(',', $projectIds)
            ]);
            Craft::$app->elements->saveElement($category);
            return 1;
        } else {
            Craft::warning('Category not found');
            throw new \Exception('Category not found');
        }
    }

    /**
     * Update all market projectIds fields checking for removed/added projects
     */
    public function updateMarketProjects()
    {
        $markets = Category::find()
            ->group('markets')
            ->all();
        if (!empty($markets)) {
            foreach ($markets as $market) {
                $oldIds = [];
                if (!empty($market->projectIds)) {
                    $oldIds = explode(',', $market->projectIds);
                }
                $newIds = Entry::find()
                    ->section('projects')
                    ->relatedTo($market)
                    ->ids();
                // Find projects that have been removed
                $adjustedIds = array_intersect($oldIds, $newIds);
                // Find projects that have been added
                $newIds = array_diff($newIds, $adjustedIds);
                // Append new projects to bottom of sorted IDs
                $adjustedWithNewIds = array_merge($adjustedIds, $newIds);
                // Save new ID array to market
                $market->setFieldValues([
                    'projectIds' => implode(',', $adjustedWithNewIds)
                ]);
                Craft::$app->elements->saveElement($market);
            }
        }
    }
}
