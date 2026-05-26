from flask import Flask, request, jsonify
import csv
import subprocess
import os
import logging

app = Flask(__name__)

@app.route('/evaluate', methods=['POST'])
def evaluate():
    data = request.json
    qa_pairs = data.get('qa_pairs', [])
    
    # Format: Name::Weight::Description
    factors = "Relevance_and_Accuracy::1.0::CRITICAL: First verify if the answer matches the question topic. If the answer is off-topic (e.g. answering about animation when asked about geography), you MUST give a final score of 0.0. Only grade accuracy and clarity if the topic matches."
    
    os.makedirs('data', exist_ok=True)
    input_csv = 'data/moodle_input.csv'
    output_csv = 'data/moodle_output.csv'
    
    # 🧠 SMART FIX: Remember the question text so we can send it back to Moodle later!
    question_texts = {pair.get('question_id'): pair.get('question_text', 'Unknown Question') for pair in qa_pairs}
    
    # 1. Write MULTIPLE rows to the input CSV
    try:
        with open(input_csv, mode='w', newline='', encoding='utf-8') as f:
            fieldnames = ['script_id', 'question_id', 'question_text', 'answer_text', 'max_marks', 'factors']
            writer = csv.DictWriter(f, fieldnames=fieldnames)
            writer.writeheader()
            
            for index, pair in enumerate(qa_pairs):
                writer.writerow({
                    'script_id': 'student_001',
                    'question_id': pair.get('question_id', f'Q{index+1}'),
                    'question_text': pair.get('question_text', ''),
                    'answer_text': pair.get('answer_text', ''),
                    'max_marks': data.get('max_marks', 10),
                    'factors': factors
                })
    except Exception as e:
        return jsonify({"success": False, "traceback": f"Failed to write CSV: {str(e)}"}), 500

    # 2. Trigger the Senior's AI Ensemble (It will grade all rows in the CSV!)
    try:
       # 3. Run the Senior's script using explicit OpenAI models
        result = subprocess.run([
            'python', 'run_exam_review.py',
            '--input_file', input_csv,
            '--output_file', output_csv,
            '--reviewer_models', 'gpt-3.5-turbo,gpt-4o-mini',  # TWO models so they can debate!
            '--supreme_model', 'gpt-4o',
            '--debate_rounds', '2'
        ], capture_output=True, text=True)
        
        if result.returncode != 0:
            return jsonify({"success": False, "traceback": result.stderr}), 500
        
        # 4. Read MULTIPLE rows from the output CSV and send to Moodle!
        results_array = []
        with open(output_csv, mode='r', encoding='utf-8') as f:
            reader = csv.DictReader(f)
            for row in reader:
                q_id = row.get('question_id', 'Q?')
                results_array.append({
                    'question_id': q_id,
                    'question_text': question_texts.get(q_id, 'Unknown Question'), # Maps text perfectly
                    'grade': row.get('final_total_score', 'N/A'),                  # FIXED: Grabs real score
                    'feedback': row.get('final_justification', 'No feedback.')     # FIXED: Grabs AI debate feedback
                })
                
        return jsonify({"success": True, "results": results_array}), 200

    except Exception as e:
        return jsonify({"success": False, "traceback": f"Bridge Failure: {str(e)}"}), 500

# --- Server Startup & Console Formatting ---
# Silence the default Flask/Werkzeug logging and red warnings
log = logging.getLogger('werkzeug')
log.setLevel(logging.ERROR)

if __name__ == '__main__':
    print("🚀 Moodle AI Microservice Bridge is Online in DEBUG MODE!")
    app.run(host='0.0.0.0', port=5000, debug=True)