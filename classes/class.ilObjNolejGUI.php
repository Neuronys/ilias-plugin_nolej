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

use ILIAS\UI\Component\Listing\Workflow\Step;
use ILIAS\GlobalScreen\Scope\MainMenu\Collector\Renderer\Hasher;

/**
 * @ilCtrl_isCalledBy ilObjNolejGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls ilObjNolejGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI
 * @ilCtrl_Calls ilObjNolejGUI: ilNolejActivityManagementGUI
 */
class ilObjNolejGUI extends ilObjectPluginGUI
{
    use Hasher;

    const LP_SESSION_ID = "xnlj_lp_session_state";

    const CMD_PROPERTIES_EDIT = "editProperties";
    const CMD_PROPERTIES_UPDATE = "updateProperties";
    const CMD_PROPERTIES_SAVE = "saveProperties";
    const CMD_CONTENT_SHOW = "showContent";
    const CMD_CONTENT_EDIT = "editContent";

    const TAB_PROPERTIES = "properties";
    const TAB_CONTENT = "content";
    const TAB_ACTIVITY_MANAGEMENT = "activity_management";

    const PROP_TITLE = "title";
    const PROP_DESCRIPTION = "description";
    const PROP_ONLINE = "online";

    /** @var ilCtrl */
    protected ilCtrl $ctrl;

    /** @var ilTabsGUI */
    protected ilTabsGUI $tabs;

    /** @var ilGlobalTemplateInterface */
    public ilGlobalTemplateInterface $tpl;

    /** @var string */
    protected string $selectedType = "";

    /**
     * Initialization.
     * @return void
     */
    protected function afterConstructor(): void
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->tabs = $DIC->tabs();
        $this->tpl = $DIC->ui()->mainTemplate();
    }

    /**
     * Get object type.
     * @return string
     */
    final public function getType(): string
    {
        return ilNolejPlugin::PLUGIN_ID;
    }

    /**
     * Handles all commmands of this class, centralizes permission checks.
     * @param string $cmd
     * @return void
     */
    public function performCommand(string $cmd): void
    {
        $nextClass = $this->ctrl->getNextClass();
        switch ($nextClass) {
            case strtolower(ilNolejActivityManagementGUI::class):
                $this->checkPermission("write");
                $this->setContentSubTabs(self::TAB_ACTIVITY_MANAGEMENT);
                $activityManagement = new ilNolejActivityManagementGUI($this);
                $this->ctrl->forwardCommand($activityManagement);
                break;

            default:
                switch ($cmd) {
                    // Need write permission.
                    case self::CMD_PROPERTIES_EDIT:
                    case self::CMD_PROPERTIES_UPDATE:
                    case self::CMD_PROPERTIES_SAVE:
                    case self::CMD_CONTENT_EDIT:
                        $this->checkPermission("write");
                        $this->$cmd();
                        break;

                    // Need read permission.
                    case self::CMD_CONTENT_SHOW:
                        $this->checkPermission("read");
                        $this->$cmd();
                        break;

                    default:
                        $this->ctrl->redirect($this, $this->getStandardCmd());
                }
        }
    }

    /**
     * After object has been created -> jump to this command
     * @return string
     */
    public function getAfterCreationCmd(): string
    {
        return self::CMD_CONTENT_EDIT;
    }

    /**
     * Get standard command
     * @return string
     */
    public function getStandardCmd(): string
    {
        return self::CMD_CONTENT_SHOW;
    }

    /**
     * Operations to do after object save.
     * @param ilObject $a_new_object
     * @return void
     */
    public function afterSave(ilObject $a_new_object): void
    {
        $parent_data = $this->tree->getParentNodeData($a_new_object->getRefId());
        $a_new_object->setPermissions($parent_data["ref_id"]);
        parent::afterSave($a_new_object);
    }

    /**
     * This object does not support export.
     * @return bool
     */
    protected function supportsExport(): bool
    {
        return false;
    }

    /**
     * This object does not support cloning.
     * @return bool
     */
    protected function supportsCloning(): bool
    {
        return false;
    }

    /**
     * Set object tabs.
     * @return void
     */
    protected function setTabs(): void
    {
        // tab for the "show content" command
        if ($this->object->hasReadPermission()) {
            $this->tabs->addTab(
                self::TAB_CONTENT,
                $this->txt("tab_" . self::TAB_CONTENT),
                $this->ctrl->getLinkTarget($this, self::CMD_CONTENT_SHOW)
            );
        }

        // Standard info screen tab.
        $this->addInfoTab();

        // Properties tabs.
        if ($this->object->hasWritePermission()) {
            $this->tabs->addTab(
                self::TAB_PROPERTIES,
                $this->txt("tab_" . self::TAB_PROPERTIES),
                $this->ctrl->getLinkTarget($this, self::CMD_PROPERTIES_EDIT)
            );
        }

        // Standard permission tab.
        $this->addPermissionTab();
    }

    /**
     * Set content sub tabs.
     * @param string $active_subtab
     * @return void
     */
    protected function setContentSubTabs($active_subtab = ""): void
    {
        $this->tabs->activateTab(self::TAB_CONTENT);

        if (!$this->checkPermissionBool("write")) {
            // Nothing to add.
        }

        $this->tabs->addSubTab(
            self::TAB_CONTENT,
            $this->txt("tab_" . self::TAB_CONTENT),
            $this->ctrl->getLinkTarget($this, self::CMD_CONTENT_SHOW)
        );

        $this->tabs->addSubTab(
            self::TAB_ACTIVITY_MANAGEMENT,
            $this->txt("tab_" . self::TAB_ACTIVITY_MANAGEMENT),
            $this->ctrl->getLinkTargetByClass(ilNolejActivityManagementGUI::class)
        );

        switch ($active_subtab) {
            case self::TAB_ACTIVITY_MANAGEMENT:
                $this->tabs->activateSubTab(self::TAB_ACTIVITY_MANAGEMENT);
                break;

            default:
                $this->tabs->activateSubTab(self::TAB_CONTENT);
        }
    }

    /**
     * Add items to info screen.
     * @param ilInfoScreenGUI $info
     * @return void
     */
    public function addInfoItems(ilInfoScreenGUI $info): void
    {
        $info->addSection($this->plugin->txt("plugin_title"));
        $info->addProperty(
            $this->plugin->txt("document_id"),
            $this->object->getDocumentId()
        );
    }

    /**
     * Edit Properties. This commands uses the form class to display an input form.
     * @return void
     */
    protected function editProperties(): void
    {
        $form = $this->initPropertiesForm();
        $this->addValuesToForm($form);
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Properties form of this object.
     * @return ilPropertyFormGUI
     */
    protected function initPropertiesForm(): ilPropertyFormGUI
    {
        $this->tabs->activateTab(self::TAB_PROPERTIES);

        $form = new ilPropertyFormGUI();
        $form->setTitle($this->plugin->txt("obj_xnlj"));

        $title = new ilTextInputGUI($this->plugin->txt("prop_" . self::PROP_TITLE), self::PROP_TITLE);
        $title->setRequired(true);
        $form->addItem($title);

        $description = new ilTextInputGUI($this->plugin->txt("prop_" . self::PROP_DESCRIPTION), self::PROP_DESCRIPTION);
        $form->addItem($description);

        $online = new ilCheckboxInputGUI($this->plugin->txt("prop_" . self::PROP_ONLINE), self::PROP_ONLINE);
        $form->addItem($online);

        $form->setFormAction($this->ctrl->getFormAction($this, self::CMD_PROPERTIES_SAVE));
        $form->addCommandButton(self::CMD_PROPERTIES_SAVE, $this->plugin->txt("cmd_update"));

        return $form;
    }

    /**
     * Add form values.
     * @param ilPropertyFormGUI $form
     * @return void
     */
    protected function addValuesToForm(&$form): void
    {
        $form->setValuesByArray([
            self::PROP_TITLE => $this->object->getTitle(),
            self::PROP_DESCRIPTION => $this->object->getDescription(),
            self::PROP_ONLINE => $this->object->isOnline(),
        ]);
    }

    /**
     * Save properties of the object.
     * @return void
     */
    protected function saveProperties(): void
    {
        $form = $this->initPropertiesForm();
        $form->setValuesByPost();
        if ($form->checkInput()) {
            $this->fillObject($this->object, $form);
            $this->object->update();
            $this->tpl->setOnScreenMessage("success", $this->plugin->txt("update_successful"), true);
            $this->ctrl->redirect($this, self::CMD_PROPERTIES_EDIT);
        }
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Print the activities list.
     * @return void
     */
    protected function printContentMenu(): void
    {
        global $DIC;

        $db = $DIC->database();
        $workflow = $DIC->ui()->factory()->listing()->workflow();
        $renderer = $DIC->ui()->renderer();

        $result = $db->queryF(
            "SELECT * FROM " . ilNolejPlugin::TABLE_H5P
            . " WHERE document_id = %s"
            . " AND `generated` = ("
            . " SELECT MAX(`generated`) FROM " . ilNolejPlugin::TABLE_H5P
            . " WHERE document_id = %s"
            . ") ORDER BY (type = 'ibook') DESC, type ASC;",
            ["text", "text"],
            [$this->object->getDocumentId(), $this->object->getDocumentId()]
        );

        $steps = [];
        $indexes = [];

        $i = 0;
        while ($row = $db->fetchAssoc($result)) {
            if ($i == 0) {
                $this->selectedType = $row["type"];
            }

            $this->ctrl->setParameter($this, "type", $row["type"]);
            $steps[] = $workflow->step(
                $this->txt("activities_" . $row["type"]),
                "",
                $this->ctrl->getLinkTarget($this, self::CMD_CONTENT_SHOW)
            )
                ->withAvailability(Step::AVAILABLE)
                ->withStatus(Step::IN_PROGRESS);

            $indexes[$row["type"]] = $i++;
        }

        $selector = $workflow->linear($this->txt("tab_activities"), $steps);
        if (isset($_GET["type"]) && array_key_exists($_GET["type"], $indexes)) {
            $this->selectedType = $_GET["type"];
            $selector = $selector->withActive($indexes[$_GET["type"]]);
        }
        $this->tpl->setRightContent($renderer->render($selector));
    }

    /**
     * Show the object content.
     * @return void
     */
    public function showContent(): void
    {
        global $DIC;

        $renderer = $DIC->ui()->renderer();
        $factory = $DIC->ui()->factory();

        $this->setContentSubTabs();

        if ($this->object->getDocumentStatus() != ilNolejActivityManagementGUI::STATUS_COMPLETED) {
            $this->tpl->setOnScreenMessage("info", $this->plugin->txt("activities_not_yet_generated"));
            return;
        }

        $h5pDir = $this->object->getDataDir() . "/h5p";
        if (!is_dir($h5pDir)) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("activities_not_downloaded"));
            return;
        }

        $this->printContentMenu();
        if (empty($this->selectedType)) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("activities_not_downloaded"));
            return;
        }

        $contentId = $this->object->getContentIdOfType($this->selectedType);

        // Display activities.
        $this->tpl->setContent(
            ($contentId != -1)
            ? $this->getH5PHtml($contentId)
            : $renderer->render(
                $factory
                    ->messageBox()
                    ->failure($this->plugin->txt("err_h5p_content"))
            )
        );
    }

    /**
     * Redirect to creation workflow.
     * @return void
     */
    public function editContent(): void
    {
        $this->ctrl->redirectByClass(ilNolejActivityManagementGUI::class);
    }

    /**
     * Get the HTML of an H5P activity.
     * @param int $contentId
     * @return string html
     */
    public static function getH5PHtml($contentId): string
    {
        global $DIC, $tpl;

        $nolej = ilNolejPlugin::getInstance();
        $renderer = $DIC->ui()->renderer();
        $factory = $DIC->ui()->factory();

        ilNolejPlugin::includeH5P();
        $component_factory = $DIC["component.factory"];
        $h5p_plugin = $component_factory->getPlugin(ilH5PPlugin::PLUGIN_ID);

        /** @var IContainer */
        $h5p_container = $h5p_plugin->getContainer();

        /** @var IRepositoryFactory */
        $repositories = $h5p_container->getRepositoryFactory();

        $tpl->addCss(ilNolejPlugin::PLUGIN_DIR . "/css/nolej.css");

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
     * @param $object ilObjNolej
     * @param $form ilPropertyFormGUI
     */
    private function fillObject($object, $form)
    {
        $object->setTitle($form->getInput(self::PROP_TITLE));
        $object->setDescription($form->getInput(self::PROP_DESCRIPTION));
        $object->setOnline($form->getInput(self::PROP_ONLINE));
    }

    /**
     * Goto redirection.
     * @param array $a_target
     * @return void
     */
    public static function _goto(array $a_target): void
    {
        $target = $a_target[0];

        include_once (ilNolejPlugin::PLUGIN_DIR . "/classes/class.ilNolejWebhook.php");

        if ($target == "webhook") {
            $webhook = new ilNolejWebhook();
            $webhook->parse();
            exit;
        }

        parent::_goto($a_target);
    }
}
