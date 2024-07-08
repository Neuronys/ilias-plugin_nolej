<?php
declare(strict_types=1);

/**
 * This file is part of Nolej Repository Object Plugin for ILIAS,
 * developed by OC Open Consulting to integrate ILIAS with Nolej
 * software by Neuronys.
 *
 * @author Vincenzo Padula <vincenzo@oc-group.eu>
 * @copyright 2024 OC Open Consulting SB Srl
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// use ILIAS\GlobalScreen\Scope\MainMenu\Provider\AbstractStaticMainMenuProvider;
use ILIAS\MainMenu\Provider\StandardTopItemsProvider;
use ILIAS\GlobalScreen\Scope\MainMenu\Provider\AbstractStaticPluginMainMenuProvider;
use ILIAS\GlobalScreen\Scope\MainMenu\Provider\StaticMainMenuProvider;


/**
 * This class provides a menu button to access Nolej modules
 * @todo in future releases
 */
class NolejMainBarProvider extends AbstractStaticPluginMainMenuProvider
{

    /**
     * @inheritDoc
     */
    public function getStaticTopItems(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStaticSubItems(): array
    {
        global $DIC;
        $title = "Test Menu"; // $DIC->language()->txt("mm_badges");
        $icon = $DIC->ui()->factory()->symbol()->icon()->standard("bdga", $title)->withIsOutlined(true);

        return [
            $this->mainmenu
                ->link($this->if->identifier('mm_pd_badges'))
                ->withTitle($title)
                ->withAction("ilias.php?baseClass=ilDashboardGUI&cmd=jumpToBadges")
                ->withPosition(40)
                ->withParent(StandardTopItemsProvider::getInstance()->getAchievementsIdentification())
                ->withSymbol($icon)
                ->withNonAvailableReason($DIC->ui()->factory()->legacy("{$DIC->language()->txt('component_not_active')}"))
                ->withAvailableCallable(
                    function () {
                        return true; // (bool) (ilBadgeHandler::getInstance()->isActive());
                    }
                )
        ];
    }
}
