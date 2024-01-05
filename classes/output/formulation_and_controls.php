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

use question_attempt;
use question_display_options;
use question_state;

/**
 * Renderable class for the displaying the formulation and controls of the question.
 *
 * @package    qtype_ordering
 * @copyright  2023 Ilya Tregubov <ilya.a.tregubov@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class formulation_and_controls extends renderable_base {

    /** @var question_display_options $options The question options. */
    protected $options;

    /**
     * The class constructor.
     *
     * @param question_attempt $qa The question attempt object.
     */
    public function __construct(question_attempt $qa, question_display_options $options) {
        $this->options = $options;
        parent::__construct($qa);
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $PAGE;

        $data = [];
        $question = $this->qa->get_question();

        $response = $this->qa->get_last_qt_data();
        $question->update_current_response($response);

        $currentresponse = $question->currentresponse;
        $correctresponse = $question->correctresponse;

        // Generate fieldnames and ids
        // response_fieldname : 1_response_319
        // response_name      : q27:1_response_319
        // response_id        : id_q27_1_response_319
        // sortable_id        : id_sortable_q27_1_response_319.
        $responsefieldname = $question->get_response_fieldname();
        $responsename      = $this->qa->get_qt_field_name($responsefieldname);
        $responseid        = 'id_'.preg_replace('/[^a-zA-Z0-9]+/', '_', $responsename);
        $sortableid        = 'id_sortable_'.$question->id;
        $ablockid          = 'id_ablock_'.$question->id;

        // Set CSS classes for sortable list and sortable items.
        if ($class = $question->get_ordering_layoutclass()) {
            $data['layoutclass'] = $class;
        }
        if ($class = $question->options->numberingstyle) {
            $data['numberingstyle'] = $class;
        }

        // In the multi-tries, the highlight response base on the hint highlight option.
        if ((isset($this->options->highlightresponse) && $this->options->highlightresponse) || !$this->qa->get_state()->is_active()) {
            $data['active'] = 'notactive';
        } else if ($this->qa->get_state()->is_active()) {
            $data['active'] = 'active';
        }

        $sortableitem = 'sortableitem';
        if ($this->options->readonly) {
            $data['readonly'] = true;
            $sortableitem = '';
        }

        $data['questiontext'] = $question->format_questiontext($this->qa);
        $data['ablockid'] = $ablockid;
        $data['sortableid'] = $sortableid;
        $data['responsename'] = $responsename;
        $data['responseid'] = $responseid;

        if (count($currentresponse)) {

            // Initialize the cache for the  answers' md5keys
            // this represents the initial position of the items.
            $md5keys = [];

            // Generate ordering items.
            foreach ($currentresponse as $position => $answerid) {

                if (!array_key_exists($answerid, $question->answers) || !array_key_exists($position, $correctresponse)) {
                    continue; // Shouldn't happen !!
                }

                $img = '';
                // Set the CSS class and correctness img for this response.
                // (correctness: HIDDEN=0, VISIBLE=1, EDITABLE=2).
                switch ($this->options->correctness) {
                    case question_display_options::VISIBLE:
                        $score = $question->get_ordering_item_score($question, $position, $answerid);
                        if (isset($score['maxscore'])) {
                            $renderer = $PAGE->get_renderer('qtype_ordering');
                            $img = $renderer->feedback_image($score['fraction']);
                        }
                        $class = trim("$sortableitem " . $score['class']);
                        break;
                    case question_display_options::HIDDEN:
                    case question_display_options::EDITABLE:
                        $class = $sortableitem;
                        break;
                    default:
                        $class = '';
                        break;
                }

                if (isset($this->options->highlightresponse) && $this->options->highlightresponse) {
                    $score = $question->get_ordering_item_score($question, $position, $answerid);
                    if (!isset($renderer)) {
                        $renderer = $PAGE->get_renderer('qtype_ordering');
                    }
                    $img = $renderer->feedback_image($score['fraction']);
                    $class = trim("$sortableitem ". $score['class']);
                }

                // Format the answer text.
                $answer = $question->answers[$answerid];
                $answertext = $question->format_text($answer->answer, $answer->answerformat,
                    $this->qa, 'question', 'answer', $answerid);

                // The original "id" revealed the correct order of the answers
                // because $answer->fraction holds the correct order number.
                // Therefore, we use the $answer's md5key for the "id".
                $data['answers'][] = ['answertext' => $img . $answertext, 'class' => $class, 'id' => $answer->md5key];

                // Cache this answer key.
                $md5keys[] = $answer->md5key;
            }
        }

        $data['value'] = implode(',', $md5keys);

        return $data;
    }
}
