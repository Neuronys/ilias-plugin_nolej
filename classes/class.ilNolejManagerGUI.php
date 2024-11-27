<?php

/**
 * This file is part of Nolej Repository Object Plugin for ILIAS,
 * developed by OC Open Consulting to integrate ILIAS with Nolej
 * software by Neuronys.
 *
 * @author Vincenzo Padula <vincenzo@oc-group.eu>
 * @copyright 2024 OC Open Consulting SB Srl
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use ILIAS\UI\Component\Listing\Workflow\Step;

/**
 * GUI to manage every step of the Nolej module creation.
 *
 * @ilCtrl_isCalledBy ilNolejManagerGUI: ilObjPluginDispatchGUI, ilObjNolejGUI
 * @ilCtrl_Calls ilNolejManagerGUI: ilNolejCreationFormGUI
 * @ilCtrl_Calls ilNolejManagerGUI: ilNolejTranscriptionFormGUI
 * @ilCtrl_Calls ilNolejManagerGUI: ilNolejConceptsFormGUI, ilNolejQuestionsFormGUI, ilNolejSummaryFormGUI
 * @ilCtrl_Calls ilNolejManagerGUI: ilNolejActivitiesFormGUI
 * @ilCtrl_Calls ilNolejManagerGUI: ilNolejH5PIntegrationGUI
 */
class ilNolejManagerGUI
{
    /** @var string */
    public const CMD_CHECK_UPDATES = "checkUpdates";

    /** @var string */
    public const CMD_WEBHOOK_CALL = "webhookCall";

    /** @var int */
    public const STATUS_CREATION = 0;

    /** @var int */
    public const STATUS_CREATION_PENDING = 1;

    /** @var int */
    public const STATUS_ANALYSIS = 2;

    /** @var int */
    public const STATUS_ANALYSIS_PENDING = 3;

    /** @var int */
    public const STATUS_REVISION = 4;

    /** @var int */
    public const STATUS_REVISION_PENDING = 5;

    /** @var int */
    public const STATUS_ACTIVITIES = 6;

    /** @var int */
    public const STATUS_ACTIVITIES_PENDING = 7;

    /** @var int */
    public const STATUS_COMPLETED = 8;

    /** @var int */
    public const STATUS_FAILED = 8;

    /** @var int */
    public const MAX_ATTEMPTS = 2;

    /** @var ilCtrl */
    protected ilCtrl $ctrl;

    /** @var ilTabsGUI */
    protected ilTabsGUI $tabs;

    /** @var ilDBInterface */
    protected ilDBInterface $db;

    /** @var ilLanguage */
    protected ilLanguage $lng;

    /** @var ilGlobalPageTemplate */
    protected ilGlobalPageTemplate $tpl;

    /** @var ilObjNolejGUI */
    protected $obj_gui;

    /** @var ilNolejPlugin */
    protected $plugin;

    /** @var string */
    protected string $defaultClass = "";

    /** @var int */
    public int $status = 0;

    /** @var string */
    public string $documentId = "";

    /** @var string */
    public string $dataDir = "";

    /**
     * @param ilObjNolejGUI $obj_gui
     * @param string|null $documentId
     */
    public function __construct($obj_gui)
    {
        global $DIC;
        $this->ctrl = $DIC->ctrl();
        $this->tabs = $DIC->tabs();
        $this->db = $DIC->database();
        $this->lng = $DIC->language();
        $this->tpl = $DIC->ui()->mainTemplate();

        $this->plugin = ilNolejPlugin::getInstance();

        $this->lng->loadLanguageModule(ilNolejPlugin::PREFIX);

        $this->obj_gui = $obj_gui;
        $this->documentId = $this->obj_gui->getObject()->getDocumentId();
        $this->dataDir = $this->obj_gui->getObject()->getDataDir();
        $this->statusCheck();

        require_once ilNolejPlugin::PLUGIN_DIR . "/classes/class.ilNolejAPI.php";
        require_once ilNolejPlugin::PLUGIN_DIR . "/classes/class.ilNolejWebhook.php";
    }

    /**
     * Get the obj_id from the document_id
     * @param string $documentId
     * @return int
     */
    public static function getObjIdFromDocumentId($documentId)
    {
        global $DIC;

        $db = $DIC->database();

        $result = $db->queryF(
            "SELECT id FROM " . ilNolejPlugin::TABLE_DATA . " WHERE document_id = %s",
            ["text"],
            [$documentId]
        );
        if ($row = $db->fetchAssoc($result)) {
            return (int) $row["id"];
        }
        return -1;
    }

    /**
     * Get the management instance by document id.
     * @param string $documentId
     * @return self
     */
    public static function getInstanceByDocumentId($documentId)
    {
        $objId = self::getObjIdFromDocumentId($documentId);
        $refId = reset(ilObject::_getAllReferences($objId));
        $obj_gui = new ilObjNolejGUI($refId);
        return new self($obj_gui);
    }

    /**
     * Handles all commmands, $cmd = functionName()
     * @return void
     */
    public function executeCommand(): void
    {
        // Check API key.
        if (!ilNolejAPI::hasKey()) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_api_key_missing"));
            return;
        }

        $this->tpl->setTitle($this->obj_gui->getObject()->getTitle(), false);
        $this->tpl->setDescription($this->obj_gui->getObject()->getDescription());

        $next_class = $this->ctrl->getNextClass();

        switch ($next_class) {
            case strtolower(ilNolejCreationFormGUI::class):
                $formGui = new ilNolejCreationFormGUI($this);
                $this->ctrl->forwardCommand($formGui);
                break;

            case strtolower(ilNolejTranscriptionFormGUI::class):
                $formGui = new ilNolejTranscriptionFormGUI($this);
                $this->ctrl->forwardCommand($formGui);
                break;

            case strtolower(ilNolejConceptsFormGUI::class):
                $formGui = new ilNolejConceptsFormGUI($this);
                $this->ctrl->forwardCommand($formGui);
                break;

            case strtolower(ilNolejQuestionsFormGUI::class):
                $formGui = new ilNolejQuestionsFormGUI($this);
                $this->ctrl->forwardCommand($formGui);
                break;

            case strtolower(ilNolejSummaryFormGUI::class):
                $formGui = new ilNolejSummaryFormGUI($this);
                $this->ctrl->forwardCommand($formGui);
                break;

            case strtolower(ilNolejActivitiesFormGUI::class):
                $formGui = new ilNolejActivitiesFormGUI($this);
                $this->ctrl->forwardCommand($formGui);
                break;

            default:
                $cmd = $this->ctrl->getCmd();
                switch ($cmd) {
                    case self::CMD_CHECK_UPDATES:
                    case self::CMD_WEBHOOK_CALL:
                        $this->$cmd();
                        break;

                    default:
                        $this->ctrl->redirectByClass($this->defaultClass);
                }
        }
    }

    /**
     * Get status and set default cmd.
     * @return void
     */
    protected function statusCheck()
    {
        $this->status = $this->obj_gui->getObject()->getDocumentStatus();

        switch ($this->status) {
            case self::STATUS_CREATION:
            case self::STATUS_CREATION_PENDING:
                $this->defaultClass = ilNolejCreationFormGUI::class;
                break;

            case self::STATUS_ANALYSIS:
            case self::STATUS_ANALYSIS_PENDING:
                $this->defaultClass = ilNolejTranscriptionFormGUI::class;
                break;

            case self::STATUS_REVISION:
            case self::STATUS_REVISION_PENDING:
            case self::STATUS_COMPLETED:
                $this->defaultClass = ilNolejConceptsFormGUI::class;
                break;

            case self::STATUS_ACTIVITIES:
            case self::STATUS_ACTIVITIES_PENDING:
                $this->defaultClass = ilNolejActivitiesFormGUI::class;
                break;
        }
    }

    /**
     * Get the current ObjectGUI.
     * @return ilObjNolejGUI
     */
    public function getObjectGUI()
    {
        return $this->obj_gui;
    }

    /**
     * Check if a status is considered "pending".
     * @param int $status
     * @return bool
     */
    public static function isStatusPending($status)
    {
        return in_array(
            $status,
            [
                self::STATUS_CREATION_PENDING,
                self::STATUS_ANALYSIS_PENDING,
                self::STATUS_ACTIVITIES_PENDING,
            ]
        );
    }

    /**
     * Call last webhook and update status.
     * @return void
     */
    protected function webhookCall(): void
    {
        if (!$this->isStatusPending($this->status)) {
            $this->ctrl->redirectByClass($this->defaultClass, ilNolejFormGUI::CMD_SHOW);
            return;
        }

        $api = new ilNolejAPI();
        $result = $api->get(
            "/documents/{$this->documentId}/lastwebhook",
            "",
            false,
            false
        );

        $data = json_decode($result, true);

        $webhook = new ilNolejWebhook();
        $webhook->parse($data);
        $this->ctrl->redirectByClass($this->defaultClass, ilNolejFormGUI::CMD_SHOW);
    }

    /**
     * Get the activity management workflow, follows current status.
     * @return \ILIAS\UI\Component\Listing\Workflow\Linear
     */
    public function getWorkflow()
    {
        global $DIC;

        $workflow = $DIC->ui()->factory()->listing()->workflow();

        ilYuiUtil::initConnection($this->tpl);
        $this->tpl->addCss(ilNolejPlugin::PLUGIN_DIR . "/css/nolej.css");

        if ($this->isStatusPending($this->status)) {
            $this->tpl->addJavaScript(ilNolejPlugin::PLUGIN_DIR . "/js/updates.js");
            $interval = (int) $this->plugin->getConfig("interval", "1");

            $this->ctrl->setParameter($this->obj_gui, "ref_id", $this->obj_gui->getObject()->getRefId());
            $redirectToUrl = $this->ctrl->getLinkTarget($this->status == self::STATUS_ACTIVITIES_PENDING ? $this->obj_gui : $this);

            $this->ctrl->setParameter($this, "document_id", $this->documentId);
            $this->ctrl->setParameter($this, "status", $this->status);
            $updateUrl = $this->ctrl->getLinkTarget($this, self::CMD_CHECK_UPDATES);

            $this->tpl->addOnLoadCode("xnlj_check_updates('{$updateUrl}', '{$redirectToUrl}', {$interval});");
        }

        $steps = [
            $workflow->step(
                $this->plugin->txt("tab_creation"),
                $this->status == self::STATUS_CREATION_PENDING
                    ? self::glyphicon("refresh gly-spin") . $this->plugin->txt("action_transcription")
                    : "",
                $this->ctrl->getLinkTargetByClass([self::class, ilNolejCreationFormGUI::class], ilNolejCreationFormGUI::CMD_SHOW)
            )
                ->withAvailability(Step::AVAILABLE)
                ->withStatus(
                    $this->status <= self::STATUS_CREATION_PENDING
                        ? Step::NOT_STARTED
                        : Step::SUCCESSFULLY
                ),
            $workflow->step(
                $this->plugin->txt("tab_analysis"),
                $this->status == self::STATUS_ANALYSIS_PENDING
                    ? self::glyphicon("refresh gly-spin") . $this->plugin->txt("action_analysis")
                    : "",
                $this->ctrl->getLinkTargetByClass([self::class, ilNolejTranscriptionFormGUI::class], ilNolejTranscriptionFormGUI::CMD_SHOW)
            )
                ->withAvailability(
                    $this->status < self::STATUS_ANALYSIS
                        ? Step::NOT_AVAILABLE
                        : Step::AVAILABLE
                )
                ->withStatus(
                    $this->status <= self::STATUS_ANALYSIS
                        ? Step::NOT_STARTED
                        : ($this->status == self::STATUS_ANALYSIS_PENDING ? Step::IN_PROGRESS : Step::SUCCESSFULLY)
                ),
            $workflow->step(
                $this->plugin->txt("review_concepts"),
                "",
                $this->ctrl->getLinkTargetByClass([self::class, ilNolejConceptsFormGUI::class], ilNolejConceptsFormGUI::CMD_SHOW)
            )
                ->withAvailability(
                    $this->status < self::STATUS_REVISION
                        ? Step::NOT_AVAILABLE
                        : Step::AVAILABLE
                )
                ->withStatus(
                    $this->status <= self::STATUS_REVISION
                        ? Step::NOT_STARTED
                        : Step::IN_PROGRESS
                ),
            $workflow->step(
                $this->plugin->txt("review_questions"),
                "",
                $this->ctrl->getLinkTargetByClass([self::class, ilNolejQuestionsFormGUI::class], ilNolejQuestionsFormGUI::CMD_SHOW)
            )
                ->withAvailability(
                    $this->status < self::STATUS_REVISION
                        ? Step::NOT_AVAILABLE
                        : Step::AVAILABLE
                )
                ->withStatus(
                    $this->status <= self::STATUS_REVISION
                        ? Step::NOT_STARTED
                        : Step::IN_PROGRESS
                ),
            $workflow->step(
                $this->plugin->txt("review_summary"),
                "",
                $this->ctrl->getLinkTargetByClass([self::class, ilNolejSummaryFormGUI::class], ilNolejSummaryFormGUI::CMD_SHOW)
            )
                ->withAvailability(
                    $this->status < self::STATUS_REVISION
                        ? Step::NOT_AVAILABLE
                        : Step::AVAILABLE
                )
                ->withStatus(
                    $this->status <= self::STATUS_REVISION
                        ? Step::NOT_STARTED
                        : Step::IN_PROGRESS
                ),
            $workflow->step(
                $this->plugin->txt("tab_activities"),
                $this->status == self::STATUS_ACTIVITIES_PENDING
                    ? self::glyphicon("refresh gly-spin") . $this->plugin->txt("action_activities")
                    : "",
                $this->ctrl->getLinkTargetByClass([self::class, ilNolejActivitiesFormGUI::class], ilNolejFormGUI::CMD_SHOW)
            )
                ->withAvailability(
                    $this->status < self::STATUS_REVISION
                        ? Step::NOT_AVAILABLE
                        : Step::AVAILABLE
                )
                ->withStatus(
                    $this->status <= self::STATUS_REVISION
                        ? Step::NOT_STARTED
                        : Step::IN_PROGRESS
                ),
        ];

        return $workflow->linear($this->plugin->txt("tab_activity_management"), $steps);
    }

    /**
     * Return a glyphicon html.
     * @param string $id
     * @return string
     */
    public static function glyphicon($id)
    {
        return "<span class=\"glyphicon glyphicon-{$id}\" aria-hidden=\"true\"></span> ";
    }

    /**
     * Check for document updates; called via ajax request.
     * Requires in GET the document ID and the status to be checked.
     *
     * Prints "reload" if there is an update; prints nothing otherwise.
     */
    protected function checkUpdates(): void
    {
        // Required params.
        if (!isset($_GET["document_id"], $_GET["status"])) {
            exit(0);
        }

        $documentId = $_GET["document_id"];
        $currentStatus = $_GET["status"];

        // Fetch document data.
        $set = $this->db->queryF(
            "SELECT `status` FROM " . ilNolejPlugin::TABLE_DOC . " WHERE document_id = %s;",
            ["text"],
            [$documentId]
        );

        $row = $this->db->fetchAssoc($set);
        if (!$row) {
            // Document not found.
            exit(0);
        }

        if ($row["status"] != $currentStatus) {
            // Document updated.
            echo "update";
        }

        exit(0);
    }

    /**
     * Read a file of the current document, if exists.
     *
     * @param string $filename the name of the file.
     * @return string|false return the content if the file exists,
     *   false otherwise.
     */
    public function hasDocumentFile($filename)
    {
        return file_exists($this->dataDir . "/" . $filename);
    }

    /**
     * Read a file of the current document, if exists.
     *
     * @param string $filename the name of the file.
     * @return string|false return the content if the file exists,
     *   false otherwise.
     */
    public function readDocumentFile($filename)
    {
        return file_get_contents($this->dataDir . "/" . $filename);
    }

    /**
     * Write a file of the current document, and create the
     * parent directory if it doesn't exists.
     *
     * @param string $filename the name of the file.
     * @param string $content the content of the file.
     *
     * @return bool returns true on success, false on failure.
     */
    public function writeDocumentFile($filename, $content)
    {
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0775, true);
        }

        return file_put_contents(
            $this->dataDir . "/" . $filename,
            $content
        ) !== false;
    }

    /**
     * Get and save the content of a Nolej file.
     *
     * @param string $pathname the "id" of Nolej file
     * @param string|null $saveAs the name of the file to be saved as
     * @param bool $forceDownload if false check if the file already exists
     * @param mixed $withData
     * @param bool $encode input's data
     *
     * @return bool|string return true on success, false on failure. If $saveAs
     * is null, then the content is returned as string.
     */
    public function getNolejContent(
        $pathname,
        $saveAs = null,
        $forceDownload = false,
        $withData = "",
        $encode = false
    ) {
        $filepath = $this->dataDir . "/" . $saveAs;
        $api = new ilNolejAPI();

        if (
            $saveAs != null &&
            !$forceDownload &&
            is_file($filepath)
        ) {
            return true;
        }

        $result = $api->get(
            "/documents/{$this->documentId}/{$pathname}",
            $withData,
            $encode,
            false
        );

        return $saveAs == null
            ? $result
            : $this->writeDocumentFile($saveAs, $result);
    }

    /**
     * Put the content of a file to Nolej
     *
     * @param string $pathname the "id" of Nolej file
     * @param string $filename the name of the file on disk
     *
     * @return bool true on success, false on failure
     */
    public function putNolejContent($pathname, $filename)
    {
        $content = $this->readDocumentFile($filename);
        if (!$content) {
            return false;
        }

        $api = new ilNolejAPI();

        $result = $api->put(
            "/documents/{$this->documentId}/{$pathname}",
            $content
        );
        return true;
    }

    /**
     * Update the status of the document
     *
     * @param int $newStatus
     * @return void
     */
    public function updateDocumentStatus($newStatus)
    {
        $this->db->manipulateF(
            "UPDATE " . ilNolejPlugin::TABLE_DOC
            . " SET status = %s WHERE document_id = %s;",
            ["integer", "text"],
            [$newStatus, $this->documentId]
        );
        $this->statusCheck();
    }

    /**
     * Download and import H5P activities
     * @return string failed imports.
     */
    public function downloadActivities()
    {
        if (!ilNolejH5PIntegrationGUI::isH5PInstalled()) {
            return $this->plugin->txt("err_h5p_not_installed");
        }

        $h5pDir = $this->dataDir . "/h5p";
        if (!is_dir($h5pDir)) {
            mkdir($h5pDir, 0775, true);
        }

        // Delete previouses h5p files.
        $dirIterator = new DirectoryIterator($h5pDir);
        foreach ($dirIterator as $item) {
            if (!$item->isDot() && $item->isFile()) {
                unlink($item->getPathname());
            }
        }

        $json = $this->getNolejContent(
            "activities",
            null,
            true,
            ["format" => "h5p"],
            true
        );
        if (!$json) {
            return $this->plugin->txt("err_json_decode");
        }
        $activities = json_decode($json);
        $errorMessages = [];

        $now = strtotime("now");
        foreach ($activities->activities as $activity) {
            $path = "{$h5pDir}/{$activity->activity_name}.h5p";

            $errorMessage = $this->downloadAndImportH5PContent($path, $activity->url, $h5pDir, $activity->activity_name, $now, self::MAX_ATTEMPTS);
            if (!empty($errorMessage)) {
                $errorMessages[] = "{$activity->activity_name} ({$errorMessage})";
            }
        }

        return implode(", ", $errorMessages);
    }

    /**
     * Download the h5p file and try to import it. In case of failure, try again
     * until there are no more attempts left.
     *
     * @param string $path to save the activity
     * @param string $url of the activity
     * @param string $h5pDir directory where are located h5p activities
     * @param string $type of h5p activity to import
     * @param int $time
     * @param int $attemptsLeft
     * @return string error message. Empty string if succedeed.
     */
    protected function downloadAndImportH5PContent($path, $url, $h5pDir, $type, $time, $attemptsLeft)
    {
        if ($attemptsLeft != self::MAX_ATTEMPTS) {
            $this->plugin->log("Retrying to import H5P activity {$type} of document {$this->documentId}...");
        }

        $attemptsLeft -= 1;

        // Download activity.
        file_put_contents(
            $path,
            file_get_contents($url)
        );

        $errorMessage = $this->importH5PContent($h5pDir, $type, $time);
        if (!empty($errorMessage)) {
            return $attemptsLeft == 0
                ? $errorMessage
                : $this->downloadAndImportH5PContent($path, $url, $h5pDir, $type, $time, $attemptsLeft);
        }

        return "";
    }

    /**
     * @param string $h5pDir directory where are located h5p activities
     * @param string $type of h5p activity to import
     * @param int $time
     * @return string error message. Empty string if succedeed.
     */
    protected function importH5PContent($h5pDir, $type, $time)
    {
        $this->plugin->log("Importing H5P activity {$type} of document {$this->documentId}...");

        $filepath = substr("{$h5pDir}/{$type}.h5p", 1);

        if (!ilNolejH5PIntegrationGUI::isH5PInstalled()) {
            return $this->plugin->txt("err_h5p_not_installed");
        }

        $h5pIntegrationGui = new ilNolejH5PIntegrationGUI($this->obj_gui);

        try {
            $contentId = $h5pIntegrationGui->importFromPath($filepath, $type);
        } catch (Exception $e) {
            $this->plugin->log("Import error for {$type} of document {$this->documentId}: {$e->getMessage()}");
            return $this->plugin->txt("err_h5p_package");
        }

        if ($contentId == -1) {
            $this->plugin->log("Import failed {$type} of document {$this->documentId}");
            return $this->plugin->txt("err_h5p_package");
        }

        if (null === $contentId) {
            $this->plugin->log("Import failed {$type} of document {$this->documentId}");
            return $this->plugin->txt("err_content_id");
        }

        $this->db->manipulateF(
            "INSERT INTO " . ilNolejPlugin::TABLE_H5P
            . " (document_id, type, `generated`, content_id)"
            . " VALUES (%s, %s, %s, %s);",
            ["text", "text", "integer", "integer"],
            [$this->documentId, $type, $time, $contentId]
        );

        $this->plugin->log("Import completed {$type} of document {$this->documentId}");
        return "";
    }
}
