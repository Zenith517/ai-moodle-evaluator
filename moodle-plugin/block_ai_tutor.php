<?php
class block_ai_tutor extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_ai_tutor');
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $url = new moodle_url('/blocks/ai_tutor/process_request.php');
        
        // ==========================================
        // TOOL 1: GENERATE QUESTIONS FORM
        // ==========================================
        $html = '<div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px dashed #ccc;">';
        $html .= '<h4 style="margin-top: 0; color: #0f6cbf;">📄 Generate Quiz</h4>';
        $html .= '<form action="' . $url . '" method="POST" enctype="multipart/form-data" style="font-family: Arial, sans-serif;">';
        
        $html .= '<input type="hidden" name="action" value="generate_questions">';
        
        // 1. File Upload Field (FIXED to match backend expected name)
        $html .= '<div style="margin-bottom: 12px;">';
        $html .= '<label for="syllabus_pdf" style="font-weight: bold; font-size: 14px;">Upload PDF Lecture:</label><br>';
        $html .= '<input type="file" name="syllabus_pdf" id="syllabus_pdf" accept=".pdf" required style="width: 100%; margin-top: 4px;">';
        $html .= '</div>';
        
        // 2. Bloom's Taxonomy Dropdown
        $html .= '<div style="margin-bottom: 12px;">';
        $html .= '<label for="bloom_level" style="font-weight: bold; font-size: 14px;">Bloom\'s Taxonomy Level:</label><br>';
        $html .= '<select name="bloom_level" id="bloom_level" required style="width: 100%; padding: 5px; margin-top: 4px;">';
        $html .= '<option value="remember">1. Remember</option>';
        $html .= '<option value="understand">2. Understand</option>';
        $html .= '<option value="apply" selected>3. Apply</option>';
        $html .= '<option value="analyze">4. Analyze</option>';
        $html .= '<option value="evaluate">5. Evaluate</option>';
        $html .= '<option value="create">6. Create</option>';
        $html .= '</select>';
        $html .= '</div>';
        
        // 3. Question Type Dropdown
        $html .= '<div style="margin-bottom: 12px;">';
        $html .= '<label for="question_type" style="font-weight: bold; font-size: 14px;">Question Type:</label><br>';
        $html .= '<select name="question_type" id="question_type" required style="width: 100%; padding: 5px; margin-top: 4px;">';
        $html .= '<option value="mcq">Standard MCQ (1 Correct)</option>';
        $html .= '<option value="multiselect">Multiple Correct Answers</option>';
        $html .= '<option value="fillintheblank">Fill in the Blanks</option>';
        $html .= '<option value="shortanswer">One Word / Line Answer</option>';
        $html .= '<option value="matching">Match the Column</option>';
        $html .= '<option value="case_based">Case-Based Scenario</option>';
        $html .= '</select>';
        $html .= '</div>';

        // 4. Number of Questions Dropdown
        $html .= '<div style="margin-bottom: 12px;">';
        $html .= '<label for="num_questions" style="font-weight: bold; font-size: 14px;">Number of Questions:</label><br>';
        $html .= '<select name="num_questions" id="num_questions" required style="width: 100%; padding: 5px; margin-top: 4px;">';
        $html .= '<option value="3">3 Questions</option>';
        $html .= '<option value="5" selected>5 Questions</option>';
        $html .= '<option value="10">10 Questions</option>';
        $html .= '</select>';
        $html .= '</div>';
        
        // 5. Submit Button
        $html .= '<div style="margin-top: 15px;">';
        $html .= '<input type="submit" value="Generate Questions" style="width: 100%; padding: 10px; background-color: #0f6cbf; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px;">';
        $html .= '</div>';
        $html .= '</form>';
        $html .= '</div>';


        // ==========================================
        // TOOL 2: BATCH EXAM GRADER (DUAL-PDF VERSION)
        // ==========================================
        $html .= '<div style="margin-bottom: 10px;">';
        $html .= '<h4 style="margin-top: 0; color: #28a745;">🤖 Batch Grade Exam (PDFs)</h4>';
        
        $html .= '<form action="' . $url . '" method="POST" enctype="multipart/form-data" style="font-family: Arial, sans-serif;">';
        
        $html .= '<input type="hidden" name="action" value="grade_answer">';
        $html .= '<input type="hidden" name="question_id" value="q_' . time() . '">';

        // Question Paper PDF Upload 
        $html .= '<div style="margin-bottom: 12px;">';
        $html .= '<label for="question_pdf" style="font-weight: bold; font-size: 14px;">Upload Question Paper (PDF):</label><br>';
        $html .= '<input type="file" name="question_pdf" id="question_pdf" accept=".pdf" required style="width: 100%; margin-top: 4px;">';
        $html .= '</div>';

        // Student Answer PDF Upload
        $html .= '<div style="margin-bottom: 12px;">';
        $html .= '<label for="student_pdf" style="font-weight: bold; font-size: 14px;">Upload Student Answer (PDF):</label><br>';
        $html .= '<input type="file" name="student_pdf" id="student_pdf" accept=".pdf" required style="width: 100%; margin-top: 4px;">';
        $html .= '</div>';

        $html .= '<div style="margin-bottom: 15px;">';
        $html .= '<label for="max_marks" style="font-weight: bold; font-size: 14px;">Max Points Per Question:</label><br>';
        $html .= '<input type="number" name="max_marks" id="max_marks" value="10" min="1" style="width: 100%; padding: 5px; margin-top: 4px;" required>';
        $html .= '</div>';

        $html .= '<div style="margin-top: 15px;">';
        $html .= '<input type="submit" value="Extract & Grade with AI" style="width: 100%; padding: 10px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px;">';
        $html .= '</div>';
        $html .= '</form>';
        $html .= '</div>';

        $this->content->text = $html;
        $this->content->footer = '<div style="font-size: 11px; color: #666; text-align: center; margin-top: 15px;">Powered by Advanced AI Core</div>';

        return $this->content;
    }
}
?>