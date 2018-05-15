<?php
/**
 * AEI plugin for Craft CMS 3.x
 *
 * Firebelly plugin for custom AEI functionality
 *
 * @link      https://www.firebellydesign.com/
 * @copyright Copyright (c) 2018 Firebelly Design
 */

namespace firebelly\aei\services;

use firebelly\aei\AEI;
use firebelly\aei\base\SectionImport;
use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\elements\Category;
use craft\records\EntryType;
use craft\helpers\DateTimeHelper;
use craft\mail\Message;
use verbb\supertable\SuperTable;

/**
 * DeltekImport Service
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
class DeltekImport extends Component
{
    private $deltekDb = null;
    private $log = '';
    private $summary = [];
    private $awards_cache = [];

    /**
     * Run Deltek Import
     *
     * AEI::$plugin->deltekImport->importRecords()
     *
     * @return string
     */
    public function importRecords($sections_to_import)
    {
        if (empty($sections_to_import)) {
            return (object) [
                'log' => 'Nothing done.',
                'summary' => 'No sections selected to import.',
            ];
        }

        // $this->bomb('fail ouch!');

        // Connect to Deltek db
        try {
            $this->deltekDb = new \PDO('mysql:host='.getenv('DELTEK_DB_SERVER').';dbname='.getenv('DELTEK_DB_DATABASE').';charset=utf8', getenv('DELTEK_DB_USER'), getenv('DELTEK_DB_PASSWORD'));
            $this->deltekDb->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch(\PDOException $e) {
            $this->bomb('PDO Error: ' . $e->getMessage());
        }

        // Import all sections specified in $sections_to_import array
        try {
            if (in_array('offices', $sections_to_import)) {
                $this->importOffices();
            }
            if (in_array('people', $sections_to_import)) {
                $this->importPeople();
            }
            if (in_array('awards', $sections_to_import)) {
                $this->importAwards();
            }
            if (in_array('projects', $sections_to_import)) {
                $this->importProjects();
            }
        } catch (\Exception $e) {
            $this->bomb('Import Error: ' . $e->getMessage());
        }

        return (object) [
            'log'     => $this->log,
            'summary' => implode(', ', $this->summary),
        ];
    }

    /**
     * Import Offices
     */
    private function importOffices() {
        $officesImport = new SectionImport('Offices');

        $result = $this->deltekDb->query("SELECT * FROM offices");
        foreach($result as $row) {
            $fields = [];
            $actionVerb = 'updated';
            $entry = Entry::find()->section('offices')->where([
                'title' => $row['office_name']
            ])->one();

            if (!$entry) {
                $entry = $this->makeNewEntry('offices');
                $entry->title = $row['office_name'];
                $actionVerb = 'added';
            }

            // Get our Super Table field
            $field = Craft::$app->fields->getFieldByHandle('quotes');
            $blockTypes = SuperTable::$plugin->service->getBlockTypesByFieldId($field->id);
            $blockType = $blockTypes[0];

            // Find Office Quotes
            $office_quotes = [];
            $i = 0;

            $rel_result = $this->deltekDb->prepare('SELECT * FROM office_quotes WHERE office_name = ?');
            $rel_result->execute([ $row['office_name'] ]);
            $rel_rows = $rel_result->fetchAll();
            foreach($rel_rows as $rel_row) {
                $i++;
                if (!empty($row['employee_num'])) {
                    $person = Entry::find()->section('people')->where([
                        'content.field_employeeNum' => $row['employee_num']
                    ])->one();
                    $aeiPerson = ($person) ? [$person->id] : [];
                } else {
                    $aeiPerson = [];
                }
                $office_quotes['new'.$i] = [
                    'type' => $blockType->id,
                    'fields' => [
                        'quote'         => $rel_row['quote'],
                        'personName'    => '', // no override field in office_quotes table for this
                        'personCompany' => $rel_row['employee_title'],
                        'quoteKey'      => $rel_row['quote_key'],
                        'aeiPerson'     => $aeiPerson,
                    ]
                ];
            }

            $fields = array_merge([
                'officeAddress1'   => $row['address1'],
                'officeAddress2'   => $row['address2'],
                'officeCity'       => $row['city'],
                'officeState'      => $row['state'],
                'officePostalCode' => $row['postal_code'],
                'officeCountry'    => $row['country'],
                'phoneNumber'      => $row['phone'],
                'description'      => $row['overview'],
                'officeMapUrl'     => $row['map_url'],
                'quotes'           => $office_quotes,
            ], $fields);
            $entry->setFieldValues($fields);

            if(Craft::$app->elements->saveElement($entry)) {
                $officesImport->saved($entry, $actionVerb);
            } else {
                $this->bomb('<li>Save error: '.print_r($entry->getErrors(), true).'</li>');
            }
        }
        list($log, $summary) = $officesImport->finish();
        $this->log .= $log;
        $this->summary = array_merge($summary, $this->summary);
    }

    /**
     * Import People
     */
    private function importPeople() {
        $peopleImport = new SectionImport('People');

        $result = $this->deltekDb->query("SELECT * FROM employees");
        foreach($result as $row) {
            $fields = [];
            $actionVerb = 'updated';
            $entry = Entry::find()->section('people')->where([
                'content.field_personEmployeeNumber' => $row['employee_num']
            ])->one();

            if (!$entry) {
                $entry = $this->makeNewEntry('person');
                $actionVerb = 'added';
            }

            // Find Office
            $office = Entry::find()->section('offices')->where([
                'title' => $row['officename']
            ])->one();
            $office_ids = $office ? [$office->id] : [];

            // Find Person Quote
            $rel_result = $this->deltekDb->prepare('SELECT * FROM employee_quotes WHERE employee_num = ?');
            $rel_result->execute([ $row['employee_num'] ]);
            $rel_rows = $rel_result->fetchAll();
            foreach($rel_rows as $rel_row) {
                // Remove quotes around text
                $fields['personQuote'] = trim(str_replace('&nbsp;', ' ', $rel_row['quote']), ' "”“');
            }

            // Find People Type IDs
            $person_type_ids = [];
            foreach (explode(',', $row['primary_category']) as $category_title) {
                if ($category = $this->getCategory('peopleTypes', trim($category_title))) {
                    $person_type_ids[] = $category->id;
                }
            }

            // Find Secondary People Type IDs
            $secondary_person_type_ids = [];
            foreach (explode(',', $row['primary_category']) as $category_title) {
                if ($category = $this->getCategory('peopleTypes', trim($category_title))) {
                    $secondary_person_type_ids[] = $category->id;
                }
            }

            // Populate Social Links (matrix field), currently just Linkedin
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
                'featured'             => $row['is_featured'],
                'office'               => $office_ids,
                'personType'           => $person_type_ids,
                'secondaryPersonType'  => $secondary_person_type_ids,
                'socialLinks'          => $social_links,
            ], $fields);
            $entry->setFieldValues($fields);

            if(Craft::$app->elements->saveElement($entry)) {
                $peopleImport->saved($entry, $actionVerb);
            } else {
                $this->bomb('<li>Save error: '.print_r($entry->getErrors(), true).'</li>');
            }
        }
        list($log, $summary) = $peopleImport->finish();
        $this->log .= $log;
        $this->summary = array_merge($summary, $this->summary);
    }

    /**
     * Import Awards
     */
    private function importAwards() {
        $awardsImport = new SectionImport('Awards');

        $result = $this->deltekDb->query("SELECT * FROM project_awards");
        foreach($result as $row) {
            $entry = Entry::find()->section('awards')->where([
                'content.field_awardKey' => $row['award_key']
            ])->one();

            $actionVerb = 'updated';
            if (!$entry) {
                $entry = $this->makeNewEntry('awards');
                $entry->title = $row['name'];
                $actionVerb = 'added';
            }

            $entry->setFieldValues([
                'awardDate'   => $row['date'],
                'awardIssuer' => $row['issuer'],
                'awardKey'    => $row['award_key'],
            ]);

            if(Craft::$app->elements->saveElement($entry)) {
                $awardsImport->saved($entry, $actionVerb);
            } else {
                $this->bomb('<li>Save error: '.print_r($entry->getErrors(), true).'</li>');
            }
        }
        list($log, $summary) = $awardsImport->finish();
        $this->log .= $log;
        $this->summary = array_merge($summary, $this->summary);
    }

    /**
     * Import Impact
     */
    private function importImpact() {
        $ImpactImport = new SectionImport('Impact');

        $impact_type = 'Articles';
        $impact_type_id = $this->getImpactType($impact_type);
        $result = $this->deltekDb->query("SELECT * FROM impact_articles");
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
                'description'          => (!empty($row['body']) ? $row['body'] : $row['abstract']),
                'excerpt'              => (!empty($row['body']) ? $row['abstract'] : ''),
                'impactPublication'    => $row['publication'],
                'impactPublicationUrl' => $row['url'],
                'impactType'           => $impact_type,
                'impactAuthor'         => $this->getImpactPeopleMatrix($row['impact_key']),
            ], $fields);
            $entry->setFieldValues($fields);

            if(Craft::$app->elements->saveElement($entry)) {
                $this->log .= '<h3>Impact ('.$impact_type.') '.$row['name'].' Saved OK!</h3>';
                // Set postDate after save in case this is a new post
                $entry->postDate = DateTimeHelper::formatTimeForDb($row['date']);
                Craft::$app->elements->saveElement($entry);
            } else {
                $this->log .= '<p>Impact ('.$impact_type.') '.$row['bombe'].' save error: '.print_r($entry->getErrors(), true).'</p>';
            }
        }
    }

    /**
     * Import Projects
     */
    private function importProjects() {
        $projectsImport = new SectionImport('Projects');
        $result = $this->deltekDb->query('SELECT * FROM projects');
        foreach($result as $row) {

            $fields = [];
            $new_entry = false;
            $entry = Entry::find()->section('projects')->where([
                'content.field_projectNumber' => $row['project_num'],
            ])->one();

            $actionVerb = 'updated';
            if (!$entry) {
                $entry = $this->makeNewEntry('projects');
                $actionVerb = 'added';
            }

            /////////////////////////////////////
            // Add one-time matrix field imports (stats, quotes, images)
            $media_blocks = [];
            $i = 0;
            // todo: find existing matrix blocks that have no deltek_id field?

            // Get our Super Table quotes field (inside mediaBlocks matrix field)
            $mediaBlockField = Craft::$app->fields->getFieldByHandle('mediaBlocks');
            $blockTypes = Craft::$app->matrix->getBlockTypesByFieldId($mediaBlockField->id);
            foreach($blockTypes as $blockType) {
                if ($blockType->handle=='quotes') {
                    $matrixFields = Craft::$app->fields->getFieldsByLayoutId($blockType->fieldLayoutId);
                    $quoteSuperTableBlocks = SuperTable::$plugin->service->getBlockTypesByFieldId($matrixFields[0]->id);
                }
            }
            $blockType = $quoteSuperTableBlocks[0]; // There will only ever be one SuperTable_BlockType

            // todo: error handling above if quote field isn't found?

            // Project Quotes
            $project_quotes = [];
            $rel_result = $this->deltekDb->prepare('SELECT * FROM project_quotes WHERE project_num = ?');
            $rel_result->execute([ $row['project_num'] ]);
            $rel_rows = $rel_result->fetchAll();
            $q = 0;
            foreach($rel_rows as $rel_row) {
                $q++;
                if (!empty($rel_row['employee_num'])) {
                    $person = Entry::find()->section('people')->where([
                        'content.field_personEmployeeNumber' => $rel_row['employee_num']
                    ])->one();
                    $aeiPerson = ($person) ? [$person->id] : [];
                } else {
                    $aeiPerson = [];
                }

                // Reverse lastName, firstName formatting
                $personName = $rel_row['author'];
                $personName = preg_replace('/^([^,]*), (.*)/', '$2 $1', $personName);

                // Make comma-delineated string of company + title
                $companyTitle = implode(',', array_filter([$rel_row['author_company'], $rel_row['author_title']]));

                // Clean up quote
                $quote = trim(str_replace('&nbsp;', ' ', $rel_row['quote']), ' "”“');

                $project_quotes['new'.$q] = [
                    'type' => $blockType->id,
                    'fields' => [
                        'quote'         => $quote,
                        'personName'    => $personName,
                        'personCompany' => $companyTitle,
                        'quoteKey'      => $rel_row['quote_key'],
                        'aeiPerson'     => $aeiPerson,
                    ]
                ];
            }
            if (!empty($project_quotes)) {
                $i++;
                $media_blocks = array_merge(['new'.$i => [
                    'type' => 'quotes',
                    'fields' => [
                        'quotes' => $project_quotes,
                    ]
                ]], $media_blocks);
            }

            // Project Stats
            $project_stats = [];
            $rel_result = $this->deltekDb->prepare('SELECT * FROM project_stats WHERE project_num = ?');
            $rel_result->execute([ $row['project_num'] ]);
            $rel_rows = $rel_result->fetchAll();
            foreach($rel_rows as $rel_row) {
                $i++;
                $project_stats['new'.$i] = [
                    'type' => 'stat',
                    'fields' => [
                        'statFigure' => $rel_row['text'],
                        'statLabel' => $rel_row['subtext'],
                    ]
                ];
            }
            $media_blocks = array_merge($project_stats, $media_blocks);

            // todo: add case_study as text block
            // todo: pull images as image blocks
            /////////////////////////////////////

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
            $markets = implode(',', array_filter([
                $row['primary_market'],
                $row['secondary_market'],
                $row['tertiary_market'],
            ]));
            foreach (explode(',', $markets) as $category_title) {
                $category = $this->getCategory('markets', trim($category_title));
                if ($category) {
                    $market_ids[] = $category->id;
                }
            }

            // Find Project Awards
            $award_ids = [];
            $rel_result = $this->deltekDb->prepare("SELECT * FROM project_awards WHERE project_num = ?");
            $rel_result->execute([ $row['project_num'] ]);
            $rel_rows = $rel_result->fetchAll();
            foreach($rel_rows as $rel_row) {
                // See if this award is imported already
                $award = Entry::find()->section('awards')->where([
                    'content.field_awardKey' => $rel_row['award_key'],
                ])->one();
                if ($award) {
                    $award_ids[] = $award->id;
                }
            }

            // Find Project Leaders (matrix field)
            $project_leaders = [];
            $rel_result = $this->deltekDb->prepare('SELECT * FROM project_leaders WHERE project_num = ?');
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
                            'aeiPerson'   => [$person->id],
                            'leaderTitle' => $rel_row['project_role'],
                        ]
                    ];
                }
            }

            // Find Project Partners (matrix field)
            $project_partners = [];
            $rel_result = $this->deltekDb->prepare('SELECT * FROM project_partners WHERE project_num = ?');
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
                'featured'          => $row['is_featured'],
                'projectLocation'   => $row['location'],
                'projectLeedStatus' => $row['leed_status'],
                'services'          => $service_ids,
                'markets'           => $market_ids,
                'projectAwards'     => $award_ids,
                'projectLeaders'    => $project_leaders,
                'projectPartners'   => $project_partners,
                'mediaBlocks' => $media_blocks
            ], $fields);
            $entry->setFieldValues($fields);

            if(Craft::$app->elements->saveElement($entry)) {
                $projectsImport->saved($entry, $actionVerb);
            } else {
                $this->bomb('<li>Save error: '.print_r($entry->getErrors(), true).'</li>');
            }
        }
        list($log, $summary) = $projectsImport->finish();
        $this->log .= $log;
        $this->summary = array_merge($summary, $this->summary);
    }

    /**
     * Slugify a string (now unused)
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
     * Find People Matrix for Impact post
     * @param  string $impact_key Deltek ID of Impact post
     * @return array              People IDs
     */
    private function getImpactPeopleMatrix(string $impact_key)
    {
        $impact_people = [];
        $i = 0;
        $rel_result = $this->deltekDb->prepare('SELECT * FROM impact_authorship WHERE impact_key = ? LIMIT 1');
        $rel_result->execute([ $impact_key ]);
        $rel_rows = $rel_result->fetchAll();
        foreach($rel_rows as $rel_row) {
            if (!empty($row['employee_num'])) {
                $person = Entry::find()->section('people')->where([
                    'content.field_employeeNum' => $row['employee_num']
                ])->one();
                $aeiPerson = ($person) ? [$person->id] : [];
            } else {
                $aeiPerson = [];
            }
            $impact_people['new'.$i] = [
                'type' => 'person',
                'fields' => [
                    'aeiPerson'     => $aeiPerson,
                    'personName'    => $rel_row['author_name'],
                    'personCompany' => $rel_row['author_company'],
                ]
            ];
        }
        return $impact_people;
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

    function bomb(string $message) {
        Craft::warning($message);
        $this->sendMail($message, 'AEI bomb', 'nate@firebellydesign.com');
        // throw new Exception($message);
    }

    /**
     * Send an email
     * @param $message
     * @param $subject
     * @param $to_email
     * @return bool
     */
    private function sendMail($message, $subject, $to_email): bool
    {
        $settings = Craft::$app->systemSettings->getSettings('email');
        $message = new Message();
        $message->setFrom([$settings['fromEmail'] => $settings['fromName']]);
        $message->setTo($to_email);
        $message->setSubject($subject);
        $message->setHtmlBody($message);

        return Craft::$app->mailer->send($message);
    }
}
