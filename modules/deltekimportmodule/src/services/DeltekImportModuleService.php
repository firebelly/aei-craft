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
use craft\helpers\DateTimeHelper;

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

            // Find Person Quote
            // $person_quotes = [];
            $rel_result = $this->db->prepare('SELECT * FROM employee_quotes WHERE employee_num = ?');
            $rel_result->execute([ $row['employee_num'] ]);
            $rel_rows = $rel_result->fetchAll();
            // Just populating single field for now, but see below if we switch to multiple...
            foreach($rel_rows as $rel_row) {
                $fields['personQuote'] = $rel_row['text'];
            }
            // For multiple quotes into a table field:
            // foreach($rel_rows as $rel_row) {
            //     $person_quotes[] = [
            //         'quote' => $rel_row['quote'],
            //     ];
            // }
            // $fields['personQuotes'] = $person_quotes;

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
     * Import Impact
     */
    private function importImpact() {
        // Articles
        $impact_type = $this->getImpactType('Articles');
        $result = $this->db->query("SELECT * FROM impact_articles");
        foreach($result as $row) {
            $fields = [];
            $entry = Entry::find()->section('impact')->where([
                'content.field_impactKey' => $row['impact_key']
            ])->one();

            if (!$entry) {
                $entry = $this->makeNewEntry('impact');
                $entry->title = $row['title'];
            }

            $fields = array_merge([
                'description'          => (!empty($row['body']) $row['body'] : $row['abstract']),
                'excerpt'              => (!empty($row['body']) $row['abstract'] : ''),
                'impactPublication'    => $row['publication'],
                'impactPublicationUrl' => $row['url'],
                'impactType'           => $impact_type,
                'impactAuthor'         => $this->getImpactPeopleIds($row['impact_key'])
            ], $fields);
            $entry->setFieldValues($fields);

            if(Craft::$app->elements->saveElement($entry)) {
                $this->log .= '<h3>Impact (Article) '.$row['name'].' Saved OK!</h3>';
                // Set postDate after save in case this is a new post
                $entry->postDate = DateTimeHelper::formatTimeForDb($row['date']);
                Craft::$app->elements->saveElement($entry);
            } else {
                $this->log .= '<p>Impact (Article) '.$row['name'].' save error: '.print_r($entry->getErrors(), true).'</p>';
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
            $new_entry = false;
            $entry = Entry::find()->section('projects')->where([
                'content.field_projectNumber' => $row['project_num'],
            ])->one();

            // New project post
            if (!$entry) {
                $entry = $this->makeNewEntry('projects');

                // Add one-time matrix field imports (stats, quotes, images)
                $media_blocks = [];
                $i = 0;

                // Project Quotes
                $project_quotes = [];
                $rel_result = $this->db->prepare('SELECT * FROM project_quotes WHERE project_num = ?');
                $rel_result->execute([ $row['project_num'] ]);
                $rel_rows = $rel_result->fetchAll();
                foreach($rel_rows as $rel_row) {
                    $i++;
                    // todo: pull person based on employee_num
                    // todo: add company + AEI person fields to quote matrix block?
                    $project_quotes['new'.$i] = [
                        'type' => 'quote',
                        'fields' => [
                            'quote' => $rel_row['quote'],
                            'source' => $rel_row['quote_author'],
                        ]
                    ];
                }
                $media_blocks = array_merge($project_quotes, $media_blocks);

                // Project Stats
                $project_stats = [];
                $rel_result = $this->db->prepare('SELECT * FROM project_stats WHERE project_num = ?');
                $rel_result->execute([ $row['project_num'] ]);
                $rel_rows = $rel_result->fetchAll();
                foreach($rel_rows as $rel_row) {
                    $i++;
                    $project_stats['new'.$i] = [
                        'type' => 'stat',
                        'fields' => [
                            'figure' => $rel_row['text'],
                            'label' => $rel_row['subtext'],
                        ]
                    ];
                }
                $media_blocks = array_merge($project_stats, $media_blocks);

                $fields = array_merge([
                    'mediaBlocks' => $media_blocks
                ], $fields);

                // todo: add case_study as text block
                // todo: pull images as image blocks
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
     * Find People IDs for Impact
     * @param  string $impact_key Deltek ID of Impact post
     * @return array              People IDs
     */
    private function getImpactPeopleIds(string $impact_key)
    {
        $peopleIds = [];
        $rel_result = $this->db->prepare('SELECT * FROM impact_authorship WHERE impact_key = ? LIMIT 1');
        $rel_result->execute([ $impact_key ]);
        $rel_rows = $rel_result->fetchAll();
        foreach($rel_rows as $rel_row) {
            $person = Entry::find()->section('people')->where([
                'content.field_employeeNum' => $row['employee_num']
            ])->one();
            if ($person) {
                $peopleIds[] = $person->id;
            }
        }
        $fields['impactAuthor'] = $peopleIds;
        return $peopleIds;
    }

    /**
     * Find Impact Type
     * @param  string $impact_type slug of impact type
     * @return array              People IDs
     */
    private function getImpactType(string $impact_type)
    {
        $return = [];
        $category = $this->getCategory('impactTypes', $impact_type);
        if ($category) {
            $return[] = $category->id;
        }
        return $return;
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

    /**
     * @param UploadedFile $uploadedFile
     * @param int $folderId
     * @return Asset
     * @throws BadRequestHttpException
     * @throws UploadFailedException
     */
    protected static function uploadNewAsset(UploadedFile $uploadedFile, $folderId) {
        if (empty($folderId)) {
            throw new BadRequestHttpException('No target destination provided for uploading');
        }

        if ($uploadedFile === null) {
            throw new BadRequestHttpException('No file was uploaded');
        }

        $assets = Craft::$app->getAssets();

        if ($uploadedFile->getHasError()) {
            throw new UploadFailedException($uploadedFile->error);
        }

        // Move the uploaded file to the temp folder
        if (($tempPath = $uploadedFile->saveAsTempFile()) === false) {
            throw new UploadFailedException(UPLOAD_ERR_CANT_WRITE);
        }

        if (empty($folderId)) {
            throw new BadRequestHttpException('The target destination provided for uploading is not valid');
        }

        $folder = $assets->findFolder(['id' => $folderId]);

        if (!$folder) {
            throw new BadRequestHttpException('The target folder provided for uploading is not valid');
        }

        // Check the permissions to upload in the resolved folder.
        $filename = Assets::prepareAssetName($uploadedFile->name);

        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->filename = $filename;
        $asset->newFolderId = $folder->id;
        $asset->volumeId = $folder->volumeId;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        $result = Craft::$app->getElements()->saveElement($asset);

        return $asset;
    }
}
