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

use ILIAS\DI\Container;
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
    const CNAME = "Repository";
    const SLOT_ID = "robj";
    const PREFIX = "rep_robj_xnlj";

    const TABLE_CONFIG = "rep_robj_xnlj_config";
    const TABLE_ACTIVITY = "rep_robj_xnlj_activity";
    const TABLE_TIC = "rep_robj_xnlj_tic";
    const TABLE_DOC = "rep_robj_xnlj_doc";
    const TABLE_DATA = "rep_robj_xnlj_data";
    const TABLE_H5P = "rep_robj_xnlj_hfp";
    const TABLE_LP = "rep_robj_xnlj_lp";

    /** @var PluginProviderCollection|null */
    protected static $pluginProviderCollection = null;


    /**
     * Initialize plugin
     */
    public function init(): void
    {
        global $DIC;

        $this->provider_collection = $this->getPluginProviderCollection(); // Fix overflow

        $DIC->language()->loadLanguageModule(self::PREFIX);
    }

    /**
     * @return PluginProviderCollection
     */
    protected function getPluginProviderCollection()
    {
        global $DIC;

        if (!isset($DIC["global_screen"])) {
            return $this->provider_collection;
        }

        require_once (self::PLUGIN_DIR . "/classes/MainBar/NolejMainBarProvider.php");
        require_once (self::PLUGIN_DIR . "/classes/Notification/NolejNotificationProvider.php");
        if (self::$pluginProviderCollection === null) {
            self::$pluginProviderCollection = new PluginProviderCollection();

            // self::$pluginProviderCollection->setMetaBarProvider(self::helpMe()->metaBar());
            // self::$pluginProviderCollection->setMainBarProvider(new NolejMainBarProvider($DIC, $this));
            self::$pluginProviderCollection->setNotificationProvider(new NolejNotificationProvider($DIC, $this));
        }

        return self::$pluginProviderCollection;
    }

    /**
     * Must correspond to the plugin subdirectory
     * @return string
     */
    public function getPluginName()
    {
        return self::PLUGIN_NAME;
    }

    /**
     * Returns a list of all repository object types which can be a parent of this type.
     * @return array
     */
    public function getParentTypes()
    {
        $par_types = array("root", "cat", "crs", "grp", "fold", "lso", "prg");
        return $par_types;
    }

    protected function afterActivation()
    {
    }

    protected function uninstallCustom()
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
     * @inheritdoc
     * @return bool
     */
    public function allowCopy()
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
     * @return string
     */
    public function getConfigurationLink()
    {
        global $DIC;
        include_once (self::PLUGIN_DIR . "/classes/class.ilNolejConfigGUI.php");

        return sprintf(
            "%s&ref_id=31&admin_mode=settings&ctype=Services&cname=%s&slot_id=%s&pname=%s",
            $DIC->ctrl()->getLinkTargetByClass(
                ["ilAdministrationGUI", "ilObjComponentSettingsGUI", ilNolejConfigGUI::class],
                ilNolejConfigGUI::CMD_CONFIGURE
            ),
            self::CNAME,
            self::SLOT_ID,
            self::PLUGIN_NAME
        );
    }

}
