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

require_once (ilNolejPlugin::PLUGIN_DIR . "/classes/class.ilNolejConfig.php");
require_once (ilNolejPlugin::PLUGIN_DIR . "/classes/class.ilNolejAPI.php");
require_once (ilNolejPlugin::PLUGIN_DIR . "/classes/class.ilNolejMediaSelectorGUI.php");
require_once (ilNolejPlugin::PLUGIN_DIR . "/classes/Notification/NolejActivity.php");

/**
 * Plugin configuration GUI class
 *
 * @ilCtrl_isCalledBy ilNolejConfigGUI: ilObjComponentSettingsGUI
 */
class ilNolejConfigGUI extends ilPluginConfigGUI
{
    const CMD_CONFIGURE = "configure";
    const CMD_SAVE = "save";
    const CMD_TIC = "tic";
    const CMD_INSERT = "insert";
    const CMD_POST = "selectObjectReference";

    const TAB_CONFIGURE = "configuration";

    /** @var ilCtrl */
    protected ilCtrl $ctrl;

    /** @var string */
    protected $cmd;

    /** @var ilTabsGUI */
    protected ilTabsGUI $tabs;

    /** @var ilDBInterface */
    protected ilDBInterface $db;

    /** @var ilLanguage */
    protected ilLanguage $lng;

    /** @var ilNolejConfig */
    protected $config;

    public function __construct()
    {
        global $DIC, $tpl;
        $this->ctrl = $DIC->ctrl();
        $this->tabs = $DIC->tabs();
        $this->db = $DIC->database();
        $this->lng = $DIC->language();

        $this->lng->loadLanguageModule(ilNolejPlugin::PREFIX);
        $this->config = new ilNolejConfig();

        $tpl->setTitleIcon(ilNolejPlugin::PLUGIN_DIR . "/templates/images/icon_xnlj.svg");
        $tpl->setTitle($this->txt("plugin_title"), false);
    }

    /**
     * @param string $key
     * @return string
     */
    protected function txt(string $key): string
    {
        return $this->config->txt($key);
    }

    /**
     * Handles all commmands,
     * $cmd = functionName()
     */
    public function performCommand(string $cmd): void
    {
        $next_class = $this->ctrl->getNextClass($this);

        if ($cmd != self::CMD_POST) {
            switch ($next_class) {
                case "ilnolejmediaselectorgui":
                    $mediaselectorgui = new ilNolejMediaSelectorGUI($this);
                    $this->ctrl->forwardCommand($mediaselectorgui);
            }
        }

        switch ($cmd) {
            case self::CMD_SAVE:
            // case self::CMD_TIC:
            case self::CMD_INSERT:
            case self::CMD_POST:
                $this->$cmd();
                break;

            case self::CMD_CONFIGURE:
            default:
                $this->configure();
        }
    }

    /**
     * Init and activate tabs
     */
    protected function initTabs($active_tab = null)
    {
        global $tpl;

        $this->tabs->addTab(
            self::TAB_CONFIGURE,
            $this->txt("tab_" . self::TAB_CONFIGURE),
            $this->ctrl->getLinkTarget($this, self::CMD_CONFIGURE)
        );

        switch ($active_tab) {
            case self::TAB_CONFIGURE:
            default:
                $this->tabs->activateTab(self::TAB_CONFIGURE);
                $tpl->setTitle($this->txt("plugin_title") . ": " . $this->txt("tab_" . self::TAB_CONFIGURE), false);
        }

        $tpl->setDescription($this->txt("plugin_description"));
    }

    /**
     * Init configuration form.
     *
     * @return ilPropertyFormGUI form object
     */
    public function initConfigureForm()
    {
        $this->initTabs(self::TAB_CONFIGURE);

        include_once ("Services/Form/classes/class.ilPropertyFormGUI.php");
        $form = new ilPropertyFormGUI();

        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->txt("configuration_title"));
        $form->addItem($section);

        $api_key = new ilTextInputGUI($this->txt("api_key"), "api_key");
        $api_key->setMaxLength(100);
        $api_key->setRequired(true);
        $api_key->setInfo($this->txt("api_key_info"));
        $api_key->setValue($this->config->get("api_key", ""));
        $form->addItem($api_key);

        $form->addCommandButton(self::CMD_SAVE, $this->txt("cmd_save"));
        $form->setFormAction($this->ctrl->getFormAction($this));

        return $form;
    }

    /**
     * Save: update values in DB
     */
    public function save()
    {
        global $tpl;

        $form = $this->initConfigureForm();

        if ($form->checkInput()) {
            $api_key = $form->getInput("api_key");
            $this->config->set("api_key", $api_key);
            $this->ctrl->redirect($this, self::CMD_CONFIGURE);

        } else {
            // input not ok, then
            $form->setValuesByPost();
            $tpl->setContent($form->getHTML());
        }
    }

    /**
     * Configuration screen
     */
    public function configure(
        $a_mob_id = null
    ) {
        global $DIC, $tpl;

        $form = $this->initConfigureForm();

        // $toolbar = new ilToolbarGUI();

        // if ($a_mob_id != null) {
        //     $signedUrl = ilNolejMediaSelectorGUI::getSignedUrl($a_mob_id);
        //     $this->ctrl->setParameter($this, "mediaUrl", urlencode($signedUrl));
        //     $toolbar->addText($signedUrl);
        // }

        // $toolbar->addButton(
        //     $this->txt("cmd_tic"),
        //     $this->ctrl->getLinkTarget($this, self::CMD_TIC)
        // );

        // $mediaselectorgui = new ilNolejMediaSelectorGUI($this);
        // $toolbar->addButton(
        // 	$this->txt("cmd_select"),
        // 	$this->ctrl->getLinkTarget($mediaselectorgui, self::CMD_INSERT)
        // );

        // $input = $mediaselectorgui->getInput();
        // $form->addItem($input);

        $tpl->setContent($form->getHTML());
    }

    /**
     * Summary of insert
     * @return void
     */
    public function insert()
    {
        $mediaselectorgui = new ilNolejMediaSelectorGUI($this);
        $this->ctrl->forwardCommand($mediaselectorgui);
    }

    /**
     * Summary of tic
     * @return void
     */
    public function tic()
    {
        global $DIC;

        $api_key = $this->config->get("api_key", "");
        if ($api_key == "") {
            $this->tpl->setOnScreenMessage("failure", $this->txt("err_api_key_missing"), true);
            $this->configure();
            return;
        }

        $ass = new NolejActivity("tst-" . time(), 6, "tic");
        $ass->withStatus("ok")
            ->withCode(0)
            ->withErrorMessage("")
            ->withConsumedCredit(0)
            ->store();

        $api = new ilNolejAPI($api_key);
        $message = "hello tic";
        $mediaUrl = isset($_GET["mediaUrl"])
            ? urldecode($_GET["mediaUrl"])
            : "http://www.africau.edu/images/default/sample.pdf";
        $webhookUrl = ILIAS_HTTP_PATH . "/goto.php?target=xnlj_webhook";

        $result = $api->post(
            "/tic",
            [
                "message" => $message,
                "s3URL" => $mediaUrl,
                "webhookURL" => $webhookUrl
            ],
            true
        );

        if (
            !is_object($result) ||
            !property_exists($result, "exchangeId") ||
            !is_string($result->exchangeId)
        ) {
            $this->tpl->setOnScreenMessage("failure", $this->txt("err_tic_response") . " " . print_r($result, true), true);
            $this->configure();
            return;
        }

        $now = strtotime("now");
        $DIC->database()->manipulateF(
            "INSERT INTO " . ilNolejPlugin::TABLE_TIC . " (exchange_id, user_id, request_on, message, request_url)"
            . " VALUES (%s, %s, %s, %s, %s);",
            ["text", "integer", "integer", "text", "text"],
            [$result->exchangeId, $DIC->user()->getId(), $now, $message, $webhookUrl]
        );
        $this->tpl->setOnScreenMessage("success", $this->txt("tic_sent"), true);
        $this->configure();
    }

    /**
     * Summary of selectObjectReference
     * @return void
     */
    public function selectObjectReference()
    {
        if (isset($_POST["id"])) {
            $id = $_POST["id"];
            if (is_array($id)) {
                if (count($id) > 0) {
                    $this->configure($id[0]);
                    return;
                }
            } else {
                $this->configure($id);
                return;
            }
        }
        $this->configure();
    }
}
