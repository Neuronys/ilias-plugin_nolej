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

require_once "./Services/Tracking/interfaces/interface.ilLPStatusPlugin.php";
require_once "./Customizing/global/plugins/Services/Repository/RepositoryObject/Nolej/classes/class.ilNolejPlugin.php";

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
     * Constructor.
     * @param int $a_ref_id
     */
    public function __construct($a_ref_id = 0)
    {
        parent::__construct($a_ref_id);
    }

    /**
     * Get type.
     * @return void
     */
    final public function initType(): void
    {
        $this->setType(ilNolejPlugin::PLUGIN_ID);
    }

    /**
     * Create object.
     * @param bool $clone_mode
     * @return void
     */
    public function doCreate(bool $clone_mode = false): void
    {
        $this->db->manipulateF(
            "INSERT INTO " . ilNolejPlugin::TABLE_DATA . " (id, is_online, document_id) VALUES (%s, %s, NULL)",
            ["integer", "integer"],
            [$this->getId(), 0]
        );
    }

    /**
     * Read data from db.
     * @return void
     */
    public function doRead(): void
    {
        $set = $this->db->queryF(
            "SELECT * FROM " . ilNolejPlugin::TABLE_DATA . " WHERE id = %s;",
            ["integer"],
            [$this->getId()]
        );

        while ($row = $this->db->fetchAssoc($set)) {
            $this->setOnline($row["is_online"] ?? false);
            $this->setDocumentId($row["document_id"] ?? "");
        }
    }

    /**
     * Update data.
     * @return void
     */
    public function doUpdate(): void
    {
        $this->db->manipulateF(
            "UPDATE " . ilNolejPlugin::TABLE_DATA . " SET is_online = %s, document_id = %s WHERE id = %s;",
            ["integer", "text", "integer"],
            [$this->isOnline(), $this->getDocumentId(), $this->getId()]
        );
    }

    /**
     * Delete data from db.
     * @return void
     */
    public function doDelete(): void
    {
        $this->db->manipulateF(
            "DELETE FROM " . ilNolejPlugin::TABLE_DATA . " WHERE id = %s;",
            ["integer"],
            [$this->getId()]
        );
    }

    /**
     * Set online.
     * @param bool $online
     * @return void
     */
    public function setOnline(bool $online): void
    {
        $this->online = $online;
    }

    /**
     * Set document_id.
     * @param string $documentId
     * @return void
     */
    public function setDocumentId(string $documentId): void
    {
        $this->documentId = $documentId;
    }

    /**
     * Get online.
     * @return bool online
     */
    public function isOnline(): bool
    {
        return $this->online;
    }

    /**
     * Get document_id.
     * @return string documentId
     */
    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    /**
     * Lookup the value of a document.
     * @param string $name of the column
     * @return string
     */
    protected function lookupDocumentData(string $name): string
    {
        if ($this->getDocumentId() == null) {
            return 0;
        }

        $result = $this->db->queryF(
            "SELECT `$name` FROM " . ilNolejPlugin::TABLE_DOC . " WHERE document_id = %s",
            ["text"],
            [$this->getDocumentId()]
        );

        $row = $this->db->fetchAssoc($result);
        return $row[$name];
    }

    /**
     * Get document status.
     * @return int
     */
    public function getDocumentStatus(): int
    {
        return (int) $this->lookupDocumentData("status");
    }

    /**
     * Get document source.
     * @return string
     */
    public function getDocumentSource(): string
    {
        return $this->lookupDocumentData("doc_url");
    }

    /**
     * Get the media type of the document.
     * @return string
     */
    public function getDocumentMediaType(): string
    {
        return $this->lookupDocumentData("media_type");
    }

    /**
     * Get the document language.
     * @return string
     */
    function getDocumentLang(): string
    {
        return $this->lookupDocumentData("language");
    }

    /**
     * Get the document title.
     * @return string
     */
    function getDocumentTitle(): string
    {
        return $this->lookupDocumentData("title");
    }

    /**
     * Returns the directory where all Nolej data is stored.
     *
     * @return string
     *
     * @todo Replace use of this method with th filesystem service
     * @see \ILIAS\DI\Container::filesystem()
     * @see Filesystems::web()
     */
    public static function dataDir(): string
    {
        return "./" . ILIAS_WEB_DIR . "/" . CLIENT_ID . "/" . ilNolejPlugin::PLUGIN_ID . "/";
    }

    /**
     * Returns the directory where all Nolej data of this document
     * is stored (transcriptions, activities, ...)
     *
     * @return string
     */
    public function getDataDir(): string
    {
        return self::dataDir() . $this->getDocumentId();
    }

    /**
     * Check if the user has write permission.
     * @return bool
     */
    public function hasWritePermission(): bool
    {
        global $ilAccess;
        return $ilAccess->checkAccess("write", "", $this->getRefId());
    }

    /**
     * Check if the user has read permission.
     * @return bool
     */
    public function hasReadPermission(): bool
    {
        global $ilAccess;
        return $ilAccess->checkAccess("read", "", $this->getRefId());
    }

    /**
     * Get the ID of h5p activity of this document
     * given its h5p package type.
     * @param string $type of h5p activity to get
     * @return int h5p content id
     */
    public function getContentIdOfType(string $type): int
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
     * Reset LP of users.
     * @param array $user_ids
     */
    public function resetLPOfUsers(array $user_ids): void
    {
        for ($i = 0, $n = count($user_ids); $i < $n; $i++) {
            self::resetLPOfUser($user_ids[$i]);
        }
    }

    /**
     * Reset LP of a single user.
     * @param int $user_id
     */
    public function resetLPOfUser(int $user_id): void
    {
        // TODO in future version
    }

    /**
     * Reset LP of the current user.
     * @param int $user_id
     */
    public function resetLP(): void
    {
        global $ilUser;
        self::resetLPOfUser($ilUser->getId());
    }

    /**
     * Get all user ids with LP status completed.
     * @return array
     */
    public function getLPCompleted(): array
    {
        return [];
    }

    /**
     * Get all user ids with LP status not attempted.
     * @return array
     */
    public function getLPNotAttempted(): array
    {
        return [];
    }

    /**
     * Get all user ids with LP status failed.
     * @return array
     */
    public function getLPFailed(): array
    {
        // Nolej modules do not have a "fail" condition (yet)
        return [];
    }

    /**
     * Get all user ids with LP status in progress.
     * @return array
     */
    public function getLPInProgress(): array
    {
        return [];
    }

    /**
     * Get current status for given user.
     * @param int $a_user_id
     * @return int
     */
    public function getLPStatusForUser(int $a_user_id): int
    {
        return ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM;
    }

    /**
     * Get current percentage for given user.
     * @param int $a_user_id
     * @return int
     */
    public function getPercentageForUser(int $a_user_id): int
    {
        return 0;
    }

}
