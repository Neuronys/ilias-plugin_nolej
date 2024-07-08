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

require_once (ilNolejPlugin::PLUGIN_DIR . "/classes/Notification/NolejActivity.php");
require_once (ilNolejPlugin::PLUGIN_DIR . "/classes/Notification/NolejNotificationPrefRepository.php");
require_once (ilNolejPlugin::PLUGIN_DIR . "/classes/class.ilNolejConfig.php");

use ILIAS\GlobalScreen\Identification\IdentificationInterface;
use ILIAS\GlobalScreen\Scope\Notification\Provider\AbstractNotificationPluginProvider;

// use ILIAS\Nolej\Notification\NolejNotificationPrefRepository;
// use ILIAS\GlobalScreen\Scope\Notification\Provider\AbstractNotificationProvider;
// use ILIAS\GlobalScreen\Scope\Notification\Provider\NotificationProvider;

/**
 * This class provides the notifications in ILIAS
 */
class NolejNotificationProvider extends AbstractNotificationPluginProvider
{

    /**
     * @inheritDoc
     */
    public function getNotifications(): array
    {
        // // global $DIC;
        $lng = $this->dic->language();
        $ui = $this->dic->ui();
        $user = $this->dic->user();
        $ctrl = $this->dic->ctrl();
        $config = new ilNolejConfig();

        $noti_repo = new NolejNotificationPrefRepository($user);

        // $lng->loadLanguageModule("badge");

        $factory = $this->notification_factory;
        $id = function (string $id): IdentificationInterface {
            return $this->if->identifier($id);
        };

        $new_activities = NolejActivity::getActivitiesForUser(
            $user->getId(),
            $noti_repo->getLastCheckedTimestamp()
        );

        if (count($new_activities) == 0) {
            return [];
        }

        // Creating a Nolej Notification Item
        $nolej_icon = $ui
            ->factory()
            ->symbol()
            ->icon()
            ->custom(
                ilRepositoryObjectPlugin::_getImagePath("Services", "robj", "Nolej", "outlined/icon_xnlj.svg"),
                $config->txt("plugin_title")
            );

        $group = $factory
            ->standardGroup($id('nolej_bucket_group'))
            ->withTitle($config->txt("plugin_title"))
            ->withOpenedCallable(function () {
                // Stuff we do, when the notification is opened
            });

        for ($i = 0, $len = count($new_activities); $i < $len; $i++) {
            $activity = $new_activities[$i];

            switch ($activity->getAction()) {
                case "tac":
                case "tic":
                    $title = $config->txt("action_" . ($activity->getAction() ?? ""));
                    $description = "";
                    break;

                default:
                    $documentTitle = $activity->lookupDocumentTitle()
                        ?? "nf-" . $activity->getAction();
                    $link = ILIAS_HTTP_PATH . "/goto.php?target=xnlj_" . $activity->lookupRefId();
                    $title = $ui->factory()->link()->standard(
                        $documentTitle,
                        $link
                    );
                    $description = $config->txt("action_" . ($activity->getAction() ?? ""));
            }

            // $title = $ui->renderer()->render($titleObj);
            $ts = new ilDateTime($activity->getTimestamp(), IL_CAL_UNIX);

            $nolej_notification_item = $ui
                ->factory()
                ->item()
                ->notification($title, $nolej_icon)
                ->withDescription($description)
                ->withProperties([$lng->txt("time") => ilDatePresentation::formatDate($ts)]);

            $group->addNotification(
                $factory
                    ->standard($id('nolej_bucket_' . $i))
                    ->withNotificationItem($nolej_notification_item)
                    ->withClosedCallable(
                        function () use ($activity) {
                            // Stuff we do, when the notification is closed
                            $activity->delete();
                        }
                    )
                    ->withNewAmount(1)
            );
        }

        return [$group];
    }
}
