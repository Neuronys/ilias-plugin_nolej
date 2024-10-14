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
    /** @var string */
    public const CMD_SHOW = "showForm";

    /** @var string */
    public const CMD_SAVE = "saveForm";

    /** @var ilCtrl */
    protected ilCtrl $ctrl;

    /** @var ilLanguage */
    protected ilLanguage $lng;

    /** @var ilDBInterface */
    protected ilDBInterface $db;

    /** @var ilGlobalPageTemplate */
    protected ilGlobalPageTemplate $tpl;

    /** @var \Psr\Http\Message\ServerRequestInterface */
    protected $request;

    /** @var \ILIAS\UI\Renderer */
    protected \ILIAS\UI\Renderer $renderer;

    /** @var \ILIAS\UI\Factory */
    protected \ILIAS\UI\Factory $factory;

    /** @var ilNolejPlugin */
    protected $plugin;

    /** @var ilObjNolejGUI */
    protected $obj_gui;

    /** @var ilNolejManagerGUI */
    protected $manager;

    /** @var string */
    protected string $documentId = "";

    /** @var int */
    protected int $status = 0;

    /**
     * @param ilObjNolejGUI $obj_gui
     * @param ilNolejManagerGUI $manager
     */
    public function __construct($manager)
    {
        global $DIC;
        $this->ctrl = $DIC->ctrl();
        $this->db = $DIC->database();
        $this->lng = $DIC->language();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->request = $DIC->http()->request();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();

        $this->obj_gui = $manager->getObjectGUI();
        $this->manager = $manager;
        $this->plugin = ilNolejPlugin::getInstance();

        $this->documentId = $this->manager->documentId;
        $this->status = $this->manager->status;
    }

    /**
     * Handles incoming commmands.
     *
     * @throws ilException if command is not known
     * @return void
     */
    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd(self::CMD_SHOW);

        switch ($cmd) {
            case self::CMD_SHOW:
            case self::CMD_SAVE:
                $this->$cmd();
                break;

            default:
                throw new ilException("Unknown command: '$cmd'");
        }
    }

    /**
     * Show form.
     * @return void
     */
    public function showForm(): void {}

    /**
     * Save form.
     * @return void
     */
    public function saveForm(): void {}

    /**
     * Init form.
     * @return ilPropertyFormGUI
     */
    protected function form(): ilPropertyFormGUI
    {
        return new ilPropertyFormGUI();
    }

    /**
     * Render an information box.
     */
    protected function infoBox(string $message): string
    {
        return $this->renderer->render(
            $this->factory->messageBox()->info($message)
        );
    }
}
