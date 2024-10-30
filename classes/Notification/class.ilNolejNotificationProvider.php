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

declare(strict_types=1);

use ILIAS\GlobalScreen\Identification\IdentificationInterface;
use ILIAS\GlobalScreen\Scope\Notification\Provider\AbstractNotificationPluginProvider;

/**
 * This class provides the notifications in ILIAS
 */
class ilNolejNotificationProvider extends AbstractNotificationPluginProvider
{
    public const NOTIFICATION_TYPE = "nolej_activity";

    /**
     * @inheritDoc
     */
    public function getNotifications(): array
    {
        $lng = $this->dic->language();
        $ui = $this->dic->ui();
        $user = $this->dic->user();
        $plugin = ilNolejPlugin::getInstance();

        require_once ilNolejPlugin::PLUGIN_DIR . "/classes/Notification/class.ilNolejActivity.php";
        require_once ilNolejPlugin::PLUGIN_DIR . "/classes/Notification/class.ilNolejNotificationPrefRepository.php";

        $notificationRepository = new ilNolejNotificationPrefRepository($user);

        $factory = $this->notification_factory;
        $id = function (string $id): IdentificationInterface {
            return $this->if->identifier($id);
        };

        $new_activities = ilNolejActivity::getActivitiesForUser(
            $user->getId(),
            $notificationRepository->getLastCheckedTimestamp()
        );

        if (count($new_activities) == 0) {
            return [];
        }

        // Creating a Nolej Notification Item
        $nolej_icon = $ui->factory()
            ->symbol()
            ->icon()
            ->custom(
                ilRepositoryObjectPlugin::_getImagePath("Services", "Repository", "robj", "Nolej", "outlined/icon_xnlj.svg"),
                $plugin->txt("plugin_title")
            );

        $group = $factory->standardGroup($id("nolej_bucket_group"))
            ->withTitle($plugin->txt("plugin_title"));

        for ($i = 0, $len = count($new_activities); $i < $len; $i++) {
            $activity = $new_activities[$i];

            $action = $activity->getAction() ?? "";
            $documentTitle = $activity->lookupDocumentTitle() ?? "nf-{$action}";
            $link = ILIAS_HTTP_PATH . "/goto.php?target=xnlj_" . $activity->lookupRefId();
            $title = $ui->factory()->link()->standard($documentTitle, $link);
            $description = $plugin->txt("action_{$action}");

            // $title = $ui->renderer()->render($titleObj);
            $ts = new ilDateTime($activity->getTimestamp(), IL_CAL_UNIX);

            $nolej_notification_item = $ui->factory()
                ->item()
                ->notification($title, $nolej_icon)
                ->withDescription($description)
                ->withProperties([$lng->txt("time") => ilDatePresentation::formatDate($ts)]);

            $group->addNotification(
                $factory->standard($id("nolej_bucket_{$i}"))
                    ->withNotificationItem($nolej_notification_item)
                    ->withClosedCallable(
                        function () use ($activity) {
                            // When the notification is closed.
                            $activity->delete();
                        }
                    )
                    ->withNewAmount(1)
            );
        }

        return [$group];
    }
}
