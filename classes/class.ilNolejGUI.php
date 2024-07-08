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

require_once ("./Customizing/global/plugins/Services/Repository/RepositoryObject/Nolej/classes/class.ilNolejConfig.php");

/**
 * Plugin GUI class
 *
 * @ilCtrl_isCalledBy ilNolejGUI: ilUIPluginRouterGUI
 */
class ilNolejGUI
{
    const CMD_SHOW_MODULES = "showModules";
    const CMD_FILTER_APPLY = "applyFilter";
    const CMD_FILTER_RESET = "resetFilter";

    const TAB_MODULES = "modules";

    /** @var ilCtrl */
    protected ilCtrl $ctrl;

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

    /**
     * Handles all commmands,
     * $cmd = functionName()
     */
    public function executeCommand(): void
    {
        global $tpl;
        $cmd = ($this->ctrl->getCmd()) ? $this->ctrl->getCmd() : self::CMD_SHOW_MODULES;

        $tpl->loadStandardTemplate();
        $tpl->setTitleIcon(ilNolejPlugin::PLUGIN_DIR . "/templates/images/icon_xnlj.svg");
        $tpl->setTitle($this->txt("plugin_title"), false);

        switch ($cmd) {
            // Need to have permission to access modules
            case self::CMD_SHOW_MODULES:
            case self::CMD_FILTER_APPLY:
            case self::CMD_FILTER_RESET:
                $this->$cmd();
                break;

            default:
                $this->showModules();
        }

        $tpl->printToStdout();
    }

    /**
     * Init and activate tabs
     */
    protected function initTabs($active_tab = null)
    {
        global $tpl;

        $this->tabs->addTab(
            self::TAB_MODULES,
            $this->txt("tab_" . self::TAB_MODULES),
            $this->ctrl->getLinkTarget($this, self::CMD_SHOW_MODULES)
        );

        switch ($active_tab) {
            case self::TAB_MODULES:
                $this->tabs->activateTab($active_tab);
                $tpl->setTitle($this->txt("plugin_title") . ": " . $this->txt("tab_" . $active_tab), false);
                break;

            default:
                $this->tabs->activateTab(self::TAB_MODULES);
                $tpl->setTitle($this->txt("plugin_title") . ": " . $this->txt("tab_" . self::TAB_MODULES), false);
        }

        $tpl->setDescription($this->txt("plugin_description"));
    }

    protected function getPermalinkGUI($idPartner, $idCourse)
    {
        $tpl = new ilTemplate(
            "tpl.permanent_link.html",
            true,
            true,
            "Services/PermanentLink"
        );

        include_once ('./Services/Link/classes/class.ilLink.php');
        $href = $this->config->getPermalink($idPartner, $idCourse);

        $tpl->setVariable("LINK", $href);
        $tpl->setVariable("ALIGN", "left");

        return $tpl->get();
    }

    /**
     * @return string
     */
    public function buildIcon($id, $alt = "")
    {
        return '<img border="0" align="middle"'
            . ' src="' . ilUtil::getImagePath($id . ".svg") . '"'
            . ' alt="' . ($alt == "" ? "" : $this->lng->txt($alt)) . '" /> ';
    }

    public function showModules()
    {
        // TODO
    }

    /**
     * Apply filter
     */
    public function applyFilter()
    {
        $this->config->applyFilter();
        $this->showModules();
    }

    /**
     * Reset filter
     */
    public function resetFilter()
    {
        $this->config->resetFilter();
        $this->showModules();
    }

    public function addUserAutoComplete()
    {
        // include_once './Services/User/classes/class.ilUserAutoComplete.php';
        // $auto = new ilUserAutoComplete();
        // $auto->addUserAccessFilterCallable([$this, 'filterUserIdsByRbacOrPositionOfCurrentUser']);
        // $auto->setSearchFields(array(
        // 	'login',
        // 	'firstname',
        // 	'lastname',
        // 	'email')); // , 'second_email'
        // $auto->enableFieldSearchableCheck(false);
        // $auto->setMoreLinkAvailable(true);
        //
        // if (($_REQUEST['fetchall'])) {
        // 	$auto->setLimit(ilUserAutoComplete::MAX_ENTRIES);
        // }
        //
        // echo $auto->getList($_REQUEST['term']);
        // exit();
    }

    /**
     * @param int[] $user_ids
     */
    public function filterUserIdsByRbacOrPositionOfCurrentUser($user_ids = [])
    {
        // global $DIC;
        // $access = $DIC->access();
        //
        // return $access->filterUserIdsByRbacOrPositionOfCurrentUser(
        // 	'read_users',
        // 	\ilObjUserFolder::ORG_OP_EDIT_USER_ACCOUNTS,
        // 	USER_FOLDER_ID,
        // 	$user_ids
        // );
    }
}
