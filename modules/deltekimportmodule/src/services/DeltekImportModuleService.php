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
    private $awards_cache = [];

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

        $this->importOffices();
        $this->importPeople();
        $this->importAwards();
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
            } else {
                $this->log .= '<p>Office '.$row['office_name'].' save error: '.print_r($entry->getErrors(), true).'</p>';
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

            // Find Office
            $office = Entry::find()->section('offices')->where([
                'title' => $row['office_name']
            ])->one();
            $office_ids = $office ? [$office->id] : [];

            // Find People Type IDs
            $people_type_ids = [];
            foreach (explode(',', $row['person_type']) as $category_title) {
                $category = $this->getCategory('peopleTypes', trim($category_title));
                if ($category) {
                    $people_type_ids[] = $category->id;
                }
            }

            // Populate Social Links (matrix field)
            if (!empty($row['linkedin'])) {
                $social_links = [
                    'new1' => [
                        'type' => 'socialLink',
                        'fields' => [
                            'socialNetwork' => 'LinkedIn',
                            'socialUrl'     => $row['linkedin'],
                        ]
                    ],
                ];
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
                'office'               => $office_ids,
                'peopleTypes'          => $people_type_ids,
                'socialLinks'          => $social_links,
            ], $fields);
            $entry->setFieldValues($fields);

            if(Craft::$app->elements->saveElement($entry)) {
                $this->log .= '<h3>Person '.$entry->title.' Saved OK!</h3>';
            } else {
                $this->log .= '<p>Person '.$entry->title.' save error: '.print_r($entry->getErrors(), true).'</p>';
            }
        }
    }

    /**
     * Import Awards
     */
    private function importAwards() {
        $result = $this->db->query("SELECT * FROM project_awards");
        foreach($result as $row) {
            $fields = [];
            $entry = Entry::find()->section('awards')->where([
                'content.field_awardKey' => $row['award_key']
            ])->one();

            if (!$entry) {
                $entry = $this->makeNewEntry('awards');
                $entry->title = $row['name'];
            }

            $fields = array_merge([
                'awardDate'   => $row['date'],
                'awardIssuer' => $row['issuer'],
            ], $fields);
            $entry->setFieldValues($fields);

            if(Craft::$app->elements->saveElement($entry)) {
                $this->log .= '<h3>Award '.$row['name'].' Saved OK!</h3>';
                // Store award entry id and project_num for setting associations in importProjects()
                $this->awards_cache[] = [ 'id' => $entry->id, 'project_num' => $row['project_num'] ];
            } else {
                $this->log .= '<p>Award '.$row['name'].' save error: '.print_r($entry->getErrors(), true).'</p>';
            }
        }
    }

    /**
     * Import Projects
     */
    private function importProjects() {
        $result = $this->db->query('SELECT * FROM projects');
        foreach($result as $row) {

            $fields = [];
            $entry = Entry::find()->section('projects')->where([
                'content.field_projectNumber' => $row['project_num'],
            ])->one();

            if (!$entry) {
                $entry = $this->makeNewEntry('projects');
            }

            // Find Service IDs
            $service_ids = [];
            foreach (explode(',', $row['services']) as $category_title) {
                $category = $this->getCategory('services', trim($category_title));
                if ($category) {
                    $service_ids[] = $category->id;
                }
            }

            // Find Market IDs
            $market_ids = [];
            foreach (explode(',', $row['market']) as $category_title) {
                $category = $this->getCategory('markets', trim($category_title));
                if ($category) {
                    $market_ids[] = $category->id;
                }
            }

            // Find Award IDs from var populated in importAwards()
            $award_ids = [];
            foreach($this->awards_cache as $award) {
                if ($award['project_num'] == $row['project_num']) {
                    $award_ids[] = $award['id'];
                }
            }

            // Find Project Leaders (matrix field)
            $project_leaders = [];
            $rel_result = $this->db->prepare('SELECT * FROM project_leaders WHERE project_num = ?');
            $rel_result->execute([ $row['project_num'] ]);
            $rel_rows = $rel_result->fetchAll();
            $i = 0;
            foreach($rel_rows as $rel_row) {
                $i++;
                $person = Entry::find()->section('people')->where([
                    'content.field_personEmployeeNumber' => $rel_row['employee_num'],
                ])->one();
                if ($person) {
                    $project_leaders['new'.$i] = [
                        'type' => 'projectLeader',
                        'fields' => [
                            'person' => [$person->id],
                            'leaderTitle' => $rel_row['project_role'],
                        ]
                    ];
                }
            }

            // Find Project Partners (matrix field)
            $project_partners = [];
            $rel_result = $this->db->prepare('SELECT * FROM project_partners WHERE project_num = ?');
            $rel_result->execute([ $row['project_num'] ]);
            $rel_rows = $rel_result->fetchAll();
            $i = 0;
            foreach($rel_rows as $rel_row) {
                $i++;
                $project_partners['new'.$i] = [
                    'type' => 'projectPartner',
                    'fields' => [
                        'partnerName' => $rel_row['partner'],
                        'partnerRole' => $rel_row['role'],
                    ]
                ];
            }

            $fields = array_merge([
                'projectNumber'     => $row['project_num'],
                'projectName'       => $row['name'],
                'projectClientName' => $row['client'],
                'projectTagline'    => $row['tagline'],
                'featured'          => $row['isfeatured'],
                'projectLocation'   => $row['location'],
                'projectLeedStatus' => $row['leed_status'],
                'services'          => $service_ids,
                'markets'           => $market_ids,
                'projectAwards'     => $award_ids,
                'projectLeaders'    => $project_leaders,
                'projectPartners'   => $project_partners,

            ], $fields);
            $entry->setFieldValues($fields);

            if(Craft::$app->elements->saveElement($entry)) {
                $this->log .= '<h3>Project '.$entry->title.' Saved OK!</h3>';
            } else {
                $this->log .= '<p>Project '.$entry->title.' save error: '.print_r($entry->getErrors(), true).'</p>';
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
     * Init a new Entry with type attributes
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
     * Get category of entry
     * @param string               $category_group_handle category group handle
     * @param string               $category_title        category title
     */
    private function getCategory(string $category_group_handle, string $category_title)
    {
        if (empty($category_title) || empty($category_group_handle)) return;
        $category_group = Craft::$app->categories->getGroupByHandle($category_group_handle);
        $category = Category::find()->where([
            'title' => $category_title,
            'groupId' => $category_group->id,
        ])->one();
        return $category;
    }
}
