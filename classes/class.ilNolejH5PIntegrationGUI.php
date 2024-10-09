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

use ILIAS\UI\Component\Input\Container\Form\Form;
use srag\Plugins\H5P\Content\ContentEditorHelper;
use srag\Plugins\H5P\Content\Form\EditContentFormBuilder;
use srag\Plugins\H5P\Content\Form\EditContentFormProcessor;
use srag\Plugins\H5P\Content\Form\IPostProcessorAware;
use srag\Plugins\H5P\Content\IContent;
use srag\Plugins\H5P\ITranslator;
use srag\Plugins\H5P\TemplateHelper;

/**
 * H5P integration class.
 * @ilCtrl_isCalledBy ilNolejH5PIntegrationGUI: ilObjPluginDispatchGUI, ilObjNolejGUI
 */
class ilNolejH5PIntegrationGUI
{
    use ContentEditorHelper;
    use TemplateHelper;
    use ilH5PRequestObject;

    /** @var string */
    protected const H5P_PLUGIN_DIR = "./Customizing/global/plugins/Services/Repository/RepositoryObject/H5P";

    /** @var string */
    protected const H5P_AUTOLOADER = self::H5P_PLUGIN_DIR . "/vendor/autoload.php";

    /** @var string edit h5p activity */
    public const CMD_EDIT = "edit";

    /** @var string update h5p activity */
    public const CMD_UPDATE = "update";

    /** @var ilCtrl */
    protected $ctrl;

    /** @var ilGlobalPageTemplate */
    protected $tpl;

    /** @var \ILIAS\UI\Factory */
    protected \ILIAS\UI\Factory $factory;

    /** @var \srag\Plugins\H5P\IContainer */
    protected $h5p_container;

    /** @var \srag\Plugins\H5P\IRepositoryFactory */
    protected $repositories;

    /** @var ITranslator */
    protected $translator;

    /** @var \Psr\Http\Message\ServerRequestInterface */
    protected $request;

    /** @var ilToolbarGUI */
    protected $toolbar;

    /** @var \ILIAS\Refinery\Factory */
    protected $refinery;

    /** @var ilLanguage */
    protected ilLanguage $lng;

    /** @var ilObjNolejGUI */
    protected $obj_gui;

    /** @var ilNolejPlugin */
    protected $plugin;

    /**
     * Constructor.
     * @param ilObjNolejGUI $obj_gui
     * @throws LogicException if H5P is not installed.
     */
    public function __construct($obj_gui)
    {
        global $DIC;

        if (!self::isH5PInstalled()) {
            throw new LogicException("You cannot use this plugin without installing H5P plugin first.");
        }

        $h5p_plugin = self::getH5PPlugin();
        $this->h5p_container = $h5p_plugin->getContainer();
        $this->repositories = $this->h5p_container->getRepositoryFactory();
        $this->translator = $this->h5p_container->getTranslator();

        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->template = $DIC->ui()->mainTemplate(); // For TemplateHelper.
        $this->renderer = $DIC->ui()->renderer();
        $this->factory = $DIC->ui()->factory();
        $this->request = $DIC->http()->request();
        $this->refinery = $DIC->refinery();
        $this->toolbar = $DIC->toolbar();
        $this->lng = $DIC->language();

        $this->obj_gui = $obj_gui;
        $this->plugin = ilNolejPlugin::getInstance();
    }

    /**
     * Handles all commmands, $cmd = functionName()
     * @throws ilException if command is not known
     * @return void
     */
    public function executeCommand(): void
    {
        // Check H5P plugin is active.
        if (!self::isH5PActive()) {
            $this->tpl->setContent(
                $this->renderer->render(
                    $this->factory->messageBox()->failure($this->plugin->txt("err_h5p_not_active"))
                )
            );
            return;
        }

        $cmd = $this->ctrl->getCmd();
        switch ($cmd) {
            case self::CMD_EDIT:
            case self::CMD_UPDATE:
                $content = $this->getContentByRequest();
                if (null === $content) {
                    $this->tpl->setContent(
                        $this->renderer->render(
                            $this->factory->messageBox()->failure($this->plugin->txt("err_h5p_content"))
                        )
                    );
                    return;
                }

                // Needed for ajax loader.
                $this->ctrl->setParameterByClass(ilH5PAjaxEndpointGUI::class, "ref_id", $_GET["ref_id"]);

                $this->$cmd($content);
                break;

            default:
                throw new ilException("Unknown command: '$cmd'");
        }
    }

    /**
     * Set back target.
     * @return void
     */
    protected function setTabs(): void
    {
        global $DIC;
        $DIC->tabs()->clearTargets();

        $this->ctrl->setParameter($this->obj_gui, "type", $_GET["type"]);
        $DIC->tabs()->setBackTarget(
            $this->lng->txt("back"),
            $this->ctrl->getLinkTarget($this->obj_gui, ilObjNolejGUI::CMD_CONTENT_SHOW)
        );
    }

    /**
     * Get the H5P plugin object.
     * @return ilH5PPlugin
     */
    public static function getH5PPlugin()
    {
        global $DIC;
        $component_factory = $DIC["component.factory"];
        return $component_factory->getPlugin(ilH5PPlugin::PLUGIN_ID);
    }

    /**
     * Check if H5P plugin is installed.
     * @return bool
     */
    public static function isH5PInstalled(): bool
    {
        if (!file_exists(self::H5P_AUTOLOADER)) {
            return false;
        }

        require_once self::H5P_AUTOLOADER;

        $h5p_plugin = self::getH5PPlugin();
        $h5p_container = $h5p_plugin->getContainer();
        $repositories = $h5p_container->getRepositoryFactory();

        return $repositories->general()->isMainPluginInstalled();
    }

    /**
     * Check if H5P plugin is active.
     * @return bool
     */
    public static function isH5PActive(): bool
    {
        $h5p_plugin = self::getH5PPlugin();
        return $h5p_plugin->isActive();
    }

    /**
     * Get the h5p content element using ref_id and type.
     * @return ?IContent
     */
    protected function getContentByRequest()
    {
        if (!isset($_GET["ref_id"], $_GET["type"])) {
            return null;
        }

        $this->ctrl->saveParameterByClass(ilObjNolejGUI::class, "ref_id");
        $this->ctrl->saveParameterByClass(ilObjNolejGUI::class, "type");

        $object = new ilObjNolej((int) $_GET["ref_id"]);
        $contentId = $object->getContentIdOfType($_GET["type"]);

        // Check H5P content ID.
        if ($contentId == -1) {
            return null;
        }

        return $this->repositories->content()->getContent($contentId);
    }

    /**
     * Get the HTML of an H5P activity.
     * @param int $contentId
     * @param string $type
     * @param bool $editable
     * @return string html
     */
    public function getHTML(int $contentId, string $type = "", bool $editable = false): string
    {
        $this->tpl->addCss(ilNolejPlugin::PLUGIN_DIR . "/css/nolej.css");

        // Check H5P plugin is active.
        if (!self::isH5PActive()) {
            return $this->renderer->render(
                $this->factory->messageBox()->failure($this->plugin->txt("err_h5p_not_active"))
            );
        }

        // Check H5P content ID.
        if ($contentId == -1) {
            return $this->renderer->render(
                $this->factory->messageBox()->failure($this->plugin->txt("err_h5p_content"))
            );
        }

        $content = $this->repositories->content()->getContent($contentId);

        if (null === $content) {
            return $this->renderer->render(
                $this->factory->messageBox()->failure($this->plugin->txt("err_h5p_content"))
            );
        }

        if ($editable) {
            $this->ctrl->setParameter($this, "type", $type);
            $this->ctrl->setParameter($this, "ref_id", $_GET["ref_id"]);
            $this->toolbar->addComponent(
                $this->factory->button()->standard(
                    $this->lng->txt("edit_content"),
                    $this->ctrl->getLinkTarget($this, self::CMD_EDIT)
                )
            );
        }

        $component = $this->h5p_container->getComponentFactory()
            ->content($content)
            ->withLoadingMessage(
                ilNolejManagerGUI::glyphicon("refresh gly-spin") . $this->plugin->txt("content_loading")
            );

        return $this->renderer->render($component);
    }

    /**
     * Import H5P file.
     * @param string $filepath
     * @param string $type
     * @return int content id
     */
    public function importFromPath($filepath, $type)
    {
        $h5p_kernel = $this->getKernel();

        $file = ilH5PEditorStorage::saveFileTemporarily(ILIAS_ABSOLUTE_PATH . $filepath, true);

        $file_upload_communicator = $this->h5p_container->getFileUploadCommunicator();
        $file_upload_communicator->setUploadPath("{$file->dir}/{$file->fileName}");

        $h5p_storage = $this->h5p_container->getKernelStorage();
        $h5p_validator = $this->h5p_container->getKernelValidator();

        if (!$h5p_validator->isValidPackage()) {
            return -1;
        }

        $metadata = (array) $h5p_kernel->mainJsonData;
        $metadata["title"] = $this->plugin->txt("activities_{$type}");
        $metadata["in_workspace"] = false;
        $metadata["obj_id"] = $this->obj_gui->getObject()->getId();
        $metadata["parent_type"] = ilNolejPlugin::PLUGIN_ID;

        $h5p_storage->savePackage([
            "metadata" => $metadata
        ]);

        ilH5PEditorStorage::removeTemporarilySavedFiles($file_upload_communicator->getUploadPath());

        return $h5p_storage->contentId;
    }

    /**
     * Update the given h5p activity.
     * @param IContent $content
     * @return void
     */
    public function edit($content): void
    {
        $this->setTabs();
        $this->render($this->getEditContentForm(self::CMD_UPDATE, $content));
    }

    /**
     * Update the h5p activity.
     * @param IContent $content
     * @return void
     */
    public function update($content): void
    {
        $this->runFormProcessor(
            $this->getEditContentFormProcessor(
                $this->getEditContentForm(self::CMD_UPDATE, $content)
            ),
            $content
        );
    }

    /**
     * Executes the given form processor and registers an additional post-processor,
     * which calles either $this->createElement() or $this->updateElement() depending
     * on the given content.
     * @param IPostProcessorAware $form_processor
     * @param IContent $content
     * @return void
     */
    protected function runFormProcessor(IPostProcessorAware $form_processor, IContent $content = null): void
    {
        if ($form_processor->processForm()) {
            $this->ctrl->redirectByClass(
                [ilObjPluginDispatchGUI::class, ilObjNolejGUI::class],
                ilObjNolejGUI::CMD_CONTENT_SHOW
            );
        }

        $this->render($form_processor->getProcessedForm());
    }

    /**
     * Return the editor form.
     * @param string $command
     * @param IContent $content
     * @return Form
     */
    protected function getEditContentForm(string $command, IContent $content): Form
    {
        $builder = new EditContentFormBuilder(
            $this->translator,
            $this->factory->input()->container()->form(),
            $this->factory->input()->field(),
            $this->h5p_container->getComponentFactory(),
            $this->refinery,
            $this->getContentEditorData($content->getContentId())
        );

        return $builder->getForm(
            $this->ctrl->getFormAction($this, $command)
        );
    }

    /**
     * Return the h5p editor processor.
     * @param Form $edit_form
     * @return IPostProcessorAware
     */
    protected function getEditContentFormProcessor(Form $edit_form): IPostProcessorAware
    {
        return new EditContentFormProcessor(
            $this->repositories->content(),
            $this->repositories->library(),
            $this->h5p_container->getKernel(),
            $this->h5p_container->getEditor(),
            $this->request,
            $edit_form,
            $_GET["ref_id"],
            ilNolejPlugin::PLUGIN_ID,
            false
        );
    }

    /**
     * Get the h5p kernel.
     * @see ContentEditorHelper::getKernel
     * @return \H5PCore
     */
    protected function getKernel(): \H5PCore
    {
        return $this->h5p_container->getKernel();
    }
}
