<?php
declare(strict_types=1);

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
 * This class provides common configuration methods.
 */
class ilNolejConfig
{
    /** @var string|null */
    private $registeredApiKey = null;

    private $filters = null;

    /** @var array */
    static $config = [];

    /** @var ilLogger */
    public $logger;

    const H5P_MAIN_AUTOLOAD = "./Customizing/global/plugins/Services/Repository/RepositoryObject/H5P/vendor/autoload.php";
    const H5P_MAIN_PLUGIN = "./Customizing/global/plugins/Services/Repository/RepositoryObject/H5P/classes/class.ilH5PPlugin.php";

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = ilLoggerFactory::getLogger(ilNolejPlugin::PREFIX);
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        if ($this->registeredApiKey == null) {
            $this->registeredApiKey = $this->get("api_key", "");
        }
        return $this->registeredApiKey;
    }

    /**
     * @param string $str
     * @return bool|int
     */
    public function checkInputString(string $str)
    {
        return preg_match('/^[a-zA-Z0-9\-]{1,100}$/', $str);
    }

    /**
     * @param string $id
     * @param string|null $default
     * @return string|null
     */
    public function getParameter(string $id, ?string $default = null): ?string
    {
        if (isset($_GET[$id]) && $this->checkInputString($_GET[$id])) {
            return $_GET[$id];
        }
        return $default;
    }

    /**
     * @param string $id
     * @param int|null $default
     * @return int|null
     */
    public function getParameterInteger(string $id, ?int $default = null): ?int
    {
        $par = $this->getParameter($id);
        if ($par != null && (is_int($par) || ctype_digit($par))) {
            return (int) $par;
        }
        return $default;
    }

    /**
     * @param string $id
     * @param int|null $default
     * @return int|null
     */
    public function getParameterPositive(string $id, ?int $default = null): ?int
    {
        $par = $this->getParameterInteger($id);
        if ($par != null && $par > 0) {
            return $par;
        }
        return $default;
    }

    /**
     * Language handler
     *
     * @param string $key
     * @return string
     */
    public static function txt(string $key): string
    {
        global $DIC;
        return $DIC->language()->txt(ilNolejPlugin::PREFIX . "_" . $key);
    }

    /**
     * Get a configuration param from the database.
     *
     * @param string $id
     * @param ?string $default
     * @return ?string
     */
    public function get(string $id, ?string $default = null): ?string
    {
        global $DIC;

        if (isset(self::$config[$id])) {
            return self::$config[$id];
        }

        $db = $DIC->database();
        $res = $db->queryF(
            "SELECT `value` FROM " . ilNolejPlugin::TABLE_CONFIG . " WHERE keyword = %s;",
            array("text"),
            array($id)
        );

        if (!$res || $db->numRows($res) <= 0) {
            return $default;
        }

        $record = $db->fetchAssoc($res);
        self::$config[$id] = $record["value"];
        return $record["value"];
    }

    /**
     * Store a configuration param to the database.
     *
     * @param string $id
     * @param string $value
     */
    public function set(string $id, string $value): void
    {
        global $DIC;

        self::$config[$id] = $value;

        $db = $DIC->database();
        $db->manipulateF(
            "REPLACE INTO " . ilNolejPlugin::TABLE_CONFIG . " (keyword, value) VALUES (%s, %s);",
            array("text", "text"),
            array($id, $value)
        );
    }

    /**
     * Returns the directory where all Nolej
     * data is stored (transcriptions, activities, ...)
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
     * Include H5P plugin or
     * @throws LogicException if it is not installed
     */
    public static function includeH5P(): void
    {
        if (
            !file_exists(self::H5P_MAIN_AUTOLOAD) ||
            !file_exists(self::H5P_MAIN_PLUGIN)
        ) {
            throw new LogicException("You cannot use this plugin without installing the H5P plugin first.");
        }

        if (!self::isH5PPluginLoaded()) {
            require_once (self::H5P_MAIN_AUTOLOAD);
            require_once (self::H5P_MAIN_PLUGIN);
        }
    }

    public static function isH5PPluginLoaded(): bool
    {
        return class_exists('ilH5PPlugin');
    }
}
