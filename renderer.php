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

/**
 * Ordering question renderer class.
 *
 * @package    qtype_ordering
 * @copyright  2013 Gordon Bateson (gordonbateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Prevent direct access to this script.

/**
 * Generates the output for ordering questions
 *
 * @copyright  2013 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ordering_renderer extends qtype_with_combined_feedback_renderer {

    /** @var array of answerids in correct order */
    protected $correctinfo = null;

    /** @var array of answerids in order of current answer*/
    protected $currentinfo = null;

    /** @var array of scored for every item */
    protected $itemscores = array();

    /** @var bool True if answer is 100% correct */
    protected $allcorrect = null;

    /**
     * Generate the display of the formulation part of the question. This is the
     * area that contains the quetsion text, and the controls for students to
     * input their answers. Some question types also embed bits of feedback, for
     * example ticks and crosses, in this area.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        global $CFG, $DB;

        // Initialize the return result.
        $result = '';

        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();
        $question->update_current_response($response);

        $currentresponse = $question->currentresponse;
        $correctresponse = $question->correctresponse;

        // Generate fieldnames and ids
        // response_fieldname : 1_response_319
        // response_name      : q27:1_response_319
        // response_id        : id_q27_1_response_319
        // sortable_id        : id_sortable_q27_1_response_319.
        $responsefieldname = $question->get_response_fieldname();
        $responsename      = $qa->get_qt_field_name($responsefieldname);
        $responseid        = 'id_'.preg_replace('/[^a-zA-Z0-9]+/', '_', $responsename);
        $sortableid        = 'id_sortable_'.$question->id;
        $ablockid          = 'id_ablock_'.$question->id;

        // Set CSS classes for sortable list and sortable items.
        $sortablelist = 'sortablelist';
        if ($class = $question->get_ordering_layoutclass()) {
            $sortablelist .= ' '.$class; // Vertical or Horizontal.
        }
        if ($class = $question->options->numberingstyle) {
            $sortablelist .= ' numbering'.$class;
        }
        if ($qa->get_state()->is_active()) {
            $sortablelist .= ' active';
        } else {
            $sortablelist .= ' notactive';
        }

        // In the multi-tries, the highlight response base on the hint highlight option.
        if (isset($options->highlightresponse) && $options->highlightresponse) {
            $sortablelist .= ' notactive';
        }

        // Initialise JavaScript if not in readonly mode.
        if ($options->readonly) {
            // Items cannot be dragged in readonly mode.
            $sortableitem = '';
        } else {
            $sortableitem = 'sortableitem';
            $params = array($sortableid, $responseid);
            $this->page->requires->js_call_amd('qtype_ordering/drag_reorder', 'init', $params);
        }

        $result .= html_writer::tag('div', $question->format_questiontext($qa), array('class' => 'qtext'));

        $printeditems = false;
        if (count($currentresponse)) {

            // Initialize the cache for the  answers' md5keys
            // this represents the initial position of the items.
            $md5keys = array();

            // Generate ordering items.
            foreach ($currentresponse as $position => $answerid) {

                if (! array_key_exists($answerid, $question->answers)) {
                    continue; // Shouldn't happen !!
                }
                if (! array_key_exists($position, $correctresponse)) {
                    continue; // Shouldn't happen !!
                }

                if ($printeditems == false) {
                    $printeditems = true;
                    $result .= html_writer::start_tag('div', array('class' => 'ablock', 'id' => $ablockid));
                    $result .= html_writer::start_tag('div', array('class' => 'answer ordering'));
                    $result .= html_writer::start_tag('ul',  array('class' => $sortablelist, 'id' => $sortableid));
                }

                // Set the CSS class and correctness img for this response.
                // (correctness: HIDDEN=0, VISIBLE=1, EDITABLE=2).
                switch ($options->correctness) {
                    case question_display_options::VISIBLE:
                        $score = $this->get_ordering_item_score($question, $position, $answerid);
                        // To do: we need image calculation in MDL-79873.
                        list($score, $maxscore, $fraction, $percent, $class, $img) = $score;
                        $class = trim("$sortableitem $class");
                        break;
                    case question_display_options::HIDDEN:
                    case question_display_options::EDITABLE:
                        $class = $sortableitem;
                        $img = '';
                        break;
                    default:
                        $class = '';
                        $img = '';
                        break;
                }

                if (isset($options->highlightresponse) && $options->highlightresponse) {
                    $score = $this->get_ordering_item_score($question, $position, $answerid);
                    // To do: we need image calculation here in MDL-79873.
                    list($score, $maxscore, $fraction, $percent, $class, $img) = $score;
                    $class = trim("$sortableitem $class");
                }

                // Format the answer text.
                $answer = $question->answers[$answerid];
                $answertext = $question->format_text($answer->answer, $answer->answerformat,
                                                     $qa, 'question', 'answer', $answerid);

                // The original "id" revealed the correct order of the answers
                // because $answer->fraction holds the correct order number.
                // Therefore we use the $answer's md5key for the "id".
                $params = array('class' => $class, 'id' => $answer->md5key);
                $result .= html_writer::tag('li', $img.$answertext, $params);

                // Cache this answer key.
                $md5keys[] = $question->answers[$answerid]->md5key;
            }
        }

        if ($printeditems) {
            $result .= html_writer::end_tag('ul');
            $result .= html_writer::end_tag('div'); // Close answer tag.
            $result .= html_writer::end_tag('div'); // Close ablock tag.

            $result .= html_writer::empty_tag('input', array('type'  => 'hidden',
                                                             'name'  => $responsename,
                                                             'id'    => $responseid,
                                                             'value' => implode(',', $md5keys)));
        }

        return $result;
    }

    /**
     * Generate the display of the outcome part of the question. This is the
     * area that contains the various forms of feedback. This function generates
     * the content of this area belonging to the question type.
     *
     * @param question_attempt $qa The question attempt to display.
     * @param question_display_options $options Controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function feedback(question_attempt $qa, question_display_options $options) {
        $output = '';
        $hint = null;

        $isshownumpartscorrect = true;

        if ($options->feedback) {
            $output .= html_writer::nonempty_tag('div', $this->specific_feedback($qa),
                array('class' => 'specificfeedback'));

            if ($options->numpartscorrect) {
                $output .= html_writer::nonempty_tag('div', $this->num_parts_correct($qa),
                    array('class' => 'numpartscorrect'));
                $isshownumpartscorrect = false;
            }

            $output .= $this->specific_grade_detail_feedback($qa);
            $hint = $qa->get_applicable_hint();
        }

        if ($options->numpartscorrect && $isshownumpartscorrect) {
            $output .= html_writer::nonempty_tag('div', $this->num_parts_correct($qa),
                array('class' => 'numpartscorrect'));
        }

        if ($hint) {
            $output .= $this->hint($qa, $hint);
        }

        if ($options->generalfeedback) {
            $output .= html_writer::nonempty_tag('div', $this->general_feedback($qa),
                array('class' => 'generalfeedback'));
        }

        if ($options->rightanswer) {
            $output .= html_writer::nonempty_tag('div', $this->correct_response($qa),
                array('class' => 'rightanswer'));
        }

        return $output;
    }

    /**
     * Display the grade detail of the response.
     *
     * @param question_attempt $qa The question attempt to display.
     * @return string Output grade detail of the response.
     */
    public function specific_grade_detail_feedback(question_attempt $qa): string {
        $specificgradedetailfeedback = new \qtype_ordering\output\specific_grade_detail_feedback($qa);
        return $this->output->render_from_template('qtype_ordering/specific_grade_detail_feedback',
            $specificgradedetailfeedback->export_for_template($this->output));
    }

    /**
     * Generate the specific feedback. This is feedback that varies according to
     * the response the student gave.
     *
     * @param question_attempt $qa The question attempt to display.
     * @return string HTML fragment.
     */
    public function specific_feedback(question_attempt $qa) {
        return $this->combined_feedback($qa);
    }

    /**
     * Generate an automatic description of the correct response to this question.
     * Not all question types can do this. If it is not possible, this method
     * should just return an empty string.
     *
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    public function correct_response(question_attempt $qa): string {
        $correctresponse = new \qtype_ordering\output\correct_response($qa);

        return $this->output->render_from_template('qtype_ordering/correct_response',
            $correctresponse->export_for_template($this->output));
    }

    // Custom methods.

    /**
     * Fills $this->correctinfo and $this->currentinfo depending on question options.
     * TO DO: REMOVE ME in MDL-79873
     *
     * @param object $question
     */
    protected function get_response_info($question) {

        $gradingtype = $question->options->gradingtype;
        switch ($gradingtype) {

            case qtype_ordering_question::GRADING_ALL_OR_NOTHING:
            case qtype_ordering_question::GRADING_ABSOLUTE_POSITION:
            case qtype_ordering_question::GRADING_RELATIVE_TO_CORRECT:
                $this->correctinfo = $question->correctresponse;
                $this->currentinfo = $question->currentresponse;
                break;

            case qtype_ordering_question::GRADING_RELATIVE_NEXT_EXCLUDE_LAST:
            case qtype_ordering_question::GRADING_RELATIVE_NEXT_INCLUDE_LAST:
                $lastitem = ($gradingtype == qtype_ordering_question::GRADING_RELATIVE_NEXT_INCLUDE_LAST);
                $this->correctinfo = $question->get_next_answerids($question->correctresponse, $lastitem);
                $this->currentinfo = $question->get_next_answerids($question->currentresponse, $lastitem);
                break;

            case qtype_ordering_question::GRADING_RELATIVE_ONE_PREVIOUS_AND_NEXT:
            case qtype_ordering_question::GRADING_RELATIVE_ALL_PREVIOUS_AND_NEXT:
                $all = ($gradingtype == qtype_ordering_question::GRADING_RELATIVE_ALL_PREVIOUS_AND_NEXT);
                $this->correctinfo = $question->get_previous_and_next_answerids($question->correctresponse, $all);
                $this->currentinfo = $question->get_previous_and_next_answerids($question->currentresponse, $all);
                break;

            case qtype_ordering_question::GRADING_LONGEST_ORDERED_SUBSET:
            case qtype_ordering_question::GRADING_LONGEST_CONTIGUOUS_SUBSET:
                $this->correctinfo = $question->correctresponse;
                $this->currentinfo = $question->currentresponse;
                $contiguous = ($gradingtype == qtype_ordering_question::GRADING_LONGEST_CONTIGUOUS_SUBSET);
                $subset = $question->get_ordered_subset($contiguous);
                foreach ($this->currentinfo as $position => $answerid) {
                    if (array_search($position, $subset) === false) {
                        $this->currentinfo[$position] = 0;
                    } else {
                        $this->currentinfo[$position] = 1;
                    }
                }
                break;
        }
    }

    /**
     * Returns score for one item depending on correctness and question settings.
     *
     * TO DO: REMOVE ME in MDL-79873
     *
     * @param object $question
     * @param int $position
     * @param int $answerid
     * @return array (score, maxscore, fraction, percent, class, img)
     */
    protected function get_ordering_item_score($question, $position, $answerid) {

        if (! isset($this->itemscores[$position])) {

            if ($this->correctinfo === null || $this->currentinfo === null) {
                $this->get_response_info($question);
            }

            $correctinfo = $this->correctinfo;
            $currentinfo = $this->currentinfo;

            $score    = 0;    // Actual score for this item.
            $maxscore = null; // Max score for this item.
            $fraction = 0.0;  // Fraction $score / $maxscore.
            $percent  = 0;    // 100 * $fraction.
            $class    = '';   // CSS class.
            $img      = '';   // Icon to show correctness.

            switch ($question->options->gradingtype) {

                case qtype_ordering_question::GRADING_ALL_OR_NOTHING:
                    if ($this->is_all_correct()) {
                        $score = 1;
                        $maxscore = 1;
                    }
                    break;

                case qtype_ordering_question::GRADING_ABSOLUTE_POSITION:
                    if (isset($correctinfo[$position])) {
                        if ($correctinfo[$position] == $answerid) {
                            $score = 1;
                        }
                        $maxscore = 1;
                    }
                    break;

                case qtype_ordering_question::GRADING_RELATIVE_NEXT_EXCLUDE_LAST:
                case qtype_ordering_question::GRADING_RELATIVE_NEXT_INCLUDE_LAST:
                    if (isset($correctinfo[$answerid])) {
                        if (isset($currentinfo[$answerid]) && $currentinfo[$answerid] == $correctinfo[$answerid]) {
                            $score = 1;
                        }
                        $maxscore = 1;
                    }
                    break;

                case qtype_ordering_question::GRADING_RELATIVE_ONE_PREVIOUS_AND_NEXT:
                case qtype_ordering_question::GRADING_RELATIVE_ALL_PREVIOUS_AND_NEXT:
                    if (isset($correctinfo[$answerid])) {
                        $maxscore = 0;
                        $prev = $correctinfo[$answerid]->prev;
                        $maxscore += count($prev);
                        $prev = array_intersect($prev, $currentinfo[$answerid]->prev);
                        $score += count($prev);
                        $next = $correctinfo[$answerid]->next;
                        $maxscore += count($next);
                        $next = array_intersect($next, $currentinfo[$answerid]->next);
                        $score += count($next);
                    }
                    break;

                case qtype_ordering_question::GRADING_LONGEST_ORDERED_SUBSET:
                case qtype_ordering_question::GRADING_LONGEST_CONTIGUOUS_SUBSET:
                    if (isset($correctinfo[$position])) {
                        if (isset($currentinfo[$position])) {
                            $score = $currentinfo[$position];
                        }
                        $maxscore = 1;
                    }
                    break;

                case qtype_ordering_question::GRADING_RELATIVE_TO_CORRECT:
                    if (isset($correctinfo[$position])) {
                        $maxscore = (count($correctinfo) - 1);
                        $answerid = $currentinfo[$position];
                        $correctposition = array_search($answerid, $correctinfo);
                        $score = ($maxscore - abs($correctposition - $position));
                        if ($score < 0) {
                            $score = 0;
                        }
                    }
                    break;
            }

            if ($maxscore === null) {
                // An unscored item is either an illegal item
                // or last item of RELATIVE_NEXT_EXCLUDE_LAST
                // or an item in an incorrect ALL_OR_NOTHING
                // or an item from an unrecognized grading type.
                $class = 'unscored';
            } else {
                if ($maxscore == 0) {
                    $fraction = 0.0;
                    $percent = 0;
                } else {
                    $fraction = ($score / $maxscore);
                    $percent = round(100 * $fraction, 0);
                }
                switch (true) {
                    case ($fraction > 0.999999):
                        $class = 'correct';
                        break;
                    case ($fraction < 0.000001):
                        $class = 'incorrect';
                        break;
                    case ($fraction >= 0.66):
                        $class = 'partial66';
                        break;
                    case ($fraction >= 0.33):
                        $class = 'partial33';
                        break;
                    default:
                        $class = 'partial00';
                        break;
                }
                $img = $this->feedback_image($fraction);
            }

            $score = array($score, $maxscore, $fraction, $percent, $class, $img);
            $this->itemscores[$position] = $score;
        }

        return $this->itemscores[$position];
    }

    /**
     * Return true if answer is 100% correct.
     * TO DO: REMOVE ME in MDL-79873
     *
     * @return bool
     */
    protected function is_all_correct() {
        if ($this->allcorrect === null) {
            // Use "==" to determine if the two "info" arrays are identical.
            $this->allcorrect = ($this->correctinfo == $this->currentinfo);
        }
        return $this->allcorrect;
    }

    /**
     * Generate a brief statement of how many sub-parts of this question the
     * student got correct|partial|incorrect.
     *
     * @param question_attempt $qa The question attempt to display.
     * @return string HTML fragment.
     */
    protected function num_parts_correct(question_attempt $qa) {
        $numpartscorrect = new \qtype_ordering\output\num_parts_correct($qa);
        return $this->output->render_from_template('qtype_ordering/num_parts_correct',
            $numpartscorrect->export_for_template($this->output));
    }
}
