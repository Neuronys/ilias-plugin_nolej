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
 * Concepts Form GUI class.
 *
 * @ilCtrl_isCalledBy ilNolejConceptsFormGUI: ilObjPluginDispatchGUI, ilObjNolejGUI, ilNolejActivityManagementGUI
 */
class ilNolejConceptsFormGUI extends ilNolejFormGUI
{
    /**
     * Show concepts form.
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

        // if ($this->status == ilNolejActivityManagementGUI::STATUS_REVISION) {
        //     $this->tpl->setRightContent($this->getReviewBox());
        // }
    }

    /**
     * Save concepts form.
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

        $concepts = [];

        $length = $form->getInput("concepts_count");
        for ($i = 0; $i < $length; $i++) {
            $id = $form->getInput(sprintf("concept_%d_id", $i));
            $enable = (bool) $form->getInput(sprintf("concept_%d_enable", $i));
            $useForCW = (bool) $form->getInput(sprintf("concept_%d_cw", $i)) ?? false;
            $useForDTW = (bool) $form->getInput(sprintf("concept_%d_dtw", $i)) ?? false;
            $useForFTW = (bool) $form->getInput(sprintf("concept_%d_ftw", $i)) ?? false;
            $useForGaming = (bool) $form->getInput(sprintf("concept_%d_gaming", $i)) ?? false;
            $useForPractice = (bool) $form->getInput(sprintf("concept_%d_practice", $i)) ?? false;
            $useForAssessment = false; // (bool) $form->getInput(sprintf("concept_%d_assessment", $i)) ?? false;
            $label = $form->getInput(sprintf("concept_%d_label", $i));
            $language = $form->getInput(sprintf("concept_%d_language", $i));
            $definition = $form->getInput(sprintf("concept_%d_definition", $i));
            $games = json_decode($form->getInput(sprintf("concept_%d_games", $i)));

            if (!empty($id)) {
                $concepts[] = [
                    "id" => $id,
                    "enable" => $enable,
                    "use_for_cw" => $useForCW,
                    "use_for_dtw" => $useForDTW,
                    "use_for_ftw" => $useForFTW,
                    "use_for_gaming" => $useForGaming,
                    "use_for_practice" => $useForPractice,
                    "use_for_assessment" => $useForAssessment,
                    "concept" => [
                        "label" => $label,
                        "language" => $language,
                        "definition" => $definition,
                        "available_games" => $games
                    ]
                ];
            }
        }

        $success = $this->manager->writeDocumentFile(
            "concepts.json",
            json_encode(["concepts" => $concepts])
        );
        if (!$success) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_concepts_save"));
            $this->showForm();
            return;
        }

        $success = $this->manager->putNolejContent("concepts", "concepts.json");
        if (!$success) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_concepts_put"));
        } else {
            $this->tpl->setOnScreenMessage("success", $this->plugin->txt("concepts_saved"));
        }
        $this->showForm();
    }

    /**
     * Init concepts form.
     * @param bool $usePost Set value from POST, if false load concepts file
     * @return ilPropertyFormGUI
     */
    protected function form($usePost = false): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->plugin->txt("review_concepts"));

        $this->manager->getNolejContent("concepts", "concepts.json");
        $json = $this->manager->readDocumentFile("concepts.json");
        if (!$json) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_concepts_file"));
            return $form;
        }

        $concepts = json_decode($json);
        $concepts = $concepts->concepts;

        $length = count($concepts);
        $length_input = new ilHiddenInputGUI("concepts_count");
        $length_input->setValue($length);
        $form->addItem($length_input);
        for ($i = 0; $i < $length; $i++) {
            $section = new ilFormSectionHeaderGUI();
            $section->setTitle(sprintf($this->plugin->txt("concepts_n"), $i + 1));
            $form->addItem($section);

            $id = new ilHiddenInputGUI(sprintf("concept_%d_id", $i));
            $id->setValue($concepts[$i]->id);
            $form->addItem($id);

            $label = new ilNonEditableValueGUI(
                $this->plugin->txt("concepts_label"),
                sprintf("concept_%d_label", $i)
            );
            $label->setValue($concepts[$i]->concept->label);
            $form->addItem($label);

            $enable = new ilCheckBoxInputGUI(
                $this->plugin->txt("concepts_enable"),
                sprintf("concept_%d_enable", $i)
            );
            $form->addItem($enable);

            $definition = new ilTextAreaInputGUI(
                $this->plugin->txt("concepts_definition"),
                sprintf("concept_%d_definition", $i)
            );
            $definition->usePurifier(false);
            $definition->setRows(4);
            $enable->addSubItem($definition);

            $availableGames = $concepts[$i]->concept->available_games;
            $useForGaming = new ilCheckBoxInputGUI(
                $this->plugin->txt("concepts_use_for_gaming"),
                sprintf("concept_%d_gaming", $i)
            );

            $useForCW = new ilCheckBoxInputGUI(
                $this->plugin->txt("concepts_use_for_cw"),
                sprintf("concept_%d_cw", $i)
            );

            $useForDTW = new ilCheckBoxInputGUI(
                $this->plugin->txt("concepts_use_for_dtw"),
                sprintf("concept_%d_dtw", $i)
            );

            $useForFTW = new ilCheckBoxInputGUI(
                $this->plugin->txt("concepts_use_for_ftw"),
                sprintf("concept_%d_ftw", $i)
            );

            if ($availableGames != null && is_array($availableGames) && count($availableGames) > 0) {
                $enable->addSubItem($useForGaming);

                if (in_array("cw", $concepts[$i]->concept->available_games)) {
                    $useForGaming->addSubItem($useForCW);
                }

                if (in_array("dtw", $concepts[$i]->concept->available_games)) {
                    $useForGaming->addSubItem($useForDTW);
                }

                if (in_array("ftw", $concepts[$i]->concept->available_games)) {
                    $useForGaming->addSubItem($useForFTW);
                }
            }

            $useForPractice = new ilCheckBoxInputGUI(
                $this->plugin->txt("concepts_use_for_practice"),
                sprintf("concept_%d_practice", $i)
            );
            $enable->addSubItem($useForPractice);

            $language = new ilHiddenInputGUI(sprintf("concept_%d_language", $i));
            $language->setValue($concepts[$i]->concept->language);
            $enable->addSubItem($language);

            $games = new ilHiddenInputGUI(
                sprintf("concept_%d_games", $i)
            );
            $games->setValue(json_encode($concepts[$i]->concept->available_games));
            $enable->addSubItem($games);

            if ($usePost) {
                $enable->setValueByArray($this->request->getParsedBody());
                $useForCW->setValueByArray($this->request->getParsedBody());
                $useForDTW->setValueByArray($this->request->getParsedBody());
                $useForFTW->setValueByArray($this->request->getParsedBody());
                $useForGaming->setValueByArray($this->request->getParsedBody());
                $useForPractice->setValueByArray($this->request->getParsedBody());
                // $useForAssessment->setValueByArray($this->request->getParsedBody());
                $definition->setValueByArray($this->request->getParsedBody());
            } else {
                $enable->setChecked($concepts[$i]->enable);
                $useForCW->setChecked($concepts[$i]->use_for_cw);
                $useForDTW->setChecked($concepts[$i]->use_for_dtw);
                $useForFTW->setChecked($concepts[$i]->use_for_ftw);
                $useForGaming->setChecked($concepts[$i]->use_for_gaming);
                $useForPractice->setChecked($concepts[$i]->use_for_practice);
                // $useForAssessment->setChecked($concepts[$i]->use_for_assessment);
                $definition->setValue($concepts[$i]->concept->definition);
            }
        }

        $form->addCommandButton(self::CMD_SAVE, $this->lng->txt("save"));
        $form->setFormAction($this->ctrl->getFormAction($this));

        return $form;
    }
}
