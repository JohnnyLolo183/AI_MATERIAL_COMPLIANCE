import os
import re
import mimetypes
import json
import pdfplumber
from flask import Flask, request, redirect, url_for, flash, render_template, send_from_directory, jsonify
from werkzeug.utils import secure_filename
from urllib.parse import urlparse, unquote

# Initialize Flask app
app = Flask(__name__)
app.secret_key = "your_secret_key_here"
UPLOAD_FOLDER = "uploads"
ALLOWED_EXTENSIONS = {'pdf'}

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
app.config['MAX_CONTENT_LENGTH'] = 10 * 1024 * 1024  # 10 MB

def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

def extract_text_from_pdf(pdf_path):
    text = ""
    with pdfplumber.open(pdf_path) as pdf:
        for page in pdf.pages:
            text += page.extract_text()
    return text

def save_text_to_file(text, filename):
    txt_filename = filename.replace('.pdf', '.txt')
    txt_path = os.path.join(UPLOAD_FOLDER, txt_filename)
    with open(txt_path, 'w') as f:
        f.write(text)
    return txt_path

def extract_data(text):
    data = {
        'manufacturer': extract_value(r'Customer:\s*(.+?)\s+Supplier:', text),
        'certificate_number': extract_value(r'Certificate No\.\s*:\s*(\d+)', text),
        'item_number': extract_value(r'Item\s+No\s*(\S+)', text),
        'materials_heat_no': extract_value(r'Heat\s+No\s*(\S+)', text),
        'material_section': extract_value(r'Section\s*(.+?)\s+Grade', text),
        'material_grade': extract_value(r'Grade\s*([A-Za-z0-9\- ]+)', text),
        'chemical_analysis': extract_elements(text),
        'mechanical_analysis': extract_mechanical(text),
        'comments': extract_comments(text)
    }
    return data

def extract_value(pattern, text):
    match = re.search(pattern, text, re.IGNORECASE)
    if match:
        return match.group(1).strip()
    return ''

def extract_elements(text):
    elements_data = {}
    chemical_section = re.search(r'CHEMICAL ANALYSIS(.*?)MECHANICAL TESTING', text, re.DOTALL | re.IGNORECASE)
    if chemical_section:
        matches = re.findall(r'\b([A-Z][a-z]?)\b\s+(\.\d+|\d+\.\d+)', chemical_section.group(1))
        for match in matches:
            element_symbol, percentage = match
            elements_data[element_symbol] = {'symbol': element_symbol, 'percentage': percentage}
    return elements_data

def extract_mechanical(text):
    mechanical_data = {}
    mechanical_section = re.search(r'MECHANICAL TESTING(.*?)BUNDLES', text, re.DOTALL | re.IGNORECASE)
    if mechanical_section:
        ys_match = re.search(r'YS\s+MPa\s+(\d+)', mechanical_section.group(1))
        uts_match = re.search(r'UTS\s+MPa\s+(\d+)', mechanical_section.group(1))
        elongn_match = re.search(r'ELONGN\s+\%\s+(\d+)', mechanical_section.group(1))
        
        if ys_match:
            mechanical_data['YS'] = {'unit': 'MPa', 'result': ys_match.group(1)}
        if uts_match:
            mechanical_data['UTS'] = {'unit': 'MPa', 'result': uts_match.group(1)}
        if elongn_match:
            mechanical_data['ELONGN'] = {'unit': '%', 'result': elongn_match.group(1)}
    return mechanical_data

def extract_comments(text):
    comments_section = re.search(r'COMMENTS(.*?)(To view Measurement Uncertainty|GDTI|TEST CERTIFICATE|Page\s+\d+|\Z)', text, re.DOTALL)
    if comments_section:
        return comments_section.group(1).strip()
    return ''

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/upload', methods=['POST'])
def upload_file():
    if 'pdfFile' not in request.files:
        flash('No file part')
        return redirect(url_for('index'))

    file = request.files['pdfFile']
    if file.filename == '':
        flash('No selected file')
        return redirect(url_for('index'))

    if file and allowed_file(file.filename):
        filename = secure_filename(file.filename)
        file_path = os.path.join(app.config['UPLOAD_FOLDER'], filename)

        if not os.path.exists(app.config['UPLOAD_FOLDER']):
            os.makedirs(app.config['UPLOAD_FOLDER'])

        file.save(file_path)

        mime_type, _ = mimetypes.guess_type(file_path)
        if mime_type != 'application/pdf':
            os.remove(file_path)
            flash('Uploaded file is not a PDF.')
            return jsonify({'error': 'Uploaded file is not a PDF.'}), 400

        return jsonify({'result': True, 'pdfUrl': url_for('uploaded_file', filename=filename, _external=True)})

    return jsonify({'error': 'Invalid file type'}), 400

@app.route('/uploads/<filename>')
def uploaded_file(filename):
    return send_from_directory(app.config['UPLOAD_FOLDER'], filename)

@app.route('/extract')
def extract():
    pdf_path = request.args.get('pdf')
    return render_template('extract.html', pdf=pdf_path)

@app.route('/process_extraction')
def process_extraction():
    pdf_url = request.args.get('pdf')
    
    parsed_url = urlparse(pdf_url)
    pdf_path = unquote(parsed_url.path)
    
    if pdf_path.startswith('/'):
        pdf_path = pdf_path[1:]

    pdf_full_path = os.path.join(app.config['UPLOAD_FOLDER'], os.path.basename(pdf_path))
    
    if not pdf_full_path or not os.path.isfile(pdf_full_path):
        return "File not found", 404

    pdf_content = extract_text_from_pdf(pdf_full_path)

    if not pdf_content:
        return "Failed to extract text from the certificate PDF.", 500

    # Save the extracted text to a file for reference
    txt_path = save_text_to_file(pdf_content, os.path.basename(pdf_full_path))
    print(f"Text extracted and saved to {txt_path}")

    # Extract data
    extracted_data = extract_data(pdf_content)

    result_path = os.path.join(app.config['UPLOAD_FOLDER'], f"{os.path.basename(pdf_path)}_result.json")
    with open(result_path, 'w') as f:
        json.dump(extracted_data, f, indent=4)

    return redirect(url_for('export', result=result_path, pdf=pdf_full_path))

@app.route('/export')
def export():
    result_path = request.args.get('result')
    pdf_path = request.args.get('pdf')

    if not result_path or not os.path.isfile(result_path):
        return "Result file not found", 404

    with open(result_path, 'r') as f:
        result_data = json.load(f)

    return render_template('export.html', data=result_data, pdf=pdf_path)

@app.route('/delete_uploads')
def delete_uploads():
    uploads_dir = app.config['UPLOAD_FOLDER']
    if os.path.exists(uploads_dir):
        files = os.listdir(uploads_dir)
        for file in files:
            file_path = os.path.join(uploads_dir, file)
            if os.path.isfile(file_path):
                os.remove(file_path)
    return redirect(url_for('index'))

@app.route('/static/<path:filename>')
def send_static(filename):
    return send_from_directory('.', filename)

if __name__ == '__main__':
    app.run(debug=True, host='127.0.0.1', port=5000)
