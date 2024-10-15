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
 * Plugin configuration GUI class
 *
 * @ilCtrl_isCalledBy ilNolejConfigGUI: ilObjComponentSettingsGUI
 */
class ilNolejConfigGUI extends ilPluginConfigGUI
{
    /** @var string */
    public const SECRET = "**********";

    /** @var string */
    public const CMD_CONFIGURE = "configure";

    /** @var string */
    public const CMD_SAVE = "save";

    /** @var string */
    public const TAB_CONFIGURE = "configuration";

    /** @var ilCtrl */
    protected ilCtrl $ctrl;

    /** @var ilTabsGUI */
    protected ilTabsGUI $tabs;

    /** @var ilDBInterface */
    protected ilDBInterface $db;

    /** @var ilLanguage */
    protected ilLanguage $lng;

    /** @var ilNolejPlugin */
    protected $plugin;

    public function __construct()
    {
        global $DIC, $tpl;
        $this->ctrl = $DIC->ctrl();
        $this->tabs = $DIC->tabs();
        $this->db = $DIC->database();
        $this->lng = $DIC->language();

        $this->lng->loadLanguageModule(ilNolejPlugin::PREFIX);
        $this->plugin = ilNolejPlugin::getInstance();

        $tpl->setTitleIcon(ilNolejPlugin::PLUGIN_DIR . "/templates/images/icon_xnlj.svg");
        $tpl->setTitle($this->plugin->txt("plugin_title"), false);

        require_once ilNolejPlugin::PLUGIN_DIR . "/classes/class.ilNolejAPI.php";
    }

    /**
     * Handles all commmands.
     * @param string $cmd
     * @return void
     */
    public function performCommand(string $cmd): void
    {
        switch ($cmd) {
            case self::CMD_SAVE:
                $this->$cmd();
                break;

            case self::CMD_CONFIGURE:
            default:
                $this->configure();
        }
    }

    /**
     * Init and activate tabs.
     * @param ?string $active_tab
     * @return void
     */
    protected function initTabs($active_tab = null)
    {
        global $tpl;

        $this->tabs->addTab(
            self::TAB_CONFIGURE,
            $this->plugin->txt("tab_" . self::TAB_CONFIGURE),
            $this->ctrl->getLinkTarget($this, self::CMD_CONFIGURE)
        );

        switch ($active_tab) {
            case self::TAB_CONFIGURE:
            default:
                $this->tabs->activateTab(self::TAB_CONFIGURE);
                $tpl->setTitle($this->plugin->txt("plugin_title"), false);
        }

        $tpl->setDescription($this->plugin->txt("plugin_description"));
    }

    /**
     * Init configuration form.
     * @return ilPropertyFormGUI form object
     */
    public function initConfigurationForm()
    {
        $this->initTabs(self::TAB_CONFIGURE);

        $form = new ilPropertyFormGUI();

        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->lng->txt("settings"));
        $form->addItem($section);

        // API Key.
        $api_key = new ilPasswordInputGUI($this->plugin->txt("api_key"), "api_key");
        $api_key->setMaxLength(100);
        $api_key->setRetype(false);
        $api_key->setDisableHtmlAutoComplete(true);
        $api_key->setRequired(true);
        $api_key->setInfo($this->plugin->txt("api_key_info"));
        $api_key->setValue(ilNolejAPI::hasKey() ? self::SECRET : ""); // Hide key for security.
        $form->addItem($api_key);

        // Updates interval.
        $interval = new ilNumberInputGUI($this->plugin->txt("config_interval"), "interval");
        $interval->setRequired(true);
        $interval->setInfo($this->plugin->txt("config_interval_info"));
        $interval->setValue($this->plugin->getConfig("interval", "1"));
        $interval->allowDecimals(false);
        $interval->setMinValue(1, true);
        $form->addItem($interval);

        $form->addCommandButton(self::CMD_SAVE, $this->plugin->txt("cmd_save"));
        $form->setFormAction($this->ctrl->getFormAction($this));

        return $form;
    }

    /**
     * Save: update values in DB
     */
    public function save()
    {
        global $DIC;

        $form = $this->initConfigurationForm();

        // Check form input.
        if (!$form->checkInput()) {
            // Input not ok.
            $form->setValuesByPost();
            $DIC->ui()->mainTemplate()->setContent($form->getHTML());
            return;
        }

        // Save API Key.
        $api_key = $form->getInput("api_key");
        if (!empty($api_key) && $api_key != self::SECRET) {
            $this->plugin->saveConfig("api_key", $api_key);
        }

        // Save interval.
        $this->plugin->saveConfig("interval", $form->getInput("interval"));

        $this->ctrl->redirect($this, self::CMD_CONFIGURE);
    }

    /**
     * Configuration screen.
     * @return void
     */
    public function configure()
    {
        global $DIC;

        $form = $this->initConfigurationForm();
        $DIC->ui()->mainTemplate()->setContent($form->getHTML());
    }
}
