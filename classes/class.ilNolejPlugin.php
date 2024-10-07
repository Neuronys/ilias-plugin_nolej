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
    const PLUGIN_ID = "xnlj";
    const PLUGIN_NAME = "Nolej";
    const PLUGIN_DIR = "./Customizing/global/plugins/Services/Repository/RepositoryObject/Nolej";
    const PERMALINK = "xnlj_modules";
    const PREFIX = "rep_robj_xnlj";

    const TABLE_CONFIG = "rep_robj_xnlj_config";
    const TABLE_ACTIVITY = "rep_robj_xnlj_activity";
    const TABLE_TIC = "rep_robj_xnlj_tic";
    const TABLE_DOC = "rep_robj_xnlj_doc";
    const TABLE_DATA = "rep_robj_xnlj_data";
    const TABLE_H5P = "rep_robj_xnlj_hfp";
    const TABLE_LP = "rep_robj_xnlj_lp";

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
        $this->logger = ilLoggerFactory::getLogger(self::PREFIX);

        if (self::$instance == null) {
            self::$instance = $this;
        }
    }

    /**
     * Used to initialize providers.
     * @param \ILIAS\DI\Container $dic
     * @return Closure
     */
    public function exchangeUIRendererAfterInitialization(\ILIAS\DI\Container $dic): Closure
    {
        // Add plugin providers.
        global $DIC;

        if (isset($DIC["global_screen"])) {
            self::$pluginProviderCollection = $this->getPluginProviderCollection(); // Fix overflow.
        }

        // This returns the callable of $c["ui.renderer"] without executing it.
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

        require_once (self::PLUGIN_DIR . "/classes/Notification/NolejNotificationProvider.php");

        if (self::$pluginProviderCollection === null) {
            self::$pluginProviderCollection = new PluginProviderCollection();

            // self::$pluginProviderCollection->setMetaBarProvider(new NolejMetaBarProvider($DIC, $this));
            self::$pluginProviderCollection->setNotificationProvider(new NolejNotificationProvider($DIC, $this));
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
            self::TABLE_TIC,
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
     * @param mixed $code
     */
    public function setPermanentLink($code)
    {
        global $tpl;
        $tpl->setPermanentLink(self::PLUGIN_ID, $code);
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
     * Include H5P plugin.
     * @return void
     * @throws LogicException if it is not installed
     */
    public static function includeH5P(): void
    {
        $h5pDirectory = "./Customizing/global/plugins/Services/Repository/RepositoryObject/H5P";
        $h5pAutoloader = $h5pDirectory . "/vendor/autoload.php";
        $h5pPlugin = $h5pDirectory . "/classes/class.ilH5PPlugin.php";

        if (!file_exists($h5pAutoloader) || !file_exists($h5pPlugin)) {
            throw new LogicException("You cannot use this plugin without installing the H5P plugin first.");
        }

        if (!self::isH5PPluginLoaded()) {
            require_once $h5pAutoloader;
            require_once $h5pPlugin;
        }
    }

    /**
     * Check that H5P plugin class is loaded.
     * @return bool
     */
    public static function isH5PPluginLoaded(): bool
    {
        return class_exists("ilH5PPlugin");
    }

    /**
     * Get the HTML of an H5P activity.
     * @param int $contentId
     * @return string html
     */
    public static function renderH5P($contentId): string
    {
        global $DIC;

        $nolej = self::getInstance();
        $renderer = $DIC->ui()->renderer();
        $factory = $DIC->ui()->factory();

        if ($contentId == -1) {
            return $renderer->render(
                $factory
                    ->messageBox()
                    ->failure($nolej->txt("err_h5p_content"))
            );
        }

        self::includeH5P();
        $component_factory = $DIC["component.factory"];
        $h5p_plugin = $component_factory->getPlugin(ilH5PPlugin::PLUGIN_ID);

        /** @var IContainer */
        $h5p_container = $h5p_plugin->getContainer();

        /** @var IRepositoryFactory */
        $repositories = $h5p_container->getRepositoryFactory();

        $DIC->ui()->mainTemplate()->addCss(self::PLUGIN_DIR . "/css/nolej.css");

        $content = $repositories->content()->getContent((int) $contentId);

        $component = (null === $content)
            ? $factory
                ->messageBox()
                ->failure($nolej->txt("err_h5p_content"))
            : $h5p_container
                ->getComponentFactory()
                ->content($content)
                ->withLoadingMessage(
                    ilNolejActivityManagementGUI::glyphicon("refresh gly-spin")
                    . $nolej->txt("content_loading")
                );

        return sprintf(
            "<div style=\"margin-top: 25px;\">%s</div>",
            $renderer->render($component)
        );
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
