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
 * H5P integration class.
 */
class ilNolejH5PIntegrationGUI
{
    /** @var string */
    protected const H5P_PLUGIN_DIR = "./Customizing/global/plugins/Services/Repository/RepositoryObject/H5P";

    /** @var string */
    protected const H5P_AUTOLOADER = self::H5P_PLUGIN_DIR . "/vendor/autoload.php";

    /** @var ilCtrl */
    protected ilCtrl $ctrl;

    /** @var ilGlobalPageTemplate */
    protected ilGlobalPageTemplate $tpl;

    /** @var \ILIAS\UI\Renderer */
    protected \ILIAS\UI\Renderer $renderer;

    /** @var \ILIAS\UI\Factory */
    protected \ILIAS\UI\Factory $factory;

    /** @var \srag\Plugins\H5P\IContainer */
    protected $h5p_container;

    /** @var \srag\Plugins\H5P\IRepositoryFactory */
    protected $repositories;

    /** @var \Psr\Http\Message\ServerRequestInterface */
    protected $request;

    /**
     * Constructor.
     * @throws LogicException if H5P is not installed.
     */
    public function __construct()
    {
        global $DIC;

        if (!self::isH5PInstalled()) {
            throw new LogicException("You cannot use this plugin without installing H5P plugin first.");
        }

        $h5p_plugin = self::getH5PPlugin();
        $this->h5p_container = $h5p_plugin->getContainer();
        $this->repositories = $this->h5p_container->getRepositoryFactory();

        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->renderer = $DIC->ui()->renderer();
        $this->factory = $DIC->ui()->factory();
        $this->request = $DIC->http()->request();

        // $this->post_request = new \srag\Plugins\H5P\ArrayBasedRequestWrapper(
        //     $DIC->http()->request()->getParsedBody()
        // );

        // $this->get_request = new \srag\Plugins\H5P\ArrayBasedRequestWrapper(
        //     $DIC->http()->request()->getQueryParams()
        // );
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
     * Get the HTML of an H5P activity.
     * @param int $contentId
     * @return string html
     */
    public function render(int $contentId): string
    {
        $this->tpl->addCss(ilNolejPlugin::PLUGIN_DIR . "/css/nolej.css");
        $nolej = ilNolejPlugin::getInstance();

        // Check H5P plugin is active.
        if (!self::isH5PActive()) {
            return $this->renderer->render(
                $this->factory->messageBox()->failure($nolej->txt("err_h5p_not_active"))
            );
        }

        // Check H5P content ID.
        if ($contentId == -1) {
            return $this->renderer->render(
                $this->factory->messageBox()->failure($nolej->txt("err_h5p_content"))
            );
        }

        $content = $this->repositories->content()->getContent($contentId);

        $component = (null === $content)
            ? $this->factory->messageBox()->failure($nolej->txt("err_h5p_content"))
            : $this->h5p_container->getComponentFactory()
            ->content($content)
            ->withLoadingMessage(
                ilNolejManagerGUI::glyphicon("refresh gly-spin") . $nolej->txt("content_loading")
            );

        return "<div style=\"margin-top: 25px;\">" . $this->renderer->render($component) . "</div>";
    }
}
