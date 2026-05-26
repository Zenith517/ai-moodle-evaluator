<?php
require_once('../../config.php');
require_login();
$env_path = __DIR__ . '/.env';

// 2. Safely load and parse the .env file
if (file_exists($env_path)) {
    $env_vars = parse_ini_file($env_path);
    $gemini_api_key = $env_vars['GEMINI_API_KEY'];
} else {
    // Stop execution if the .env file is missing to prevent errors
    die("Security Error: .env file not found.");
}
// =========================================================================
// 🛠️ CRITICAL MOODLE FIXES
// =========================================================================
// Force PHP to show errors instead of a blank white screen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Moodle Page Setup to prevent fatal crashes
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ai_tutor/process_request.php'));
$PAGE->set_title('AI Tutor Evaluation');
$PAGE->set_heading('AI Tutor Results');

// =========================================================================
// CONFIGURATION ZONE
// =========================================================================

// 🌐 The URL of your local Python Flask Bridge
$flask_api_url = 'http://127.0.0.1:5000/evaluate';

// Get the action from the Moodle form
$action = optional_param('action', '', PARAM_TEXT);

// =========================================================================
// LANE 1: QUIZ GENERATION (Native PDF Vision + Upload Debugger)
// =========================================================================
if ($action === 'generate_questions') { 
    // Dynamically grab inputs from your Moodle frontend form
    $blooms_level = optional_param('bloom_level', 'understand', PARAM_TEXT);
    $num_questions = optional_param('num_questions', 5, PARAM_INT);
    
    // --- START SYLLABUS/TEXTBOOK PDF EXTRACTION ---
    $syllabus_pdf_base64 = "";
    if (isset($_FILES['syllabus_pdf'])) {
        if ($_FILES['syllabus_pdf']['error'] === UPLOAD_ERR_OK) {
            $syllabus_pdf_base64 = base64_encode(file_get_contents($_FILES['syllabus_pdf']['tmp_name']));
        } else {
            // This will print the exact PHP error code (1 means file too big, 4 means no file selected, etc.)
            die("Error: File upload failed with PHP Error Code: " . $_FILES['syllabus_pdf']['error']);
        }
    } else {
        die("Error: 'syllabus_pdf' was not found in the POST request at all. Check your form enctype and input name.");
    }
    // --- END EXTRACTION ---

    // 🔥 UPGRADED TO GEMINI 3.1 PRO PREVIEW 🔥
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-pro-preview:generateContent?key=' . $gemini_api_key;
    
    // STRICT ZERO-PREAMBLE + ONE-SHOT XML TEMPLATE PROMPT
    $prompt_text = "Act as an expert professor. Read the attached document. Generate exactly $num_questions multiple-choice questions in Moodle XML format based STRICTLY on the facts in this document, targeting the '$blooms_level' level of Bloom's Taxonomy. " .
              "CRITICAL OUTPUT FORMATTING: You are a backend data generator. You must output ONLY raw, perfectly formatted Moodle XML containing exactly $num_questions distinct <question> blocks. " .
              "DO NOT prefix your answers with letters (A, B, C) or words like 'ERR' or 'Correct'. Just output the raw answer text. " .
              "DO NOT include markdown code blocks (do not use ```xml or ```). " .
              "You MUST use this exact XML structure for every single question:\n" .
              "<question type=\"multichoice\">\n" .
              "  <name><text>Question Concept</text></name>\n" .
              "  <questiontext format=\"html\"><text><![CDATA[<p>The question goes here?</p>]]></text></questiontext>\n" .
              "  <answer fraction=\"100\"><text><![CDATA[<p>The correct answer goes here.</p>]]></text></answer>\n" .
              "  <answer fraction=\"0\"><text><![CDATA[<p>A wrong distractor goes here.</p>]]></text></answer>\n" .
              "  <answer fraction=\"0\"><text><![CDATA[<p>Another wrong distractor goes here.</p>]]></text></answer>\n" .
              "  <answer fraction=\"0\"><text><![CDATA[<p>A third wrong distractor goes here.</p>]]></text></answer>\n" .
              "</question>";

    // Send the prompt AND the base64 PDF directly to Gemini
    $data = [
        "contents" => [
            ["parts" => [
                ["text" => $prompt_text],
                ["inline_data" => ["mime_type" => "application/pdf", "data" => $syllabus_pdf_base64]]
            ]]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    $xml_output = $result['candidates'][0]['content']['parts'][0]['text'] ?? '<error>Generation Failed</error>';
    
    // 🛡️ PHP SAFETY NET: Scrub out markdown AND rogue <quiz> tags from the AI
    $xml_output = str_replace(['```xml', '```', '<quiz>', '</quiz>'], '', $xml_output);
    $xml_output = trim($xml_output);
    
    // 🏗️ THE ROOT NODE FIX: Apply our guaranteed <quiz> wrapper
    $final_xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $final_xml .= "<quiz>\n";
    $final_xml .= $xml_output . "\n";
    $final_xml .= "</quiz>";
    
    // Push the perfectly wrapped XML file to the user for download
    header('Content-Type: application/xml');
    header('Content-Disposition: attachment; filename="moodle_quiz.xml"');
    echo $final_xml;
    exit;
}

// =========================================================================
// LANE 2: BATCH ANSWER EVALUATION (Dual-PDF Upload)
// =========================================================================
elseif ($action === 'grade_answer') {
    $max_marks = optional_param('max_marks', 10, PARAM_INT);
    $rubric_string = "accuracy::$max_marks::Standard grading rubric"; 
    
    // --- START REAL FILE EXTRACTION ---
    $question_pdf_base64 = "";
    $student_pdf_base64 = "";

    if (isset($_FILES['question_pdf']) && $_FILES['question_pdf']['error'] === UPLOAD_ERR_OK) {
        $question_pdf_base64 = base64_encode(file_get_contents($_FILES['question_pdf']['tmp_name']));
    } else {
        die("Error: Question Paper PDF was not uploaded correctly.");
    }

    if (isset($_FILES['student_pdf']) && $_FILES['student_pdf']['error'] === UPLOAD_ERR_OK) {
        $student_pdf_base64 = base64_encode(file_get_contents($_FILES['student_pdf']['tmp_name']));
    } else {
        die("Error: Student Answer PDF was not uploaded correctly.");
    }
    // --- END REAL FILE EXTRACTION ---

    // 1. The Dual-PDF Mapper Prompt 
    // 🔥 UPGRADED TO GEMINI 3.1 PRO PREVIEW 🔥
    $ocr_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-pro-preview:generateContent?key=' . $gemini_api_key;
    
    $prompt_text = "You are an expert data extractor. I have provided a Question Paper PDF and a Student Answer PDF. Extract every question from the Question Paper. Then, extract the student's text. Map the student's text to the corresponding question. CRITICAL INSTRUCTION: Even if the student's answer is completely irrelevant, off-topic, or seems to answer a different question, you MUST still map their exact text to the 'answer_text' field of the first available question. Do not return an empty array. The downstream grading system relies on you providing the text so it can evaluate it and assign a 0. Return ONLY a raw JSON array of objects. Format: [{\"question_id\": \"Q1\", \"question_text\": \"...\", \"answer_text\": \"...\"}]";

    $ocr_data = [
        "contents" => [
            ["parts" => [
                ["text" => $prompt_text],
                ["inline_data" => ["mime_type" => "application/pdf", "data" => $question_pdf_base64]],
                ["inline_data" => ["mime_type" => "application/pdf", "data" => $student_pdf_base64]]
            ]]
        ]
    ];

    $ch_ocr = curl_init($ocr_url);
    curl_setopt($ch_ocr, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_ocr, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch_ocr, CURLOPT_POST, true);
    curl_setopt($ch_ocr, CURLOPT_POSTFIELDS, json_encode($ocr_data));
    $ocr_response = curl_exec($ch_ocr);
    curl_close($ch_ocr);

    $ocr_result = json_decode($ocr_response, true);
    $gemini_json_string = $ocr_result['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
    $gemini_json_string = str_replace(['```json', '```'], '', $gemini_json_string);
    $mapped_qa_array = json_decode(trim($gemini_json_string), true);

    // 🚨 TRIPWIRE ACTIVATION 🚨
    if (empty($mapped_qa_array)) {
        die("<div style='background:#222; color:#0f0; padding:20px; font-family:monospace;'><h3>RAW GEMINI OUTPUT:</h3><pre>" . htmlspecialchars($gemini_json_string) . "</pre></div>");
    }

    // 2. Send the Array to the Python Flask Bridge
    $payload = [
        'qa_pairs' => $mapped_qa_array,
        'factors' => $rubric_string,
        'max_marks' => $max_marks
    ];

    $ch_flask = curl_init($flask_api_url);
    curl_setopt($ch_flask, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_flask, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch_flask, CURLOPT_POST, true);
    curl_setopt($ch_flask, CURLOPT_POSTFIELDS, json_encode($payload));
    $flask_response = curl_exec($ch_flask);
    $http_code = curl_getinfo($ch_flask, CURLINFO_HTTP_CODE);
    curl_close($ch_flask);

    $final_data = json_decode($flask_response, true);
    
    // 3. Render the Batch Results in Moodle UI
    echo $OUTPUT->header();
    echo "<h2>AI Tutor Evaluation Results</h2>";

    if ($http_code == 200 && $final_data['success']) {
        if (!empty($final_data['results'])) {
            foreach ($final_data['results'] as $result) {
                echo "<div style='background-color:#d4edda; color:#155724; padding:20px; border-radius:5px; margin-bottom:15px;'>";
                echo "<h3>" . htmlspecialchars($result['question_id']) . " Grade: " . htmlspecialchars($result['grade']) . " / $max_marks</h3>";
                echo "<p><strong>Question:</strong> " . htmlspecialchars($result['question_text']) . "</p>";
                echo "<p><strong>AI Feedback:</strong><br>" . nl2br(htmlspecialchars($result['feedback'])) . "</p>";
                echo "</div>";
            }
        } 
    } else {
        echo "<div style='background-color:#212529; color:#ff4d4d; padding:20px; font-family:monospace; border-radius:5px;'>";
        echo "<h3>[!] System Halt: Backend Evaluation Error</h3>";
        echo "<pre>" . htmlspecialchars($final_data['traceback'] ?? 'Unknown connection error to Flask API.') . "</pre>";
        echo "</div>";
    }
    
    echo $OUTPUT->footer();
    exit;
} else {
    echo "Invalid Action.";
}
?>