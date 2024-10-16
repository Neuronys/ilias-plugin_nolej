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
 * @ilCtrl_isCalledBy ilNolejTranscriptionFormGUI: ilObjPluginDispatchGUI, ilObjNolejGUI, ilNolejManagerGUI
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
        $this->tpl->setRightContent(
            $this->renderer->render($this->manager->getWorkflow()->withActive(1))
        );

        if ($this->status < ilNolejManagerGUI::STATUS_ANALYSIS) {
            $this->tpl->setContent($this->infoBox($this->plugin->txt("err_transcription_not_ready")));
            return;
        }

        $errorMessage = $this->downloadTranscription();
        if (null !== $errorMessage) {
            $this->tpl->setOnScreenMessage("failure", $errorMessage);
            return;
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
            $this->tpl->setRightContent(
                $this->renderer->render($this->manager->getWorkflow()->withActive(1))
            );
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

        // Start analysis.
        $errorMessage = $this->runAnalysis($title, $transcription, false);
        if (!empty($errorMessage)) {
            // An error occurred.
            $this->tpl->setOnScreenMessage("failure", $errorMessage);
            $this->manager->updateDocumentStatus(ilNolejManagerGUI::STATUS_FAILED);

            $ass = new ilNolejActivity($this->documentId, $DIC->user()->getId(), "analysis");
            $ass->withStatus("ko")
                ->withCode(0)
                ->withErrorMessage($errorMessage)
                ->withConsumedCredit(0)
                ->store();

            return;
        }

        $this->tpl->setOnScreenMessage("success", $this->plugin->txt("action_analysis"), true);
        $this->ctrl->redirectByClass(ilNolejConceptsFormGUI::class, ilNolejConceptsFormGUI::CMD_SHOW);
    }

    /**
     * Init transcription form.
     * @return ilPropertyFormGUI
     */
    protected function form(): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();

        // Object title.
        $objTitle = $this->obj_gui->getObject()->getTitle();

        // Module transcription.
        $content = $this->manager->readDocumentFile("transcription.htm");

        if ($this->status != ilNolejManagerGUI::STATUS_ANALYSIS) {
            $form->setTitle($objTitle);
            $form->setDescription($content ? $content : "---");
            return $form;
        }

        $form->setTitle($this->plugin->txt("tab_analysis"));
        $form->setShowTopButtons(false);

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
            $txt->disableButtons(["charmap", "anchor"]);
        }
        $txt->setValue($content);
        $form->addItem($txt);

        $form->addCommandButton(self::CMD_SAVE, $this->lng->txt("save"));
        $form->setFormAction($this->ctrl->getFormAction($this));

        return $form;
    }

    /**
     * Download the transctiption of the analyzed media.
     * @return ?string error message
     */
    public function downloadTranscription()
    {
        if ($this->status < ilNolejManagerGUI::STATUS_ANALYSIS) {
            // Transcription is not ready!
            return $this->plugin->txt("err_transcription_not_ready");
        }

        if ($this->manager->hasDocumentFile("transcription.htm")) {
            // Transcription already downloaded.
            return;
        }

        $api = new ilNolejAPI();
        $result = $api->get("/documents/{$this->documentId}/transcription");

        if (
            !is_object($result) ||
            !property_exists($result, "title") ||
            !is_string($result->title) ||
            !property_exists($result, "result") ||
            !is_string($result->result)
        ) {
            return $this->plugin->txt("err_transcription_get") . print_r($result);
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
            return $this->plugin->txt("err_transcription_download") . print_r($result, true);
        }
    }

    /**
     * Call Nolej API to start the analysis.
     * @param string $title
     * @param ?string $transcription null to start analysis with no transcription changes.
     * @param bool $automaticMode
     * @return string $errorMessage empty on success
     */
    public function runAnalysis($title, $transcription = null, $automaticMode = false): string
    {
        global $DIC;

        $api = new ilNolejAPI();

        // Check transcription.
        if ($transcription == null) {
            // Start analysis with no changes in the transcription.
            $this->plugin->log("Requesting analysis with unmodified transcription for document: {$this->documentId}");
            $result = $api->put(
                "/documents/{$this->documentId}/transcription",
                [],
                true,
                true
            );
        } elseif (empty($transcription) || empty($title)) {
            // Error: transcription and title cannot be empty.
            return $this->plugin->txt("err_transcription_missing");
        } else {
            // Update transcription file.
            $this->manager->writeDocumentFile("transcription.htm", $transcription);

            // Generate transcription url webhook.
            $url = ILIAS_HTTP_PATH . substr(ilWACSignedPath::signFile($this->manager->dataDir . "/transcription.htm"), 1);

            $this->plugin->log("Requesting analysis for document: {$this->documentId}");

            // Call Nolej analysis API.
            $result = $api->put(
                "/documents/{$this->documentId}/transcription",
                [
                    "s3URL" => $url,
                    "automaticMode" => $automaticMode,
                ],
                true,
                true
            );
        }

        if (
            !is_object($result) ||
            !property_exists($result, "result") ||
            !is_string($result->result) ||
            !(
                $result->result == "\"ok\"" ||
                $result->result == "ok"
            )
        ) {
            return "An error occurred: " . print_r($result, true);
        }

        $this->manager->updateDocumentStatus(ilNolejManagerGUI::STATUS_ANALYSIS_PENDING);

        $ass = new ilNolejActivity($this->documentId, $DIC->user()->getId(), "analysis");
        $ass->withStatus("ok")
            ->withCode(0)
            ->withErrorMessage("")
            ->withConsumedCredit(0)
            ->store();

        return "";
    }
}
