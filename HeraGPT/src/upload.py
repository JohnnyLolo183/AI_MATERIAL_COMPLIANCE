from flask import Flask, request, redirect, url_for, flash, render_template, send_from_directory, jsonify
import os
from werkzeug.utils import secure_filename
import mimetypes
from load_env import load_env
from pdfminer.high_level import extract_text
import json
import re
from urllib.parse import urlparse, unquote

# Load environment variables
load_env('../.env')

app = Flask(__name__)
app.secret_key = os.getenv('SECRET_KEY')
UPLOAD_FOLDER = os.getenv('UPLOAD_FOLDER')
ALLOWED_EXTENSIONS = {'pdf'}
MAX_FILE_SIZE = 10 * 1024 * 1024  # 10 MB

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
app.config['MAX_CONTENT_LENGTH'] = MAX_FILE_SIZE

def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

def extract_text_from_pdf(pdf_path):
    text = extract_text(pdf_path)
    return text

def extract_data(text):
    data = {
        'manufacturer': extract_value(r'Manufacturer:\s*(.+)', text),
        'certificate_number': extract_value(r'Certificate number:\s*(.+)', text),
        'material_standard': extract_value(r'Material Standard:\s*(.+)', text),
        'material_grade': extract_value(r'Material Grade:\s*(.+)', text),
        'description': extract_value(r'Description:\s*(.+)', text),
        'chemical_analysis': extract_elements(text),
        'mechanical_analysis': extract_mechanical(text)
    }
    return data

def extract_value(pattern, text):
    match = re.search(pattern, text)
    if match:
        return match.group(1)
    return ''

def extract_elements(text):
    elements = ['C', 'Mn', 'P', 'S', 'Si', 'Cr', 'Mo', 'Ni', 'Cu']
    data = {}
    for element in elements:
        match = re.search(rf'\b{element}\b\s*[:=]?\s*(\d+(\.\d+)?)', text, re.IGNORECASE)
        if match:
            data[element] = {'symbol': element, 'percentage': float(match.group(1))}
    return data

def extract_mechanical(text):
    mechanical_properties = ['Yield strength', 'Ultimate tensile', 'Elongation']
    data = {}
    for property in mechanical_properties:
        match = re.search(rf'{property}:\s*(\d+(\.\d+)?)', text, re.IGNORECASE)
        if match:
            data[property] = {'unit': 'MPa' if 'strength' in property else '%', 'result': float(match.group(1))}
    return data

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
    
    # Extract the filename from the URL
    parsed_url = urlparse(pdf_url)
    pdf_path = unquote(parsed_url.path)
    
    # Ensure the path is relative and points to the correct uploads folder
    if pdf_path.startswith('/'):
        pdf_path = pdf_path[1:]

    pdf_full_path = os.path.join(app.config['UPLOAD_FOLDER'], os.path.basename(pdf_path))
    
    if not pdf_full_path or not os.path.isfile(pdf_full_path):
        return "File not found", 404

    # Extract text from the uploaded PDF
    pdf_content = extract_text_from_pdf(pdf_full_path)

    if not pdf_content:
        return "Failed to extract text from the certificate PDF.", 500

    # Extract data
    extracted_data = extract_data(pdf_content)

    # Save the result to a JSON file
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
