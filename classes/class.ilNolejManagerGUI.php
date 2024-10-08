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
 * @ilCtrl_Calls ilObjNolejGUI: ilNolejCreationFormGUI
 * @ilCtrl_Calls ilObjNolejGUI: ilNolejTranscriptionFormGUI
 * @ilCtrl_Calls ilObjNolejGUI: ilNolejConceptsFormGUI, ilNolejQuestionsFormGUI, ilNolejSummaryFormGUI
 * @ilCtrl_Calls ilObjNolejGUI: ilNolejActivitiesFormGUI
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
    public const STATUS_ANALISYS = 2;

    /** @var int */
    public const STATUS_ANALISYS_PENDING = 3;

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

        ilNolejPlugin::includeH5P();

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
                $formGui = new ilNolejCreationFormGUI($this->obj_gui, $this);
                $this->ctrl->forwardCommand($formGui);
                break;

            case strtolower(ilNolejTranscriptionFormGUI::class):
                $formGui = new ilNolejTranscriptionFormGUI($this->obj_gui, $this);
                $this->ctrl->forwardCommand($formGui);
                break;

            case strtolower(ilNolejConceptsFormGUI::class):
                $formGui = new ilNolejConceptsFormGUI($this->obj_gui, $this);
                $this->ctrl->forwardCommand($formGui);
                break;

            case strtolower(ilNolejQuestionsFormGUI::class):
                $formGui = new ilNolejQuestionsFormGUI($this->obj_gui, $this);
                $this->ctrl->forwardCommand($formGui);
                break;

            case strtolower(ilNolejSummaryFormGUI::class):
                $formGui = new ilNolejSummaryFormGUI($this->obj_gui, $this);
                $this->ctrl->forwardCommand($formGui);
                break;

            case strtolower(ilNolejActivitiesFormGUI::class):
                $formGui = new ilNolejActivitiesFormGUI($this->obj_gui, $this);
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
     * Get status and set default cmd
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

            case self::STATUS_ANALISYS:
            case self::STATUS_ANALISYS_PENDING:
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
     * Check if a status is considered "pending".
     * @param int $status
     * @return bool
     */
    protected function isStatusPending($status)
    {
        return in_array(
            $status,
            [
                self::STATUS_CREATION_PENDING,
                self::STATUS_ANALISYS_PENDING,
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
     * Print a caller to the last webhook.
     * @return string
     */
    public function getWebhookCallBox(): string
    {
        global $DIC;
        $factory = $DIC->ui()->factory();
        $renderer = $DIC->ui()->renderer();

        $buttons = [
            $factory->button()->standard(
                $this->plugin->txt("cmd_webhook_call"),
                $this->ctrl->getLinkTarget($this, self::CMD_WEBHOOK_CALL)
            )
        ];

        return $renderer->render(
            $factory->messageBox()
                ->confirmation($this->plugin->txt("cmd_webhook_call_info"))
                ->withButtons($buttons)
        );
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
        $this->tpl->addJavaScript(ilNolejPlugin::PLUGIN_DIR . "/js/nolej.js");

        if ($this->isStatusPending($this->status)) {
            $this->ctrl->setParameter($this, "document_id", $this->documentId);
            $this->ctrl->setParameter($this, "status", $this->status);
            $updateUrl = $this->ctrl->getLinkTarget($this, self::CMD_CHECK_UPDATES);
            $this->tpl->addOnLoadCode("xnlj_check_updates('{$updateUrl}')");
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
                $this->status == self::STATUS_ANALISYS_PENDING
                    ? self::glyphicon("refresh gly-spin") . $this->plugin->txt("action_analysis")
                    : "",
                $this->ctrl->getLinkTargetByClass([self::class, ilNolejTranscriptionFormGUI::class], ilNolejTranscriptionFormGUI::CMD_SHOW)
            )
                ->withAvailability(
                    $this->status < self::STATUS_ANALISYS
                        ? Step::NOT_AVAILABLE
                        : Step::AVAILABLE
                )
                ->withStatus(
                    $this->status <= self::STATUS_ANALISYS
                        ? Step::NOT_STARTED
                        : ($this->status == self::STATUS_ANALISYS_PENDING ? Step::IN_PROGRESS : Step::SUCCESSFULLY)
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
                $this->ctrl->getLinkTargetByClass([self::class, ilNolejActivitiesFormGUI::class], ilNolejActivitiesFormGUI::CMD_SHOW)
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
     * @param string $id
     */
    public static function glyphicon($id)
    {
        return "<span class=\"glyphicon glyphicon-" . $id . "\" aria-hidden=\"true\"></span> ";
    }

    /**
     * Check for document updates; called via ajax request.
     * Requires in GET the document ID and the status to be checked.
     *
     * Prints "reload" if there is an update; prints nothing otherwise.
     */
    protected function checkUpdates(): void
    {
        global $DIC;

        if (!isset($_GET["document_id"], $_GET["status"])) {
            exit(0);
        }

        $set = $this->db->queryF(
            "SELECT `action` FROM " . ilNolejPlugin::TABLE_ACTIVITY
                . " WHERE document_id = %s AND user_id = %s"
                . " ORDER BY tstamp DESC LIMIT 1",
            ["text", "integer"],
            [$_GET["document_id"], $DIC->user()->getId()]
        );
        $row = $this->db->fetchAssoc($set);
        if (!$row) {
            exit(0);
        }

        if (
            $row["action"] == ($_GET["status"] . "_ok") ||
            $row["action"] == ($_GET["status"] . "_ko")
        ) {
            echo "reload";
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

            // Download activity.
            file_put_contents(
                $path,
                file_get_contents($activity->url)
            );

            $errorMessage = $this->importH5PContent($h5pDir, $activity->activity_name, $now);
            if (!empty($errorMessage)) {
                $errorMessages[] = "{$activity->activity_name} ({$errorMessage})";
            }
        }

        return implode(", ", $errorMessages);
    }

    /**
     * @param string $h5pDir directory where are located h5p activities
     * @param string $type of h5p activity to import
     * @param int $time
     * @return string error message. Empty string if succedeed.
     */
    public function importH5PContent($h5pDir, $type, $time)
    {
        global $DIC;
        $filePath = "{$h5pDir}/{$type}.h5p";
        $filePath = substr($filePath, 1);
        $absolutePath = ILIAS_ABSOLUTE_PATH . $filePath;

        $this->plugin->log("Importing H5P activity " . $type . " of document " . $this->documentId);

        $component_factory = $DIC['component.factory'];
        $h5p_plugin = $component_factory->getPlugin(ilH5PPlugin::PLUGIN_ID);

        /** @var IContainer */
        $h5p_container = $h5p_plugin->getContainer();

        /** @var H5PCore */
        $h5p_kernel = $h5p_container->getKernel();

        $file = ilH5PEditorStorage::saveFileTemporarily(
            $absolutePath,
            true
        );

        /** @var FileUploadCommunicator */
        $file_upload_communicator = $h5p_container->getFileUploadCommunicator();
        $file_upload_communicator->setUploadPath($file->dir . "/" . $file->fileName);

        /** @var H5PStorage */
        $h5p_storage = $h5p_container->getKernelStorage();

        /** @var H5PValidator */
        $h5p_validator = $h5p_container->getKernelValidator();
        if (!$h5p_validator->isValidPackage()) {
            $this->plugin->log("Import validation failed " . $type . " of document " . $this->documentId);
            return $this->plugin->txt("err_h5p_package");
        }

        $h5p_storage->savePackage([
            "metadata" => [
                "authors" => $h5p_kernel->mainJsonData["authors"] ?? "-",
                "authorComments" => $h5p_kernel->mainJsonData["authorComments"] ?? "",
                "changes" => $h5p_kernel->mainJsonData["changes"] ?? "",
                "defaultLanguage" => $h5p_kernel->mainJsonData["defaultLanguage"] ?? "",
                "license" => $h5p_kernel->mainJsonData["license"] ?? "",
                "licenseExtras" => $h5p_kernel->mainJsonData["licenseExtras"] ?? "",
                "licenseVersion" => $h5p_kernel->mainJsonData["licenseVersion"] ?? "",
                "source" => $h5p_kernel->mainJsonData["source"] ?? "",
                "title" => $h5p_kernel->mainJsonData["title"] ?? $this->plugin->txt("activities_{$type}"),
                "yearFrom" => $h5p_kernel->mainJsonData["yearFrom"] ?? "",
                "yearTo" => $h5p_kernel->mainJsonData["yearTo"] ?? "",
                "obj_id" => $this->getObjIdFromDocumentId($this->documentId),
                // "parent_type" => "unknown", // instead of ilNolejPlugin::PLUGIN_ID, so that read permission is granted
                "in_workspace" => false
            ]
        ]);

        ilH5PEditorStorage::removeTemporarilySavedFiles($file_upload_communicator->getUploadPath());

        $contentId = $h5p_storage->contentId;

        if ($contentId == null || $contentId < 1) {
            $this->plugin->log("Import failed " . $type . " of document " . $this->documentId);
            return $this->plugin->txt("err_content_id");
        }

        $this->db->manipulateF(
            "INSERT INTO " . ilNolejPlugin::TABLE_H5P
                . " (document_id, type, `generated`, content_id)"
                . " VALUES (%s, %s, %s, %s);",
            ["text", "text", "integer", "integer"],
            [$this->documentId, $type, $time, $contentId]
        );

        $this->plugin->log("Import completed " . $type . " of document " . $this->documentId);
        return "";
    }
}
