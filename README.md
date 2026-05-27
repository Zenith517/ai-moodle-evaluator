# AI-Powered Evaluation and Assessment Microservice for Moodle

An enterprise-grade, decoupled AI microservice designed to safely augment the Moodle Learning Management System (LMS). This platform automates the generation of Moodle XML assessments and objective grading of handwritten exams without risking LMS database corruption or server timeouts.

## 🚀 Key Features

* **Intelligent Assessment Generation (Lane 1):** Utilizes Google's Gemini 3.1 Pro Vision API and strict One-Shot Template Prompting to extract context from unstructured syllabus PDFs and generate 100% database-compliant Moodle XML quizzes.
* **Multi-Agent Batch Grading (Lane 2):** Maps handwritten student answers to question papers using Multimodal AI. Evaluates subjective responses via a programmatic Multi-Agent Debate Loop to eliminate single-model hallucinations and leniency bias.
* **Decoupled Architecture:** Moodle's native PHP acts strictly as a secure router and string sanitizer, offloading all heavy machine learning computations to an asynchronous Python Flask microservice.

## 🛠️ Tech Stack

* **Frontend / LMS Core:** Moodle LMS, PHP, HTML/CSS
* **Backend Microservice:** Python 3.x, Flask
* **Artificial Intelligence:** Google Gemini 3.1 Pro (Vision API), LLM Multi-Agent Debate Architecture
* **Data Handling:** JSON Serialization, Base64 Encoding, Moodle XML

## 🏗️ Architecture Overview

The structural integrity of this system relies on a strict separation of concerns, insulating Moodle’s synchronous PHP environment from the resource-intensive demands of modern generative AI models. 

1. **Client Input:** Secure ingestion of PDFs via Moodle's PHP frontend.
2. **Context Extraction:** Multimodal mapping via the Gemini Vision API.
3. **Serialization:** Network transmission of JSON payloads to the Python backend.
4. **AI Orchestration:** The Flask bridge executes the Multi-Agent Debate loop.
5. **UI Rendering:** Evaluated data is securely returned to Moodle for frontend display.

## ⚙️ Installation & Setup

### Prerequisites
* A running instance of Moodle LMS (local or hosted)
* Python 3.10+
* Google Gemini API Key

### 1. Backend (Python Flask) Setup
\`\`\`bash
cd backend-python
python -m venv venv
source venv/bin/activate  # On Windows use: venv\Scripts\activate
pip install -r requirements.txt
\`\`\`
* Create a `.env` file inside `backend-python/` and add your API key: `GEMINI_API_KEY=your_key_here`
* Start the Flask bridge:
\`\`\`bash
python api.py
\`\`\`

### 2. Frontend (Moodle Plugin) Setup
* Copy the contents of the `moodle-plugin/` directory into your Moodle installation under `/blocks/ai_tutor/`.
* Log in to Moodle as an Administrator to trigger the installation/upgrade script.
* Ensure the local Moodle server can communicate with the Flask API running on `http://127.0.0.1:5000`.

## 👨‍💻 Author
**Somesh Nikhare**
IDD in Computer Science and Engineering
**Archit Vyas**
Btech in Electronics Engineering
