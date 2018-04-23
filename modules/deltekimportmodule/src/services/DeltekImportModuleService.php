<?php
/**
 * Deltek Import module for Craft CMS 3.x
 *
 * Pulls data from a custom MySQL dump from Deltek
 *
 * @link      https://www.firebellydesign.com/
 * @copyright Copyright (c) 2018 Firebelly Design
 */

namespace modules\deltekimportmodule\services;

use modules\deltekimportmodule\DeltekImportModule;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\elements\Category;
use craft\records\EntryType;

/**
 * DeltekImportModuleService Service
 *
 * All of your module’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other modules can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Firebelly Design
 * @package   DeltekImportModule
 * @since     1.0.0
 */
class DeltekImportModuleService extends Component
{
    private $db = null;
    private $log = '';

    /**
     * Run Deltek Import
     *
     * call via DeltekImportModule::$instance->deltekImportModuleService->importRecords()
     *
     * @return string
     */
    public function importRecords()
    {
        // Connect to Deltek db
        try {
            $this->db = new \PDO('mysql:host=localhost;dbname=aei_deltek;charset=utf8', 'root', 'root');
            $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            $return .= 'ERROR: ' . $e->getMessage();
        }

        // $this->importOffices();
        // $this->importPeople();
        $this->importProjects();

        return $this->log;
    }

    /**
     * Import Offices
     */
    private function importOffices() {
        $result = $this->db->query("SELECT * FROM offices");
        foreach($result as $row) {
            $fields = [];
            $entry = Entry::find()->section('offices')->where([
                'title' => $row['office_name']
            ])->one();

            if (!$entry) {
                $entry = $this->makeNewEntry('offices');
                $entry->title = $row['office_name'];
            }

            $fields = array_merge([
                'officeAddress1'   => $row['address1'],
                'officeAddress2'   => $row['address2'],
                'officeCity'       => $row['city'],
                'officeState'      => $row['state'],
                'officePostalCode' => $row['postal_code'],
                'officeCountry'    => $row['country'],
                'phoneNumber'      => $row['phone'],
                'officeMapUrl'     => $row['map_url'],
            ], $fields);
            $entry->setFieldValues($fields);

            if(Craft::$app->elements->saveElement($entry)) {
                $this->log .= '<h3>Office '.$row['office_name'].' Saved OK!</h3>';
                // ImportPlugin::log('Successfully saved entry "'.$entry->id.'"', LogLevel::Info);
            } else {
                $this->log .= '<p>Office '.$row['office_name'].' save error: '.print_r($entry->getErrors(), true).'</p>';
                // throw new \Exception("Couldn't save Office: " . print_r($entry->getErrors(), true));
                //     $errors = $entry->getErrors();
                //     foreach ($errors as $error) {
                //         ImportPlugin::log('Error:'.$error[0], LogLevel::Error);
                //     }

            }
        }
    }

    /**
     * Import People
     */
    private function importPeople() {
        $result = $this->db->query("SELECT * FROM employees");
        foreach($result as $row) {
            $fields = [];
            $entry = Entry::find()->section('people')->where([
                'content.field_personEmployeeNumber' => $row['employee_num']
            ])->one();

            if (!$entry) {
                $entry = $this->makeNewEntry('person');
            }

            $fields = array_merge([
                'email'                => $row['email'],
                'personFirstName'      => $row['firstname'],
                'personLastName'       => $row['lastname'],
                'personCertifications' => $row['certifications'],
                'phoneNumber'          => $row['phone'],
                'personTitle'          => $row['title'],
                'description'          => $row['bio'],
                'personEmployeeNumber' => $row['employee_num'],
            ], $fields);
            $entry->setFieldValues($fields);

            if(Craft::$app->elements->saveElement($entry)) {
                $this->log .= '<h3>Person '.$entry->title.' Saved OK!</h3>';

                // todo: remove existing relations first

                // Set Office
                $this->addRelation('office', 'offices', $row['office_name'], $entry);

                // Set Person Type
                foreach (explode(',', $row['person_type']) as $category_title) {
                    $this->addCategory('peopleTypes', 'peopleTypes', trim($category_title), $entry);
                }

                // ImportPlugin::log('Successfully saved entry "'.$entry->id.'"', LogLevel::Info);
            } else {
                $this->log .= '<p>Person '.$entry->title.' save error: '.print_r($entry->getErrors(), true).'</p>';
            }
        }
    }

    /**
     * Import Projects
     */
    private function importProjects() {
        $result = $this->db->query("SELECT * FROM projects");
        foreach($result as $row) {
            $fields = [];
            $entry = Entry::find()->section('projects')->where([
                'content.field_projectNumber' => $row['project_num'],
            ])->one();

            if (!$entry) {
                $entry = $this->makeNewEntry('projects');
            }

            $fields = array_merge([
                'projectNumber'     => $row['project_num'],
                'projectName'       => $row['name'],
                'projectClientName' => $row['client'],
                'projectTagline'    => $row['tagline'],
                'featured'          => $row['isfeatured'],
                'projectLocation'   => $row['location'],
                'projectLeedStatus' => $row['leed_status'],
            ], $fields);
            $entry->setFieldValues($fields);

            if(Craft::$app->elements->saveElement($entry)) {
                $this->log .= '<h3>Project '.$entry->title.' Saved OK!</h3>';

                // todo: remove existing relations first

                // Set Services
                foreach (explode(',', $row['services']) as $category_title) {
                    $this->addCategory('services', 'services', trim($category_title), $entry);
                }

                // Set Markets
                foreach (explode(',', $row['market']) as $category_title) {
                    $this->addCategory('markets', 'markets', trim($category_title), $entry);
                }

                // ImportPlugin::log('Successfully saved entry "'.$entry->id.'"', LogLevel::Info);
            } else {
                $this->log .= '<p>Person '.$entry->title.' save error: '.print_r($entry->getErrors(), true).'</p>';
            }
        }
    }

    /**
     * Slugify a string
     */
    private function slugify(string $title) {
        return preg_replace('/[^a-z0-9\-]/', '-', strtolower($title) );
    }

    /**
     * Helper function to init a new Entry with type, title
     * @param  string $entry_type Slug of entry type
     * @return object             New Entry object
     */
    private function makeNewEntry(string $entry_type)
    {
        $entryType = EntryType::find()->where(['handle' => $entry_type])->one();
        $entry = new Entry();
        $entry->sectionId = $entryType->getAttribute('sectionId');
        $entry->typeId = $entryType->getAttribute('id');
        $entry->authorId = 1;
        return $entry;
    }

    /**
     * Add a relation between two entries
     * @param string               $field_handle  field handle of entry relations
     * @param string               $section       section of related entry
     * @param string               $related_title related entry title
     * @param craft\elements\Entry $entry         entry to relate to
     */
    private function addRelation(string $field_handle, string $section, string $related_title, craft\elements\Entry $entry)
    {
        $related_entry = Entry::find()->section($section)->where([
            'title' => $related_title
        ])->one();
        if ($related_entry) {
            $field = Craft::$app->fields->getFieldByHandle($field_handle);
            try {
                Craft::$app->relations->saveRelations($field, $entry, [$related_entry->id]);
                $this->log .= "<p>Related {$field_handle}:{$section}:{$related_title} saved</p>";
            } catch (\Exception $e) {
                $this->log .= '<p>Error: '.print_r($e->getErrors(), true).'</p>';
                // ImportPlugin::log('Successfully saved entry "'.$entry->id.'"', LogLevel::Info);
            }
        }
    }

    /**
     * Set category of entry
     * @param string               $field_handle          field name of categories
     * @param string               $category_group_handle category group handle
     * @param string               $category_title        category title
     * @param craft\elements\Entry $entry                 entry to relate to
     */
    private function addCategory(string $field_handle, string $category_group_handle, string $category_title, craft\elements\Entry $entry)
    {
        if (empty($category_title) || empty($category_group_handle)) return;
        $category_group = Craft::$app->categories->getGroupByHandle($category_group_handle);
        $category = Category::find()->where([
            'title' => $category_title,
            'groupId' => $category_group->id,
        ])->one();
        if (!$category) return;
        $field = Craft::$app->fields->getFieldByHandle($field_handle);

        try {
            Craft::$app->relations->saveRelations($field, $entry, [$category->id]);
            $this->log .= "<p>Category {$field}:{$category} {$category->id} {$entry->id} saved</p>";
        } catch (\Exception $e) {
            $this->log .= '<p>Error: '.print_r($e->getErrors(), true).'</p>';
            // ImportPlugin::log('Successfully saved entry "'.$entry->id.'"', LogLevel::Info);
        }
    }

}
