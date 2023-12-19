<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace qtype_ordering\output;

use renderer_base;

/**
 * Renderable class for the displaying the feedback.
 *
 * @package    qtype_ordering
 * @copyright  2023 Mathew May <mathew.solutions>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback extends renderable_base {

    /**
     * Export the data for the mustache template.
     *
     * @param renderer_base $output renderer to be used to render the feedback elements.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $PAGE;

        $data = [];
        $question = $this->qa->get_question();
        $hint = null;
        $isshownumpartscorrect = true;
        $qtyperenderer = $PAGE->get_renderer('qtype_ordering');

        if ($this->options->feedback) {
            // Literal render out but we trust the teacher.
            $data['feedback'] = $qtyperenderer->specific_feedback($this->qa);

            if ($this->options->numpartscorrect) {
                $numpartscorrect = new num_parts_correct($this->qa);
                $data['numpartscorrect'] = $numpartscorrect->export_for_template($output);
                $isshownumpartscorrect = false;
            }

            $specificgradedetailfeedback = new specific_grade_detail_feedback($this->qa);
            $data['specificfeedback'] = $specificgradedetailfeedback->export_for_template($output);
            $hint = $this->qa->get_applicable_hint();
        }

        if ($this->options->numpartscorrect && $isshownumpartscorrect) {
            $numpartscorrect = new num_parts_correct($this->qa);
            $data['numpartscorrect'] = $numpartscorrect->export_for_template($output);
        }

        if ($hint) {
            $data['hint'] = $question->format_hint($hint, $this->qa);

        }

        if ($this->options->generalfeedback) {
            // Literal render out but we trust the teacher.
            $data['generalfeedback'] = $question->format_generalfeedback($this->qa);
        }

        if ($this->options->rightanswer) {
            $correctresponse = new correct_response($this->qa);
            $data['rightanswer'] = $correctresponse->export_for_template($output);
        }

        return $data;
    }
}
