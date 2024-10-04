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
 * Summary Form GUI class.
 *
 * @ilCtrl_isCalledBy ilNolejSummaryFormGUI: ilObjPluginDispatchGUI, ilObjNolejGUI, ilNolejActivityManagementGUI
 */
class ilNolejSummaryFormGUI extends ilNolejFormGUI
{
    /**
     * Show summary form.
     * @return void
     */
    public function showForm(): void
    {
        $status = $this->status;

        if ($status < ilNolejActivityManagementGUI::STATUS_ANALISYS) {
            $this->tpl->setContent($this->infoBox($this->plugin->txt("err_transcription_not_ready")));
            return;
        }

        if ($status < ilNolejActivityManagementGUI::STATUS_REVISION) {
            $this->tpl->setContent($this->infoBox($this->plugin->txt("err_analysis_not_ready")));
            return;
        }

        $form = $this->form();
        $this->tpl->setContent($form->getHTML());

        $this->tpl->setRightContent(
            $this->renderer->render($this->manager->getWorkflow()->withActive(4))
        );
    }

    /**
     * Save summary form.
     * @return void
     */
    public function saveForm(): void
    {
        $status = $this->status;

        if ($status < ilNolejActivityManagementGUI::STATUS_REVISION) {
            $this->tpl->setContent($this->infoBox($this->plugin->txt("err_analysis_not_ready")));
            return;
        }

        $form = $this->form(true);
        if (!$form->checkInput()) {
            // Input not ok.
            $this->tpl->setContent($form->getHTML());
            return;
        }

        $summary = [
            "summary" => [],
            "abstract" => "",
            "keypoints" => []
        ];

        // Summary.
        $length = $form->getInput("summary_count");
        for ($i = 0; $i < $length; $i++) {
            $title = $form->getInput("summary_{$i}_title");
            $txt = $form->getInput("summary_{$i}_text");
            if (!empty($title) && !empty($txt)) {
                $summary["summary"][] = [
                    "title" => $title,
                    "text" => $txt
                ];
            }
        }

        // Abstract.
        $summary["abstract"] = $length > 1
            ? $form->getInput("abstract")
            : "";

        // Keypoints.
        $length = $form->getInput("keypoints_count");
        for ($i = 0; $i < $length; $i++) {
            $txt = $form->getInput(sprintf("keypoints_%d", $i));
            if (!empty($txt)) {
                $summary["keypoints"][] = $txt;
            }
        }

        $success = $this->manager->writeDocumentFile("summary.json", json_encode($summary));
        if (!$success) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_summary_save"));
            $this->showForm();
            return;
        }

        $success = $this->manager->putNolejContent("summary", "summary.json");
        if (!$success) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_summary_put"));
        } else {
            $this->tpl->setOnScreenMessage("success", $this->plugin->txt("summary_saved"));
        }
        $this->showForm();
    }

    /**
     * Init summary form.
     * @param bool $usePost Set value from POST, otherwise load summary file
     * @return ilPropertyFormGUI
     */
    protected function form($usePost = false): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();

        $this->manager->getNolejContent("summary", "summary.json");
        $json = $this->manager->readDocumentFile("summary.json");
        if (!$json) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_summary_file"));
            return $form;
        }

        $summary = json_decode($json);

        // Summary.
        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->plugin->txt("review_summary"));
        $form->addItem($section);
        $length = count($summary->summary);
        $length_input = new ilHiddenInputGUI("summary_count");
        $length_input->setValue($length);
        $form->addItem($length_input);
        for ($i = 0; $i < $length; $i++) {
            $title = new ilTextInputGUI(
                $this->plugin->txt("prop_title"),
                sprintf("summary_%d_title", $i)
            );
            $form->addItem($title);

            $txt = new ilTextAreaInputGUI(
                $this->plugin->txt("prop_freetext"),
                sprintf("summary_%d_text", $i)
            );
            $txt->usePurifier(false);
            $txt->setRows(6);
            $form->addItem($txt);

            if ($usePost) {
                $txt->setValueByArray($this->request->getParsedBody());
                $title->setValueByArray($this->request->getParsedBody());
            } else {
                $txt->setValue($summary->summary[$i]->text);
                $title->setValue($summary->summary[$i]->title);
            }
        }

        // Abstract.
        if (count($summary->summary) > 1) {
            $section = new ilFormSectionHeaderGUI();
            $section->setTitle($this->plugin->txt("summary_abstract"));
            $form->addItem($section);
            $txt = new ilTextAreaInputGUI("", "abstract");
            $txt->usePurifier(false);
            if ($usePost) {
                $txt->setValueByArray($this->request->getParsedBody());
            } else {
                $txt->setValue($summary->abstract);
            }
            $txt->setRows(10);
            $form->addItem($txt);
        }

        // Keypoints.
        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->plugin->txt("summary_keypoints"));
        $form->addItem($section);
        $length = count($summary->keypoints);
        $length_input = new ilHiddenInputGUI("keypoints_count");
        $length_input->setValue($length);
        $form->addItem($length_input);
        for ($i = 0; $i < $length; $i++) {
            $txt = new ilTextAreaInputGUI(
                "",
                sprintf("keypoints_%d", $i)
            );
            $txt->usePurifier(false);
            if ($usePost) {
                $txt->setValueByArray($this->request->getParsedBody());
            } else {
                $txt->setValue($summary->keypoints[$i]);
            }
            $txt->setRows(2);
            $form->addItem($txt);
        }

        $form->addCommandButton(self::CMD_SAVE, $this->lng->txt("save"));
        $form->setFormAction($this->ctrl->getFormAction($this));

        return $form;
    }
}
