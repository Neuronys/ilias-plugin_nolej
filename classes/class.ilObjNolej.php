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

require_once ("./Services/Tracking/interfaces/interface.ilLPStatusPlugin.php");

require_once ("./Customizing/global/plugins/Services/Repository/RepositoryObject/Nolej/classes/class.ilNolejPlugin.php");
require_once (ilNolejPlugin::PLUGIN_DIR . "/classes/class.ilNolejConfig.php");
require_once (ilNolejPlugin::PLUGIN_DIR . "/classes/class.ilObjNolejGUI.php");

/**
 * Repository plugin object class
 */
class ilObjNolej extends ilObjectPlugin implements ilLPStatusPluginInterface
{

    /** @var bool */
    protected bool $online = false;

    /** @var string */
    protected string $documentId = "";

    /**
     * Constructor
     *
     * @access public
     * @param int $a_ref_id
     */
    function __construct($a_ref_id = 0)
    {
        parent::__construct($a_ref_id);
        $this->config = new ilNolejConfig();
    }

    /**
     * Get type.
     */
    final function initType()
    {
        $this->setType(ilNolejPlugin::PLUGIN_ID);
    }

    /**
     * Create object
     */
    function doCreate($clone_mode = false)
    {
        global $ilDB;

        $ilDB->manipulateF(
            "INSERT INTO " . ilNolejPlugin::TABLE_DATA . " (id, is_online, document_id) VALUES (%s, %s, NULL)",
            array("integer", "integer"),
            array($this->getId(), 0)
        );
    }

    /**
     * Read data from db
     */
    function doRead()
    {
        global $ilDB;

        $set = $ilDB->queryF(
            "SELECT * FROM " . ilNolejPlugin::TABLE_DATA . " WHERE id = %s;",
            array("integer"),
            array($this->getId())
        );
        while ($row = $ilDB->fetchAssoc($set)) {
            $this->setOnline($row["is_online"] ?? false);
            $this->setDocumentId($row["document_id"] ?? "");
        }
    }

    /**
     * Update data
     */
    function doUpdate()
    {
        global $ilDB;

        $ilDB->manipulateF(
            "UPDATE " . ilNolejPlugin::TABLE_DATA . " SET is_online = %s, document_id = %s WHERE id = %s;",
            array("integer", "text", "integer"),
            array($this->isOnline(), $this->getDocumentId(), $this->getId())
        );
    }

    /**
     * Delete data from db
     */
    function doDelete()
    {
        global $ilDB;

        $ilDB->manipulateF(
            "DELETE FROM " . ilNolejPlugin::TABLE_DATA . " WHERE id = %s;",
            array("integer"),
            array($this->getId())
        );
    }

    /**
     * Set online
     * @param boolean online
     */
    function setOnline(bool $a_val)
    {
        $this->online = $a_val;
    }

    /**
     * Set document_id
     * @param string documentId
     */
    function setDocumentId(string $a_val)
    {
        $this->documentId = $a_val;
    }

    /**
     * Get online
     * @return boolean online
     */
    function isOnline()
    {
        return $this->online;
    }

    /**
     * Get document_id
     * @return string documentId
     */
    function getDocumentId()
    {
        return $this->documentId;
    }

    /**
     * @return int
     */
    function getDocumentStatus()
    {
        global $ilDB;

        if ($this->getDocumentId() == null) {
            return 0;
        }

        $result = $ilDB->queryF(
            "SELECT `status` FROM " . ilNolejPlugin::TABLE_DOC . " WHERE document_id = %s",
            array("text"),
            array($this->getDocumentId())
        );

        $row = $this->db->fetchAssoc($result);
        return (int) $row["status"];
    }

    /**
     * @return string
     */
    function getDocumentSource()
    {
        global $ilDB;

        if ($this->getDocumentId() == null) {
            return "";
        }

        $result = $ilDB->queryF(
            "SELECT `doc_url` FROM " . ilNolejPlugin::TABLE_DOC . " WHERE document_id = %s",
            array("text"),
            array($this->getDocumentId())
        );

        $row = $this->db->fetchAssoc($result);
        return $row["doc_url"];
    }

    /**
     * @return string
     */
    function getDocumentMediaType()
    {
        global $ilDB;

        if ($this->getDocumentId() == null) {
            return "";
        }

        $result = $ilDB->queryF(
            "SELECT `media_type` FROM " . ilNolejPlugin::TABLE_DOC . " WHERE document_id = %s",
            array("text"),
            array($this->getDocumentId())
        );

        $row = $this->db->fetchAssoc($result);
        return $row["media_type"];
    }

    /**
     * @return string
     */
    function getDocumentLang()
    {
        global $ilDB;

        if ($this->getDocumentId() == null) {
            return "";
        }

        $result = $ilDB->queryF(
            "SELECT `language` FROM " . ilNolejPlugin::TABLE_DOC . " WHERE document_id = %s",
            array("text"),
            array($this->getDocumentId())
        );

        $row = $this->db->fetchAssoc($result);
        return $row["language"];
    }

    /**
     * @return string
     */
    function getDocumentTitle()
    {
        global $ilDB;

        if ($this->getDocumentId() == null) {
            return "";
        }

        $result = $ilDB->queryF(
            "SELECT `title` FROM " . ilNolejPlugin::TABLE_DOC . " WHERE document_id = %s",
            array("text"),
            array($this->getDocumentId())
        );

        $row = $this->db->fetchAssoc($result);
        return $row["title"];
    }

    /**
     * @return bool
     */
    function getDocumentAutomaticMode()
    {
        global $ilDB;

        if ($this->getDocumentId() == null) {
            return false;
        }

        $result = $ilDB->queryF(
            "SELECT `automatic_mode` FROM " . ilNolejPlugin::TABLE_DOC . " WHERE document_id = %s",
            array("text"),
            array($this->getDocumentId())
        );

        $row = $this->db->fetchAssoc($result);
        return ilUtil::yn2tf($row["automatic_mode"]);
    }

    /**
     * @return string
     */
    public function getDataDir()
    {
        return $this->config->dataDir() . $this->getDocumentId();
    }

    public function hasWritePermission()
    {
        global $ilAccess;
        return $ilAccess->checkAccess("write", "", $this->getRefId());
    }

    public function hasReadPermission()
    {
        global $ilAccess;
        return $ilAccess->checkAccess("read", "", $this->getRefId());
    }

    public function lookupDetails()
    {
    }

    /**
     * @param string $type of h5p activity to get
     * @return int h5p content id
     */
    public function getContentIdOfType(string $type)
    {
        $result = $this->db->queryF(
            "SELECT content_id FROM " . ilNolejPlugin::TABLE_H5P
            . " WHERE document_id = %s"
            . " AND type = %s"
            . " ORDER BY `generated` DESC"
            . " LIMIT 1",
            ["text", "text"],
            [$this->documentId, $type]
        );
        if ($row = $this->db->fetchAssoc($result)) {
            return (int) $row["content_id"];
        }
        return -1;
    }

    /**
     * @param array $user_ids
     */
    public function resetLPOfUsers($user_ids)
    {
        for ($i = 0, $n = count($user_ids); $i < $n; $i++) {
            self::resetLPOfUser($user_ids[$i]);
        }
    }

    /**
     * @param int $user_id
     */
    public function resetLPOfUser($user_id)
    {
        // TODO in future versions
    }

    public function resetLP()
    {
        global $ilUser;
        self::resetLPOfUser($ilUser->getId());
    }

    /**
     * Get all user ids with LP status completed
     * @return array
     */
    public function getLPCompleted()
    {
        return [];
    }

    /**
     * Get all user ids with LP status not attempted
     * @return array
     */
    public function getLPNotAttempted()
    {
        return [];
    }

    /**
     * Get all user ids with LP status failed
     * @return array
     */
    public function getLPFailed()
    {
        // Nolej modules do not have a "fail" condition (yet)
        return [];
    }

    /**
     * Get all user ids with LP status in progress
     * @return array
     */
    public function getLPInProgress()
    {
        return [];
    }

    /**
     * Get current status for given user
     * @param int $a_user_id
     * @return int
     */
    public function getLPStatusForUser($a_user_id)
    {
        return ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM;
    }

    /**
     * Get current percentage for given user
     * @param int $a_user_id
     * @return int
     */
    public function getPercentageForUser($a_user_id)
    {
        return 0;
    }

}
