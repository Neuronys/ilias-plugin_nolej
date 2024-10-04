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
 * Form GUI class.
 */
class ilNolejFormGUI
{
    public const CMD_SHOW = "showForm";
    public const CMD_SAVE = "saveForm";

    /** @var ilCtrl */
    protected ilCtrl $ctrl;

    /** @var ilLanguage */
    protected ilLanguage $lng;

    /** @var ilDBInterface */
    protected ilDBInterface $db;

    /** @var ilGlobalPageTemplate */
    protected ilGlobalPageTemplate $tpl;

    /** @var ilNolejPlugin */
    protected $plugin;

    /** @var ilObjNolejGUI */
    protected $obj_gui;

    /** @var string */
    protected string $documentId = "";

    /** @var int */
    protected int $status = 0;

    /**
     * @param ilObjNolejGUI $obj_gui
     */
    public function __construct($obj_gui)
    {
        global $DIC;
        $this->ctrl = $DIC->ctrl();
        $this->db = $DIC->database();
        $this->lng = $DIC->language();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->plugin = ilNolejPlugin::getInstance();

        $this->obj_gui = $obj_gui;
        $this->documentId = $this->obj_gui->getObject()->getDocumentId();
        $this->status = $this->obj_gui->getObject()->getDocumentStatus();

        ilNolejPlugin::includeH5P();
    }

    /**
     * Handles incoming commmands.
     *
     * @throws ilException if command is not known
     * @return void
     */
    public function executeCommand(): void
    {
    }

    /**
     * Show form.
     * @return void
     */
    public function showForm(): void
    {
    }

    /**
     * Save form.
     * @return void
     */
    public function saveForm(): void
    {
    }

    /**
     * Init form.
     * @return ilPropertyFormGUI
     */
    protected function form(): ilPropertyFormGUI
    {
        return new ilPropertyFormGUI();
    }
}
