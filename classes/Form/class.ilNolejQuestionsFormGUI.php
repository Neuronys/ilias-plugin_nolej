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

use ILIAS\UI\Component\Listing\Workflow\Step;

/**
 * Questions Form GUI class.
 *
 * @ilCtrl_isCalledBy ilNolejQuestionsFormGUI: ilObjPluginDispatchGUI, ilObjNolejGUI, ilNolejActivityManagementGUI
 */
class ilNolejQuestionsFormGUI extends ilNolejFormGUI
{
    /**
     * Show questions form.
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
    }

    /**
     * Save questions form.
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

        $this->manager->getNolejContent("questions", "questions.json");
        $json = $this->manager->readDocumentFile("questions.json");
        if (!$json) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_questions_file"));
            $this->tpl->setContent($form->getHTML());
            return;
        }

        $questions = json_decode($json);
        $questions = $questions->questions;

        $length = $form->getInput("questions_count");
        for ($i = 0; $i < $length; $i++) {
            $id = $form->getInput(sprintf("question_%d_id", $i));
            if (empty($id)) {
                continue;
            }

            $questionType = $form->getInput(sprintf("question_%d_type", $i));

            if ($questionType == "open") {
                $enable = (bool) $form->getInput(sprintf("question_%d_enable", $i));
                $useForGrading = false;
            } else {
                $useForGrading = (bool) $form->getInput(sprintf("question_%d_enable", $i));
                $enable = false;
            }

            $answer = $questionType == "hoq"
                ? ""
                : $form->getInput(sprintf("question_%d_answer", $i));

            $question = $questionType == "tf"
                ? ""
                : $form->getInput(sprintf("question_%d_question", $i));

            $distractorsLength = $form->getInput(sprintf("question_%d_distractors", $i));
            $distractors = [];
            for ($j = 0; $j < $distractorsLength; $j++) {
                $distractor = $form->getInput(sprintf("question_%d_distractor_%d", $i, $j));
                if (!empty($distractor)) {
                    $distractors[] = $distractor;
                }
            }
            $selectedDistractor = "";
            $questions[$i] = [
                "id" => $id,
                "explanation" => "false",
                "enable" => $enable,
                "answer" => $answer,
                "use_for_grading" => $useForGrading,
                "question" => $question,
                "question_type" => $questionType,
                "distractors" => $distractors,
                "selected_distractor" => $selectedDistractor
            ];
        }

        $success = $this->manager->writeDocumentFile(
            "questions.json",
            json_encode(["questions" => $questions])
        );
        if (!$success) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_questions_save"));
            $this->showForm();
            return;
        }

        $success = $this->manager->putNolejContent("questions", "questions.json");
        if (!$success) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_questions_put"));
        } else {
            $this->tpl->setOnScreenMessage("success", $this->plugin->txt("questions_saved"));
        }
        $this->showForm();
    }

    /**
     * Init questions form.
     * @param bool $usePost Set value from POST, if false load questions file
     * @return ilPropertyFormGUI
     */
    protected function form($usePost = false): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->plugin->txt("review_questions"));

        $this->manager->getNolejContent("questions", "questions.json");
        $json = $this->manager->readDocumentFile("questions.json");
        if (!$json) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_questions_file"));
            return $form;
        }

        $questionTypeFilter = $_GET["question_type"] ?? "";
        $questionTypes = [];

        $questions = json_decode($json);
        $questions = $questions->questions;

        $length = count($questions);
        $length_input = new ilHiddenInputGUI("questions_count");
        $length_input->setValue($length);
        $form->addItem($length_input);
        for ($i = 0; $i < $length; $i++) {

            // Counter.
            if (!isset($questionTypes[$questions[$i]->question_type])) {
                $questionTypes[$questions[$i]->question_type] = 0;
            }
            $questionTypes[$questions[$i]->question_type] += 1;

            // Filter.
            if (!empty($questionTypeFilter) && $questionTypeFilter != $questions[$i]->question_type) {
                continue;
            }

            $section = new ilFormSectionHeaderGUI();
            $section->setTitle(sprintf($this->plugin->txt("questions_n"), $i + 1));
            $form->addItem($section);

            $id = new ilHiddenInputGUI(sprintf("question_%d_id", $i));
            $id->setValue($questions[$i]->id);
            $form->addItem($id);

            if ($questions[$i]->question_type != "tf") {
                $question = new ilTextAreaInputGUI(
                    $this->plugin->txt("questions_question"),
                    sprintf("question_%d_question", $i)
                );
                $question->usePurifier(false);
                $question->setRows(3);
                $form->addItem($question);
            } else {
                $question = null;
            }

            $questionType = new ilHiddenInputGUI(sprintf("question_%d_type", $i));
            $questionType->setValue($questions[$i]->question_type);
            $form->addItem($questionType);

            $questionTypeLabel = new ilNonEditableValueGUI(
                $this->plugin->txt("questions_question_type"),
                sprintf("question_%d_type_label", $i)
            );
            $questionTypeLabel->setValue(
                $this->plugin->txt("questions_type_" . $questions[$i]->question_type)
            );
            $form->addItem($questionTypeLabel);

            $enable = new ilCheckboxInputGUI(
                $this->plugin->txt(
                    $questions[$i]->question_type == "open"
                        ? "questions_enable"
                        : "questions_use_for_grading"
                ),
                sprintf("question_%d_enable", $i)
            );
            $form->addItem($enable);

            if ($questions[$i]->question_type != "hoq") {
                $answer = new ilTextAreaInputGUI(
                    $this->plugin->txt("questions_answer"),
                    sprintf("question_%d_answer", $i)
                );
                $answer->usePurifier(false);
                $answer->setRows(3);
                if ($questions[$i]->question_type == "tf") {
                    $form->addItem($answer);
                } else {
                    $enable->addSubItem($answer);
                }
            } else {
                $answer = null;
            }

            $distractorsLength = count($questions[$i]->distractors);
            $distractors = new ilHiddenInputGUI(sprintf("question_%d_distractors", $i));
            $distractors->setValue($distractorsLength);
            $enable->addSubItem($distractors);
            for ($j = 0; $j < $distractorsLength; $j++) {
                $distractor = new ilTextAreaInputGUI(
                    $j == 0 ? $this->plugin->txt("questions_distractors") : "",
                    sprintf("question_%d_distractor_%d", $i, $j)
                );
                $distractor->usePurifier(false);
                $enable->addSubItem($distractor);
                if ($usePost) {
                    $distractor->setValueByArray($this->request->getParsedBody());
                } else {
                    $distractor->setValue($questions[$i]->distractors[$j]);
                }
            }

            if ($usePost) {
                $enable->setValueByArray($this->request->getParsedBody());
                if (isset($answer)) {
                    $answer->setValueByArray($this->request->getParsedBody());
                }
                if (isset($question)) {
                    $question->setValueByArray($this->request->getParsedBody());
                }
            } else {
                $enable->setChecked(
                    $questions[$i]->question_type == "open"
                        ? $questions[$i]->enable
                        : $questions[$i]->use_for_grading
                );
                if (isset($answer)) {
                    $answer->setValue($questions[$i]->answer);
                }
                if (isset($question)) {
                    $question->setValue($questions[$i]->question);
                }
            }
        }

        $steps = [
            $this->factory->listing()->workflow()->step(
                sprintf(
                    "%s (%d)",
                    $this->plugin->txt("questions_type_all"),
                    $length
                ),
                "",
                $this->ctrl->getLinkTarget($this, self::CMD_SHOW)
            )
                ->withAvailability(Step::AVAILABLE)
                ->withStatus(Step::IN_PROGRESS)
        ];

        $selectedIndex = 0;
        $i = 1;
        foreach ($questionTypes as $type => $count) {
            $steps[] = $this->factory->listing()->workflow()->step(
                sprintf(
                    "%s (%d)",
                    $this->plugin->txt("questions_type_" . $type),
                    $count
                ),
                "",
                $this->ctrl->getLinkTarget($this, self::CMD_SHOW)
                    . "&question_type=" . $type
            )
                ->withAvailability(Step::AVAILABLE)
                ->withStatus(Step::IN_PROGRESS);
            if ($type == $questionTypeFilter) {
                $selectedIndex = $i;
            }
            $i++;
        }
        $wf = $this->factory->listing()->workflow()->linear($this->plugin->txt("questions_question_type"), $steps);

        // TODO: Use toolbar selector.
        $this->tpl->setLeftContent(
            $this->renderer->render($wf->withActive($selectedIndex))
            // . ($this->status == ilNolejActivityManagementGUI::STATUS_REVISION ? $this->getReviewBox() : "")
        );

        $form->addCommandButton(self::CMD_SAVE, $this->lng->txt("save"));
        $form->setFormAction($this->ctrl->getFormAction($this));

        return $form;
    }
}
