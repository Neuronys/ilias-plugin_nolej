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

use ILIAS\GlobalScreen\Provider\PluginProviderCollection;

/**
 * Plugin main class
 */
class ilNolejPlugin extends ilRepositoryObjectPlugin
{
    /** @var string */
    public const PLUGIN_ID = "xnlj";

    /** @var string */
    public const PLUGIN_NAME = "Nolej";

    /** @var string */
    public const PLUGIN_DIR = "./Customizing/global/plugins/Services/Repository/RepositoryObject/Nolej";

    /** @var string */
    public const PERMALINK = "xnlj_modules";

    /** @var string */
    public const PREFIX = "rep_robj_xnlj";

    /** @var string */
    public const TABLE_CONFIG = "rep_robj_xnlj_config";

    /** @var string */
    public const TABLE_ACTIVITY = "rep_robj_xnlj_activity";

    /** @var string */
    public const TABLE_DOC = "rep_robj_xnlj_doc";

    /** @var string */
    public const TABLE_DATA = "rep_robj_xnlj_data";

    /** @var string */
    public const TABLE_H5P = "rep_robj_xnlj_hfp";

    /** @var string */
    public const TABLE_LP = "rep_robj_xnlj_lp";

    /** @var self|null */
    protected static $instance = null;

    /** @var PluginProviderCollection|null */
    protected static $pluginProviderCollection = null;

    /** @var ilLogger */
    public ilLogger $logger;

    /** @var array */
    protected static $config = [];

    /**
     * Initialize plugin.
     * @return void
     */
    public function init(): void
    {
        require_once self::PLUGIN_DIR . "/classes/class.ilNolejH5PIntegrationGUI.php";

        $this->logger = ilLoggerFactory::getLogger(self::PREFIX);

        if (self::$instance == null) {
            self::$instance = $this;
        }
    }

    /**
     * Exchange renderer after plugin initialization.
     * @param \ILIAS\DI\Container $dic
     * @return Closure
     */
    public function exchangeUIRendererAfterInitialization(\ILIAS\DI\Container $dic): Closure
    {
        if ($dic->offsetExists("global_screen")) {
            // Add plugin providers.
            self::$pluginProviderCollection = $this->getPluginProviderCollection(); // Fix overflow.
        }

        /**
         * Exchanges the default renderer instead of h5p plugin, if it is available, installed but not active.
         * This needs to be done because this plugins should still be usable even if the
         * main plugin is inactive. Since renderers are only exchanged if a plugin is
         * active, we need to exchange the renderer here to cover this scenario.
         * @see ilH5PPageComponentPlugin::exchangeUIRendererAfterInitialization
         */
        if (ilNolejH5PIntegrationGUI::isH5PInstalled() && !ilNolejH5PIntegrationGUI::isH5PActive()) {
            $h5p = ilNolejH5PIntegrationGUI::getH5PPlugin();
            return $h5p->exchangeUIRendererAfterInitialization($dic);
        }

        // Returns the default callable without executing it.
        return $dic->raw("ui.renderer");
    }

    /**
     * @return PluginProviderCollection
     */
    protected function getPluginProviderCollection()
    {
        global $DIC;

        if (!isset($DIC["global_screen"])) {
            return self::$pluginProviderCollection;
        }

        require_once self::PLUGIN_DIR . "/classes/Notification/class.ilNolejNotificationProvider.php";

        if (self::$pluginProviderCollection === null) {
            self::$pluginProviderCollection = new PluginProviderCollection();

            // self::$pluginProviderCollection->setMetaBarProvider(new NolejMetaBarProvider($DIC, $this));
            self::$pluginProviderCollection->setNotificationProvider(new ilNolejNotificationProvider($DIC, $this));
        }

        return self::$pluginProviderCollection;
    }

    /**
     * @return self
     */
    public static function getInstance(): self
    {
        global $DIC;

        if (self::$instance == null) {
            /** @var ilComponentFactory */
            $component_factory = $DIC["component.factory"];

            $plugin = $component_factory->getPlugin(self::PLUGIN_ID);
            self::$instance = $plugin;
        }

        return self::$instance;
    }

    /**
     * Must correspond to the plugin subdirectory
     * @return string
     */
    public function getPluginName(): string
    {
        return self::PLUGIN_NAME;
    }

    /**
     * Returns a list of all repository object types which can be a parent of this type.
     * @return array
     */
    public function getParentTypes(): array
    {
        $par_types = ["root", "cat", "crs", "grp", "fold", "lso", "prg"];
        return $par_types;
    }

    /**
     * Delete plugin tables.
     * @return void
     */
    protected function uninstallCustom(): void
    {
        $tables = [
            self::TABLE_CONFIG,
            self::TABLE_ACTIVITY,
            self::TABLE_DATA,
            self::TABLE_DOC,
            self::TABLE_H5P,
            self::TABLE_LP
        ];

        for ($i = 0, $len = count($tables); $i < $len; $i++) {
            if ($this->db->tableExists($tables[$i])) {
                $this->db->dropTable($tables[$i]);
            }
        }
    }

    /**
     * Nolej objects cannot be copied.
     * @return bool
     */
    public function allowCopy(): bool
    {
        return false;
    }

    /**
     * Get the url to the plugin configuration GUI.
     * @return string
     */
    public function getConfigurationLink()
    {
        global $DIC;

        $ctrl = $DIC->ctrl();

        include_once self::PLUGIN_DIR . "/classes/class.ilNolejConfigGUI.php";

        $ctrl->setParameterByClass(ilNolejConfigGUI::class, "ref_id", "31");
        $ctrl->setParameterByClass(ilNolejConfigGUI::class, "ctype", "Services");
        $ctrl->setParameterByClass(ilNolejConfigGUI::class, "cname", "Repository");
        $ctrl->setParameterByClass(ilNolejConfigGUI::class, "slot_id", "robj");
        $ctrl->setParameterByClass(ilNolejConfigGUI::class, "plugin_id", self::PLUGIN_ID);
        $ctrl->setParameterByClass(ilNolejConfigGUI::class, "pname", self::PLUGIN_NAME);

        return $ctrl->getLinkTargetByClass(
            ["ilAdministrationGUI", "ilObjComponentSettingsGUI", ilNolejConfigGUI::class],
            ilNolejConfigGUI::CMD_CONFIGURE
        );
    }

    /**
     * @param string $keyword
     * @param string $defaultValue
     * @return string
     */
    public static function getConfig($keyword, $defaultValue = "")
    {
        global $DIC;
        $db = $DIC->database();

        if (isset(self::$config[$keyword])) {
            return self::$config[$keyword];
        }

        $res = $db->queryF(
            "SELECT `value` FROM " . self::TABLE_CONFIG . " WHERE keyword = %s;",
            ["text"],
            [$keyword]
        );

        if (!$res || $db->numRows($res) <= 0) {
            return $defaultValue;
        }

        $record = $db->fetchAssoc($res);
        self::$config[$keyword] = $record["value"];
        return $record["value"];
    }

    /**
     * @param string $keyword
     * @param string $value
     */
    public static function saveConfig($keyword, $value)
    {
        global $DIC;
        $db = $DIC->database();

        self::$config[$keyword] = $value;

        $db->manipulateF(
            "REPLACE INTO " . self::TABLE_CONFIG . " (keyword, value) VALUES (%s, %s);",
            ["text", "text"],
            [$keyword, $value]
        );
    }

    /**
     * Get the HTML of an H5P activity.
     * @param int $contentId
     * @param bool $editable
     * @return string html
     */
    public static function renderH5P($contentId, bool $editable = false): string
    {
        global $DIC;

        $factory = $DIC->ui()->factory();
        $renderer = $DIC->ui()->renderer();
        $db = $DIC->database();

        try {
            $result = $db->queryF(
                "SELECT id FROM " . self::TABLE_DATA
                    . " WHERE document_id = (SELECT document_id FROM " . self::TABLE_H5P . " WHERE content_id = %s)",
                ["integer"],
                [$contentId]
            );
            if ($row = $db->fetchAssoc($result)) {
                $objId = $row["id"];
                $refId = reset(ilObject::_getAllReferences($objId));
                $obj_gui = new ilObjNolejGUI($refId);
                $h5p = new ilNolejH5PIntegrationGUI($obj_gui);
                return $h5p->getHTML((int) $contentId, $editable);
            }

            throw new Exception("Activity not found.");
        } catch (Exception $e) {
            // Fallback.
            return $renderer->render($factory->messageBox()->failure($e->getMessage()));
        }
    }

    /**
     * Log a message.
     * @param string $a_message
     * @param int $a_level (info, warning, error, debug)
     * @return void
     */
    public function log($a_message, int $a_level = ilLogLevel::INFO): void
    {
        $this->logger->log($a_message, $a_level);
    }
}
