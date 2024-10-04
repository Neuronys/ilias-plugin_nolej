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
 * Transcription Form GUI class.
 *
 * @ilCtrl_Calls ilNolejTranscriptionFormGUI: ilNolejConceptsFormGUI, ilNolejQuestionsFormGUI, ilNolejSummaryFormGUI
 * @ilCtrl_isCalledBy ilNolejTranscriptionFormGUI: ilObjPluginDispatchGUI, ilObjNolejGUI, ilNolejActivityManagementGUI
 */
class ilNolejTranscriptionFormGUI extends ilNolejFormGUI
{
    public const PROP_TITLE = "title";
    public const PROP_TRANSCRIPTION = "transcription";

    /**
     * Show transcription form.
     * @return void
     */
    public function showForm(): void
    {
        $status = $this->status;

        if ($status < ilNolejActivityManagementGUI::STATUS_ANALISYS) {
            $this->tpl->setContent($this->infoBox($this->plugin->txt("err_transcription_not_ready")));
            return;
        }

        if (!file_exists($this->manager->dataDir . "/transcription.htm")) {
            $downloadSuccess = $this->downloadTranscription();
            if (!$downloadSuccess) {
                return;
            }
        }

        $form = $this->form();
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Save transcription form.
     * @return void
     */
    public function saveForm(): void
    {
        global $DIC;

        $form = $this->form();

        if (!$form->checkInput()) {
            // Input not ok.
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
            return;
        }

        // May update title.
        $title = $form->getInput(self::PROP_TITLE);
        $objTitle = $this->obj_gui->getObject()->getTitle();
        if ($title != "" && $title != $objTitle) {
            $this->obj_gui->getObject()->setTitle($title);
            $this->obj_gui->getObject()->update();
        }

        // Update transcription.
        $transcription = $form->getInput(self::PROP_TRANSCRIPTION);
        $this->manager->writeDocumentFile("transcription.htm", $transcription);

        $api = new ilNolejAPI();
        $url = ILIAS_HTTP_PATH . substr(ilWACSignedPath::signFile($this->manager->dataDir . "/transcription.htm"), 1);
        $result = $api->put(
            sprintf("/documents/%s/transcription", $this->documentId),
            [
                "s3URL" => $url,
                "automaticMode" => false,
            ],
            true,
            true
        );

        if (
            !is_object($result) ||
            !property_exists($result, "result") ||
            !is_string($result->result) ||
            !(
                $result->result == "\"ok\"" ||
                $result->result == "ok"
            )
        ) {
            $this->tpl->setOnScreenMessage("failure", "An error occurred: " . print_r($result, true));
            return;
        }

        $this->manager->updateDocumentStatus(ilNolejActivityManagementGUI::STATUS_ANALISYS_PENDING);

        $ass = new NolejActivity($this->documentId, $DIC->user()->getId(), "analysis");
        $ass->withStatus("ok")
            ->withCode(0)
            ->withErrorMessage("")
            ->withConsumedCredit(0)
            ->store();

        $this->tpl->setOnScreenMessage("success", $this->plugin->txt("action_analysis"), true);
        $this->ctrl->redirectByClass(ilNolejConceptsFormGUI::class, ilNolejConceptsFormGUI::CMD_SHOW);
    }

    /**
     * Init transcription form.
     * @return ilPropertyFormGUI
     */
    protected function form(): ilPropertyFormGUI
    {
        $status = $this->status;

        $form = new ilPropertyFormGUI();

        // Object title.
        $objTitle = $this->obj_gui->getObject()->getTitle();

        if ($status != ilNolejActivityManagementGUI::STATUS_ANALISYS) {
            $transcription = new ilFormSectionHeaderGUI();
            $transcription->setTitle($objTitle);
            $transcription->setInfo($this->manager->readDocumentFile("transcription.htm"));
            $form->addItem($transcription);
            return $form;
        }

        $form->setTitle($this->plugin->txt("tab_analysis"));

        // Document title.
        $title = $this->obj_gui->getObject()->getDocumentTitle();
        if ($title != "" && $title != $objTitle) {
            $titleInput = new ilTextInputGUI(
                $this->plugin->txt("prop_" . self::PROP_TITLE),
                self::PROP_TITLE
            );
            $titleInput->setValue($title);
        } else {
            $titleInput = new ilNonEditableValueGUI(
                $this->plugin->txt("prop_" . self::PROP_TITLE),
                self::PROP_TITLE
            );
            $titleInput->setValue($objTitle);
        }
        $form->addItem($titleInput);

        // Transcription.
        $txt = new ilTextAreaInputGUI($this->plugin->txt("prop_transcription"), self::PROP_TRANSCRIPTION);
        $txt->setRequired(true);
        $txt->setRows(50);
        $txt->setMinNumOfChars(500);
        $txt->setMaxNumOfChars(50000);
        $txt->usePurifier(false);
        if (ilObjAdvancedEditing::_getRichTextEditor() === "tinymce") {
            $txt->setUseRte(true);
            $txt->setRteTags(["h1", "h2", "h3", "p", "ul", "ol", "li", "br", "strong", "u", "i"]);
            $txt->setRTERootBlockElement("");
            $txt->disableButtons([
                "charmap",
                "justifyright",
                "justifyleft",
                "justifycenter",
                "justifyfull",
                "alignleft",
                "aligncenter",
                "alignright",
                "alignjustify",
                "anchor",
                "pasteword"
            ]);
        }
        $txt->setValue($this->manager->readDocumentFile("transcription.htm"));
        $form->addItem($txt);

        $form->addCommandButton(self::CMD_SAVE, $this->lng->txt("save"));
        $form->setFormAction($this->ctrl->getFormAction($this));

        return $form;
    }

    /**
     * Download the transctiption of the analyzed media.
     * @return bool success
     */
    protected function downloadTranscription()
    {
        $status = $this->status;

        if ($status < ilNolejActivityManagementGUI::STATUS_ANALISYS) {
            // Transctiption is not ready!
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_transcription_not_ready"));
            return false;
        }

        $api = new ilNolejAPI();
        $result = $api->get(
            sprintf("/documents/%s/transcription", $this->documentId)
        );

        if (
            !is_object($result) ||
            !property_exists($result, "title") ||
            !is_string($result->title) ||
            !property_exists($result, "result") ||
            !is_string($result->result)
        ) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_transcription_get") . sprintf($result));
            return false;
        }

        $title = $result->title;
        $this->db->manipulateF(
            "UPDATE " . ilNolejPlugin::TABLE_DOC . " SET title = %s WHERE document_id = %s;",
            ["text", "text"],
            [$title, $this->documentId]
        );

        $success = $this->manager->writeDocumentFile(
            "transcription.htm",
            file_get_contents($result->result)
        );
        if (!$success) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_transcription_download") . print_r($result, true));
            return false;
        }

        return true;
    }
}
