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

/**
 * This class takes care of the calls to the webhook.
 */
class ilNolejWebhook
{
    /** @var ilNolejPlugin */
    protected $plugin;

    /** @var array */
    protected $data;

    /** @var bool */
    protected $shouldDie = false;

    public function __construct()
    {
        require_once ilNolejPlugin::PLUGIN_DIR . "/classes/Notification/NolejActivity.php";
        require_once ilNolejPlugin::PLUGIN_DIR . "/classes/class.ilNolejActivityManagementGUI.php";
        $this->plugin = ilNolejPlugin::getInstance();
    }

    /**
     * Parse the request from POST content if
     * @param mixed $data is not null
     */
    public function parse($data = null)
    {
        if ($data == null) {
            header("Content-type: application/json; charset=UTF-8");
            $data = json_decode(file_get_contents("php://input"), true);
            $this->shouldDie = true;
        }

        if (
            !is_array($data) ||
            !isset($data["action"]) ||
            !is_string($data["action"])
        ) {
            $this->die_message(400, "Request not valid.");
            $this->plugin->log("Received invalid request: " . var_export($data, true));
        }

        $this->data = $data;
        switch ($data["action"]) {
            case "transcription":
                $this->plugin->log("Received transcription request: " . var_export($data, true));
                $this->checkTranscription();
                break;

            case "analysis":
                $this->plugin->log("Received analysis request: " . var_export($data, true));
                $this->checkAnalysis();
                break;

            case "activities":
                $this->plugin->log("Received activities request: " . var_export($data, true));
                $this->checkActivities();
                break;

            case "work in progress":
                $this->plugin->log("Received work in progress.");
                global $DIC, $tpl;
                if (!$DIC->user()->isAnonymous()) {
                    $tpl->setOnScreenMessage("info", $this->plugin->txt("work_in_progress"));
                    return;
                }
                break;

            default:
                $this->plugin->log("Received invalid action: " . var_export($data, true));
        }
    }

    /**
     * Die with status code and a message
     * @param int $code
     * @param string $message
     */
    protected function die_message(
        $code = 400,
        $message = ""
    ) {
        if (!empty($message)) {
            $this->plugin->log("Replied to Nolej with message: " . $message);
            if ($this->shouldDie) {
                echo json_encode(["message" => $message]);
            }
        }

        if (!$this->shouldDie) {
            return false;
        }

        http_response_code($code);
        exit;
    }

    public function checkTranscription()
    {
        global $DIC;

        if ($this->data["consumedCredit"] == null) {
            $this->data["consumedCredit"] = 0;
        }

        if (
            !isset(
                $this->data["documentID"],
                $this->data["status"],
                $this->data["code"],
                $this->data["error_message"],
                $this->data["consumedCredit"]
            ) ||
            !is_string($this->data["documentID"]) ||
            !is_string($this->data["status"]) ||
            !is_string($this->data["error_message"]) ||
            !is_integer($this->data["code"]) ||
            !is_integer($this->data["consumedCredit"])
        ) {
            $this->die_message(400, "Request not valid.");
            return;
        }

        $db = $DIC->database();
        $documentId = $this->data["documentID"];

        $result = $db->queryF(
            "SELECT a.user_id, d.title"
            . " FROM " . ilNolejPlugin::TABLE_DOC . " d"
            . " INNER JOIN ("
            . "   SELECT * FROM " . ilNolejPlugin::TABLE_ACTIVITY
            . "   WHERE document_id = %s"
            . "   AND tstamp = ("
            . "     SELECT MAX(tstamp)"
            . "     FROM " . ilNolejPlugin::TABLE_ACTIVITY
            . "     WHERE document_id = %s"
            . "   )"
            . " ) a"
            . " ON a.document_id = d.document_id"
            . " WHERE d.document_id = %s AND d.status = %s;",
            [
                "text",
                "text",
                "text",
                "integer"
            ],
            [
                $documentId,
                $documentId,
                $documentId,
                ilNolejActivityManagementGUI::STATUS_CREATION_PENDING
            ]
        );
        if ($db->numRows($result) != 1) {
            $this->die_message(404, "Document ID not found.");
            return;
        }

        $document = $db->fetchAssoc($result);

        $now = strtotime("now");
        $this->setUserLang($document["user_id"]);

        if (
            $this->data["status"] != "\"ok\"" &&
            $this->data["status"] != "ok"
        ) {
            $this->plugin->log("Result: ko");

            $result = $db->manipulateF(
                "UPDATE " . ilNolejPlugin::TABLE_DOC
                . " SET status = %s, consumed_credit = %s"
                . " WHERE document_id = %s;",
                [
                    "integer",
                    "integer",
                    "text"
                ],
                [
                    ilNolejActivityManagementGUI::STATUS_CREATION,
                    $this->data["consumedCredit"],
                    $documentId
                ]
            );
            if (!$result) {
                $this->die_message(404, "Document not found.");
            }

            $this->sendNotification(
                $documentId,
                (int) $document["user_id"],
                "transcription_ko",
                $this->data["status"],
                $this->data["code"],
                $this->data["error_message"],
                $this->data["consumedCredit"],
                "action_transcription_ko_desc",
                [
                    $document["title"],
                    ilDatePresentation::formatDate(new ilDateTime($now, IL_CAL_UNIX)),
                    $this->data["error_message"]
                ]
            );
            return;
        }

        $result = $db->manipulateF(
            "UPDATE " . ilNolejPlugin::TABLE_DOC
            . " SET status = %s, consumed_credit = %s WHERE document_id = %s;",
            [
                "integer",
                "integer",
                "text"
            ],
            [
                ilNolejActivityManagementGUI::STATUS_ANALISYS,
                $this->data["consumedCredit"],
                $documentId
            ]
        );
        if (!$result) {
            $this->die_message(404, "Document not found.");
        }

        $this->sendNotification(
            $documentId,
            (int) $document["user_id"],
            "transcription_ok",
            $this->data["status"],
            $this->data["code"],
            $this->data["error_message"],
            $this->data["consumedCredit"],
            "action_transcription_ok_desc",
            [
                $document["title"],
                ilDatePresentation::formatDate(new ilDateTime($now, IL_CAL_UNIX)),
                $this->data["error_message"]
            ]
        );

        $this->die_message(200, "Transcription received!");
    }

    public function checkAnalysis()
    {
        global $DIC;

        if ($this->data["consumedCredit"] == null) {
            $this->data["consumedCredit"] = 0;
        }

        if (
            !isset(
                $this->data["documentID"],
                $this->data["status"],
                $this->data["code"],
                $this->data["error_message"],
                $this->data["consumedCredit"]
            ) ||
            !is_string($this->data["documentID"]) ||
            !is_string($this->data["status"]) ||
            !is_string($this->data["error_message"]) ||
            !is_integer($this->data["code"]) ||
            !is_integer($this->data["consumedCredit"])
        ) {
            $this->die_message(400, "Request not valid.");
            return;
        }

        $db = $DIC->database();
        $documentId = $this->data["documentID"];

        $result = $db->queryF(
            "SELECT a.user_id, d.title"
            . " FROM " . ilNolejPlugin::TABLE_DOC . " d"
            . " INNER JOIN ("
            . "   SELECT * FROM " . ilNolejPlugin::TABLE_ACTIVITY
            . "   WHERE document_id = %s"
            . "   AND tstamp = ("
            . "     SELECT MAX(tstamp)"
            . "     FROM " . ilNolejPlugin::TABLE_ACTIVITY
            . "     WHERE document_id = %s"
            . "   )"
            . " ) a"
            . " ON a.document_id = d.document_id"
            . " WHERE d.document_id = %s AND d.status = %s;",
            [
                "text",
                "text",
                "text",
                "integer"
            ],
            [
                $documentId,
                $documentId,
                $documentId,
                ilNolejActivityManagementGUI::STATUS_ANALISYS_PENDING
            ]
        );
        if ($db->numRows($result) != 1) {
            $this->die_message(404, "Document ID not found.");
            return;
        }

        $document = $db->fetchAssoc($result);

        $now = strtotime("now");
        $this->setUserLang($document["user_id"]);

        if (
            $this->data["status"] != "\"ok\"" &&
            $this->data["status"] != "ok"
        ) {
            $this->plugin->log("Result: ko");

            $result = $db->manipulateF(
                "UPDATE " . ilNolejPlugin::TABLE_DOC
                . " SET status = %s, consumed_credit = %s"
                . " WHERE document_id = %s;",
                [
                    "integer",
                    "integer",
                    "text"
                ],
                [
                    ilNolejActivityManagementGUI::STATUS_ANALISYS,
                    $this->data["consumedCredit"],
                    $documentId
                ]
            );
            if (!$result) {
                $this->die_message(404, "Document not found.");
            }

            $this->sendNotification(
                $documentId,
                (int) $document["user_id"],
                "analysis_ko",
                $this->data["status"],
                $this->data["code"],
                $this->data["error_message"],
                $this->data["consumedCredit"],
                "action_analysis_ko_desc",
                [
                    $document["title"],
                    ilDatePresentation::formatDate(new ilDateTime($now, IL_CAL_UNIX)),
                    $this->data["error_message"]
                ]
            );
            return;
        }

        $result = $db->manipulateF(
            "UPDATE " . ilNolejPlugin::TABLE_DOC
            . " SET status = %s, consumed_credit = %s"
            . " WHERE document_id = %s;",
            [
                "integer",
                "integer",
                "text"
            ],
            [
                ilNolejActivityManagementGUI::STATUS_REVISION,
                $this->data["consumedCredit"],
                $documentId
            ]
        );
        if (!$result) {
            $this->die_message(404, "Document not found.");
        }

        $this->sendNotification(
            $documentId,
            (int) $document["user_id"],
            "analysis_ok",
            $this->data["status"],
            $this->data["code"],
            $this->data["error_message"],
            $this->data["consumedCredit"],
            "action_analysis_ok_desc",
            [
                $document["title"],
                ilDatePresentation::formatDate(new ilDateTime($now, IL_CAL_UNIX)),
                $this->data["error_message"]
            ]
        );

        $this->die_message(200, "Analysis received!");
    }

    public function checkActivities()
    {
        global $DIC;

        if ($this->data["consumedCredit"] == null) {
            $this->data["consumedCredit"] = 0;
        }

        if (
            !isset(
                $this->data["documentID"],
                $this->data["status"],
                $this->data["code"],
                $this->data["error_message"],
                $this->data["consumedCredit"]
            ) ||
            !is_string($this->data["documentID"]) ||
            !is_string($this->data["status"]) ||
            !is_string($this->data["error_message"]) ||
            !is_integer($this->data["code"]) ||
            !is_integer($this->data["consumedCredit"])
        ) {
            $this->die_message(400, "Request not valid.");
            return;
        }

        $db = $DIC->database();
        $documentId = $this->data["documentID"];

        $result = $db->queryF(
            "SELECT a.user_id, d.title"
            . " FROM " . ilNolejPlugin::TABLE_DOC . " d"
            . " INNER JOIN ("
            . "   SELECT * FROM " . ilNolejPlugin::TABLE_ACTIVITY
            . "   WHERE document_id = %s"
            . "   AND tstamp = ("
            . "     SELECT MAX(tstamp)"
            . "     FROM " . ilNolejPlugin::TABLE_ACTIVITY
            . "     WHERE document_id = %s"
            . "   )"
            . " ) a"
            . " ON a.document_id = d.document_id"
            . " WHERE d.document_id = %s AND d.status = %s;",
            [
                "text",
                "text",
                "text",
                "integer"
            ],
            [
                $documentId,
                $documentId,
                $documentId,
                ilNolejActivityManagementGUI::STATUS_ACTIVITIES_PENDING
            ]
        );
        if ($db->numRows($result) != 1) {
            $this->die_message(404, "Document ID not found.");
            return;
        }

        $document = $db->fetchAssoc($result);
        $now = strtotime("now");
        $this->setUserLang($document["user_id"]);

        if (
            $this->data["status"] != "\"ok\"" &&
            $this->data["status"] != "ok"
        ) {
            $this->plugin->log("Result: ko");
            $this->sendNotification(
                $documentId,
                (int) $document["user_id"],
                "activities_ko",
                $this->data["status"],
                $this->data["code"],
                $this->data["error_message"],
                $this->data["consumedCredit"],
                "action_activities_ko_desc",
                [
                    $document["title"],
                    ilDatePresentation::formatDate(new ilDateTime($now, IL_CAL_UNIX)),
                    $this->data["error_message"]
                ]
            );
            return;
        }

        $result = $db->manipulateF(
            "UPDATE " . ilNolejPlugin::TABLE_DOC
            . " SET status = %s, consumed_credit = %s"
            . " WHERE document_id = %s;",
            [
                "integer",
                "integer",
                "text"
            ],
            [
                ilNolejActivityManagementGUI::STATUS_COMPLETED,
                $this->data["consumedCredit"],
                $documentId
            ]
        );
        if (!$result) {
            $this->die_message(404, "Document not found.");
        }

        $activityManagement = new ilNolejActivityManagementGUI(null, $documentId);
        $fails = $activityManagement->downloadActivities();
        if (!empty($fails)) {
            $this->plugin->log("Failed to download some activities: " . $fails . ".");
            $this->sendNotification(
                $documentId,
                (int) $document["user_id"],
                "activities_ko",
                $this->data["status"],
                $this->data["code"],
                $this->data["error_message"],
                $this->data["consumedCredit"],
                "err_activities_get",
                [$fails]
            );

            $this->die_message(200, "Activities received, but something went wrong while retrieving them.");
            return;
        }

        $this->sendNotification(
            $documentId,
            (int) $document["user_id"],
            "activities_ok",
            $this->data["status"],
            $this->data["code"],
            $this->data["error_message"],
            $this->data["consumedCredit"],
            "action_activities_ok_desc",
            [
                $document["title"],
                ilDatePresentation::formatDate(new ilDateTime($now, IL_CAL_UNIX))
            ]
        );
        $this->die_message(200, "Activities received!");
    }

    /**
     * Send notification to user
     *
     * @param string $documentId
     * @param int $userId
     * @param string $action used as language key for title
     * @param string $status
     * @param int $code
     * @param string $errorMessage
     * @param int $credits
     * @param string $bodyVar language variable to use in mail body
     * @param string[] $vars parameters to use in $bodyVar's sprintf
     */
    public function sendNotification(
        $documentId,
        $userId,
        $action,
        $status,
        $code,
        $errorMessage,
        $credits,
        $bodyVar,
        $vars = []
    ) {
        /** Send Notification */
        $ass = new NolejActivity($documentId, $userId, $action);
        $ass->withStatus($status)
            ->withCode($code)
            ->withErrorMessage($errorMessage)
            ->withConsumedCredit($credits)
            ->store();

        /** Send Email */
        $lng = $this->setUserLang($userId);

        $notification = new ILIAS\Notifications\Model\ilNotificationConfig("chat_invitation");

        $notification->setTitleVar(
            $lng->txt(
                sprintf(
                    "%s_action_%s",
                    ilNolejPlugin::PREFIX,
                    $action
                )
            )
        );
        $descriptionVar = sprintf(
            $lng->txt(ilNolejPlugin::PREFIX . "_" . $bodyVar),
            ...$vars
        );
        $notification->setIconPath(ilNolejPlugin::PLUGIN_DIR . "/templates/images/icon_xnlj.svg");
        $notification->setShortDescriptionVar($descriptionVar);
        $notification->setLongDescriptionVar($descriptionVar);
        $notification->setAutoDisable(false);
        $notification->setValidForSeconds(0);
        $notification->setHandlerParam('mail.sender', SYSTEM_USER_ID);
        $notification->notifyByUsers([$userId]);
    }

    /**
     * @param int $a_user_id
     * @return ilLanguage
     */
    protected function setUserLang($a_user_id)
    {
        $lang = ilObjUser::_lookupLanguage($a_user_id);
        $lng = new ilLanguage($lang);
        $lng->loadLanguageModule(ilNolejPlugin::PREFIX);
        ilDatePresentation::setUseRelativeDates(false);
        ilDatePresentation::setLanguage($lng);
        return $lng;
    }
}
