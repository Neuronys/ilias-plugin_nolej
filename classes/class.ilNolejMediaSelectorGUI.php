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

/**
 * Select media object
 *
 * @ilCtrl_isCalledBy ilNolejMediaSelectorGUI: ilNolejGUI, ilNolejConfigGUI
 * @ilCtrl_Calls ilNolejMediaSelectorGUI: ilObjMediaObjectGUI
 */

class ilNolejMediaSelectorGUI
{
    // const CMD_INSERT = "insert";
    const CMD_CH_OBJ_REF = "changeObjectReference";
    const CMD_INSERT_FROM_POOL = "insertFromPool";
    const CMD_POOL_SELECTION = "poolSelection";
    const CMD_SELECT_POOL = "selectPool";
    // const CMD_INSERT_NEW = "insertNew";

    const CMD_APPLY_FILTER = "applyFilter";
    const CMD_RESET_FILTER = "resetFilter";

    // const TAB_NEW = "new_media";
    const TAB_INSERT_FROM_POOL = "insert_from_pool";

    /** @var ilNolejGUI|ilNolejConfigGUI */
    protected $guiObj;

    /** @var ilCtrl */
    protected ilCtrl $ctrl;

    /** @var string */
    protected string $cmd;

    /** @var string */
    protected string $subCmd;

    /** @var string */
    protected string $pool_view = "";

    /** @var ilTabsGUI */
    protected ilTabsGUI $tabs;

    /** @var ilDBInterface */
    protected ilDBInterface $db;

    /** @var ilAccessHandler */
    protected ilAccessHandler $access;

    /** @var \ILIAS\DI\UIServices */
    protected \ILIAS\DI\UIServices $ui;

    /** @var ilToolbarGUI */
    protected ilToolbarGUI $toolbar;

    /** @var ilLanguage */
    protected ilLanguage $lng;

    /** @var ilNolejConfig */
    protected $config;

    /**
     * @param ilNolejGUI|ilNolejConfigGUI $guiObj
     */
    public function __construct(
        $guiObj
    ) {
        global $DIC;
        $this->guiObj = $guiObj;
        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $this->tabs = $DIC->tabs();
        $this->db = $DIC->database();
        $this->access = $DIC->access();
        $this->ui = $DIC->ui();
        $this->toolbar = $DIC->toolbar();

        $this->subCmd = $_GET["subCmd"] ?? "";
        $this->pool_view = "folder";
        $pv = $_GET["pool_view"] ?? "";
        if (in_array($pv, array("folder", "all"))) {
            $this->pool_view = $pv;
        }
        $this->ctrl->saveParameter($this, ["pool_view"]);

        $this->config = new ilNolejConfig();
    }

    /**
     * @param string $key
     * @return string
     */
    protected function txt(string $key): string
    {
        return $this->config->txt($key);
    }

    public function getInput()
    {
        ilModalGUI::initJS();

        $link = $this->ctrl->getLinkTarget($this);

        $number = new ilNumberInputGUI("mob_id");

        $f = $this->ui->factory();
        $r = $this->ui->renderer();

        $modal = $f
            ->modal()
            ->roundtrip('---', [])
            ->withAsyncRenderUrl($link);

        $select = $f
            ->button()
            ->shy($this->txt("delete"), '')
            ->withOnClick($modal->getShowSignal());

        $input = new ilCustomInputGUI($this->txt("media_select"));
        $input->setHtml($number->render() . $r->render($modal) . $r->render($select));
        return $input;
    }

    /**
     * @return mixed
     * @throws ilCtrlException
     */
    public function executeCommand(): void
    {
        global $tpl;

        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd() ?? self::CMD_INSERT_FROM_POOL;
        $tpl->setTitleIcon(ilUtil::getImagePath("icon_mob.svg"));

        switch ($next_class) {
            // case "ilobjmediaobjectgui":
            //     // $tpl->setTitle(
            // 	// 	$this->lng->txt("mob") . ": " . $this->content_obj->getMediaObject()->getTitle()
            // 	// );
            //     $mob_gui = new ilObjMediaObjectGUI(
            // 		"",
            // 		0, //$this->content_obj->getMediaObject()->getId(),
            // 		false,
            // 		false
            // 	);
            //     $mob_gui->setBackTitle("Back"); // $this->page_back_title);
            //     $mob_gui->setEnabledMapAreas(false);
            //     $this->ctrl->forwardCommand($mob_gui);
            //     break;

            default:
                $this->lng->loadLanguageModule("content");
                switch ($cmd) {
                    case self::CMD_CH_OBJ_REF:
                    case self::CMD_POOL_SELECTION:
                    case self::CMD_SELECT_POOL:
                        // case self::CMD_INSERT_NEW:
                        $this->$cmd();
                        break;
                    // case self::CMD_INSERT:
                    case self::CMD_INSERT_FROM_POOL:
                    default:
                        // $this->insert();
                        $this->insertFromPool();
                }
        }
    }

    /**
     * Init and activate tabs
     */
    protected function initTabs($active_tab = null)
    {
        global $tpl;

        // $this->tabs->addTab(
        // 	self::TAB_NEW,
        // 	$this->txt("tab_" . self::TAB_NEW),
        // 	$this->ctrl->getLinkTarget($this, self::CMD_INSERT_NEW)
        // );

        $this->tabs->addTab(
            self::TAB_INSERT_FROM_POOL,
            $this->txt("tab_" . self::TAB_INSERT_FROM_POOL),
            $this->ctrl->getLinkTarget($this, self::CMD_INSERT_FROM_POOL)
        );

        switch ($active_tab) {
            case self::TAB_INSERT_FROM_POOL:
            default:
                $this->tabs->activateTab(self::TAB_INSERT_FROM_POOL);
                $tpl->setTitle($this->txt("plugin_title") . ": " . $this->txt("tab_" . self::TAB_INSERT_FROM_POOL), false);
                break;

            // case self::TAB_NEW:
            // default:
            // 	$this->tabs->activateTab(self::TAB_NEW);
            // 	$tpl->setTitle($this->txt("plugin_title") . ": " . $this->txt("tab_" . self::TAB_NEW), false);
        }

        $tpl->setDescription($this->txt("plugin_description"));
    }

    // public function insert(
    // 	$a_post_cmd = "edpost",
    // 	$a_submit_cmd = "create_mob",
    // 	$a_input_error = false
    // ): void {
    // 	$subCmd = $this->subCmd;

    // 	// if (in_array($subCmd, ["insertNew", "insertFromPool"])) {
    // 	// 	$this->edit_repo->setSubCmd($subCmd);
    // 	// }

    // 	// if (($subCmd == "") && $this->edit_repo->getSubCmd() != "") {
    // 	// 	$subCmd = $this->edit_repo->getSubCmd();
    // 	// }

    // 	switch ($subCmd) {
    // 		case self::CMD_INSERT_FROM_POOL:
    // 		case self::CMD_POOL_SELECTION:
    // 		case self::CMD_SELECT_POOL:
    // 		case self::CMD_APPLY_FILTER:
    // 		case self::CMD_RESET_FILTER:
    // 			$this->$subCmd();
    // 			break;

    // 		case self::CMD_INSERT_NEW:
    // 		default:
    // 			$this->insertNew();
    // 			break;
    // 	}
    // }

    /**
     * Change object reference
     */
    public function changeObjectReference(): void
    {
        $subCmd = $this->subCmd;

        // if (in_array($subCmd, ["insertNew", "insertFromPool"])) {
        // 	$this->edit_repo->setSubCmd($subCmd);
        // }

        // if (($subCmd == "") && $this->edit_repo->getSubCmd() != "") {
        // 	$subCmd = $this->edit_repo->getSubCmd();
        // }

        switch ($subCmd) {
            case self::CMD_POOL_SELECTION:
            case self::CMD_SELECT_POOL:
                $this->$subCmd(true);
                break;

            // case self::CMD_INSERT_NEW:
            case self::CMD_INSERT_FROM_POOL:
            default:
                // $this->insertNew(true);
                $this->insertFromPool();
        }
    }

    /**
     * Create new media object
     */
    // public function insertNew(
    // 	bool $a_change_obj_ref = false
    // ) : void
    // {
    // 	global $tpl;

    // 	$this->initTabs(self::TAB_NEW);

    // 	$mob_gui = new ilObjMediaObjectGUI("");
    // 	$mob_gui->initForm("create");
    // 	$form = $mob_gui->getForm();
    // 	$form->clearCommandButtons();

    // 	if ($a_change_obj_ref) {
    // 		$this->ctrl->setParameter($this, "subCmd", self::CMD_CH_OBJ_REF);
    // 		$form->setFormAction($this->ctrl->getFormAction($this));
    // 		$form->addCommandButton("createNewObjectReference", $this->txt("save"));
    // 	} else {
    // 		$form->setFormAction($this->ctrl->getFormAction($this, "create_mob"));
    // 		$form->addCommandButton("create_mob", $this->txt("save"));
    // 	}

    // 	$form->addCommandButton("cancelCreate", $this->txt("cancel"));
    // 	$tpl->setContent($form->getHTML());
    // }

    /**
     * Insert media object from pool
     */
    public function insertFromPool(
        bool $a_change_obj_ref = false
    ): void {
        global $tpl;

        $mediaPoolId = (int) $_GET["pool_ref_id"] ?? 0;

        if (
            $mediaPoolId <= 0 ||
            !$this->access->checkAccess("write", "", $mediaPoolId) ||
            ilObject::_lookupType(ilObject::_lookupObjId($mediaPoolId)) != "mep"
        ) {
            // Cannot access to the selected mediapool
            $this->poolSelection($a_change_obj_ref);
            return;
        }

        $this->initTabs(self::TAB_INSERT_FROM_POOL);
        $html = "";
        $toolbar = new ilToolbarGUI();

        // button: select pool
        $this->ctrl->setParameter($this, "subCmd", self::CMD_POOL_SELECTION);
        $this->ctrl->setParameter($this, "pool_ref_id", $mediaPoolId);
        if ($a_change_obj_ref) {
            $toolbar->addButton(
                $this->lng->txt("cont_switch_to_media_pool"),
                $this->ctrl->getLinkTarget($this, self::CMD_CH_OBJ_REF)
            );
        } else {
            $toolbar->addButton(
                $this->lng->txt("cont_switch_to_media_pool"),
                $this->ctrl->getLinkTarget($this, self::CMD_INSERT_FROM_POOL)
            );
        }

        $this->ctrl->setParameter($this, "subCmd", "");
        // $this->ctrl->setParameter($this, "pool_ref_id", null);

        // view mode: pool view (folders/all media objects)
        $f = $this->ui->factory();
        $tcmd = ($a_change_obj_ref)
            ? self::CMD_CH_OBJ_REF
            : self::CMD_INSERT_FROM_POOL;

        $this->lng->loadLanguageModule("mep");

        $this->ctrl->setParameter($this, "pool_view", "folder");
        $actions[$this->lng->txt("folders")] = $this->ctrl->getLinkTarget($this, $tcmd);

        $this->ctrl->setParameter($this, "pool_view", "all");
        $actions[$this->lng->txt("mep_all_mobs")] = $this->ctrl->getLinkTarget($this, $tcmd);

        $this->ctrl->setParameter($this, "pool_view", $this->pool_view);
        $aria_label = $this->lng->txt("cont_change_pool_view");
        $view_control = $f
            ->viewControl()
            ->mode($actions, $aria_label)
            ->withActive(
                ($this->pool_view == "folder")
                ? $this->lng->txt("folders")
                : $this->lng->txt("mep_all_mobs")
            );
        $toolbar->addSeparator();
        $toolbar->addComponent($view_control);

        $html = $toolbar->getHTML();

        $pool = new ilObjMediaPool($mediaPoolId);

        $this->ctrl->setParameter($this, "subCmd", self::CMD_INSERT_FROM_POOL);
        $tcmd = ($a_change_obj_ref)
            ? self::CMD_CH_OBJ_REF
            : self::CMD_INSERT_FROM_POOL;

        $mpool_table = new ilMediaPoolTableGUI(
            $this,
            $tcmd,
            $pool,
            "mep_folder",
            ilMediaPoolTableGUI::IL_MEP_SELECT_SINGLE,
            $this->pool_view == "all"
        );

        // handle table sub commands and get the table
        switch ($this->subCmd) {
            case self::CMD_APPLY_FILTER:
                $mpool_table->resetOffset();
                $mpool_table->writeFilterToSession();
                break;

            case self::CMD_RESET_FILTER:
                $mpool_table->resetOffset();
                $mpool_table->resetFilter();
                break;
        }

        $html .= $mpool_table->getHTML();
        $tpl->setContent($html);
    }

    /**
     * Select concrete pool
     */
    public function selectPool(
        bool $a_change_obj_ref = false
    ): void {
        $this->ctrl->setParameter($this, "pool_ref_id", $_GET["pool_ref_id"]);
        $this->ctrl->setParameter($this, "subCmd", self::CMD_INSERT_FROM_POOL);
        if ($a_change_obj_ref) {
            $this->ctrl->redirect($this, self::CMD_CH_OBJ_REF);
        } else {
            $this->ctrl->redirect($this, self::CMD_INSERT_FROM_POOL);
        }
    }

    /**
     * Pool Selection
     */
    public function poolSelection(
        bool $a_change_obj_ref = false
    ): void {
        global $tpl;

        $this->initTabs(self::TAB_INSERT_FROM_POOL);

        $this->ctrl->setParameter($this, "subCmd", self::CMD_POOL_SELECTION);
        if ($a_change_obj_ref) {
            $exp = new ilPoolSelectorGUI($this, self::CMD_CH_OBJ_REF, $this, self::CMD_CH_OBJ_REF);
        } else {
            $exp = new ilPoolSelectorGUI($this, self::CMD_INSERT_FROM_POOL);
        }

        // filter
        $exp->setTypeWhiteList(array("root", "cat", "grp", "fold", "crs", "mep"));
        $exp->setClickableTypes(array("mep"));

        if (!$exp->handleCommand()) {
            $tpl->setContent($exp->getHTML());
        }
    }

    public static function getObjId($a_mob_id): int
    {
        global $DIC;
        $db = $DIC->database();

        $sql = "SELECT foreign_id FROM mep_item WHERE obj_id = %s;";
        $result = $db->queryF($sql, ["integer"], [$a_mob_id]);
        if ($row = $db->fetchAssoc($result)) {
            return (int) $row["foreign_id"];
        }
        return 0;
    }

    public static function getSignedUrl($a_mob_id, bool $a_is_obj_id = false, int $ttl = 10): string
    {
        $objId = $a_is_obj_id ? $a_mob_id : self::getObjId($a_mob_id);
        $path = ilObjMediaObject::_lookupItemPath($objId);

        $tokenMaxLifetimeInSeconds = ilWACSignedPath::getTokenMaxLifetimeInSeconds();
        ilWACSignedPath::setTokenMaxLifetimeInSeconds($ttl);

        $url = ILIAS_HTTP_PATH . substr(ilWACSignedPath::signFile($path), 1);

        ilWACSignedPath::setTokenMaxLifetimeInSeconds($tokenMaxLifetimeInSeconds);
        return $url;
    }
}
