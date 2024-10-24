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
 * Handles the presentation in container items (categories, courses, ...)
 * together with the corresponding ...Access class.
 *
 * PLEASE do not create instances of larger classes here. Use the
 * ...Access class to get DB data and keep it small.
 */
class ilObjNolejListGUI extends ilObjectPluginListGUI
{
    /**
     * Init type.
     */
    public function initType(): void
    {
        $this->setType(ilNolejPlugin::PLUGIN_ID);
    }

    /**
     * Get name of gui class handling the commands
     * @return string
     */
    public function getGuiClass(): string
    {
        return "ilObjNolejGUI";
    }

    /**
     * Get commands
     * @return array
     */
    public function initCommands(): array
    {
        $this->commands_enabled = true;
        $this->copy_enabled = false;
        $this->description_enabled = true;
        $this->notice_properties_enabled = true;
        $this->properties_enabled = true;
        $this->comments_enabled = false;
        $this->comments_settings_enabled = false;
        $this->expand_enabled = false;
        $this->info_screen_enabled = false;
        $this->notes_enabled = false;
        $this->preconditions_enabled = false;
        $this->rating_enabled = false;
        $this->rating_categories_enabled = false;
        $this->repository_transfer_enabled = false;
        $this->search_fragment_enabled = false;
        $this->static_link_enabled = false;
        $this->tags_enabled = false;
        $this->timings_enabled = false;

        return [
            [
                "permission" => "read",
                "cmd" => ilObjNolejGUI::CMD_CONTENT_SHOW,
                "default" => true
            ]
        ];
    }

    /**
     * Get item properties
     *
     * @return array of array properties:
     * - "alert" (boolean) => display as an alert property (usually in red)
     * - "property" (string) => property name
     * - "value" (string) => property value
     */
    public function getProperties(): array
    {
        $props = [];

        if (ilObjNolejAccess::_isOffline($this->obj_id)) {
            $props[] = [
                "alert" => true,
                "property" => $this->txt("prop_status"),
                "value" => $this->txt("prop_offline"),
            ];
        }

        if ($this->checkCommandAccess("write", "", $this->ref_id, $this->type)) {
            $object = new ilObjNolej($this->ref_id);
            $props[] = [
                "alert" => false,
                "property" => $this->plugin->txt("plugin_title"),
                "value" => $object->getDocumentStatusInfo(),
            ];
        }

        return $props;
    }
}
