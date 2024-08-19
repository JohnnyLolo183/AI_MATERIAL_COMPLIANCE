import os
import re
import json
from flask import Flask, request, redirect, url_for, render_template, jsonify, send_from_directory
from werkzeug.utils import secure_filename
from urllib.parse import urlparse, unquote
from io import StringIO
from pdfminer.high_level import extract_text_to_fp
from pdfminer.layout import LAParams
import spacy

app = Flask(__name__)
app.secret_key = "your_secret_key_here"
UPLOAD_FOLDER = "uploads"
ALLOWED_EXTENSIONS = {'pdf'}

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
app.config['MAX_CONTENT_LENGTH'] = 10 * 1024 * 1024  # 10 MB

# Load the trained NER model
nlp = spacy.load("uploads/ner_model")

def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

def extract_first_line(pattern, text, flags=0):
    match = re.search(pattern, text, flags)
    if match:
        return match.group(1).strip().splitlines()[0]  # Get only the first line
    return ''

def extract_data_from_pdf(pdf_path):
    data = {}
    output_string = StringIO()
    with open(pdf_path, 'rb') as f:
        extract_text_to_fp(f, output_string, laparams=LAParams(), output_type='text', codec=None)
    full_text = output_string.getvalue()
    
    # Print the full extracted text for debugging
    print(full_text)
    
    # Regex-based extraction
    data['certificate_number'] = extract_value(r'(Certificate No\.|Test Certificate No\.)\s*:\s*([\w\d]+)', full_text)
    data['manufacturer'] = extract_first_line(r'(Customer|Supplier):\s*([\w\s&]+)', full_text)
    
    # Use NER model to fill in the gaps
    doc = nlp(full_text)
    for ent in doc.ents:
        if ent.label_ == "CERTIFICATE_NUMBER" and not data.get('certificate_number'):
            data['certificate_number'] = ent.text
        elif ent.label_ == "CUSTOMER" and not data.get('manufacturer'):
            data['manufacturer'] = ent.text
        elif ent.label_ == "SUPPLIER" and not data.get('supplier'):
            data['supplier'] = ent.text
    
    # Extract Items Covered section with flexibility for different headings
    items_section = re.search(r'(SPEC\. & PROD\. DETAILS COVERED BY THIS TEST CERTIFICATE.*?)(CHEMICAL ANALYSIS|MECHANICAL TESTING)', full_text, re.DOTALL)
    if items_section:
        items_text = items_section.group(1).strip()
        data['items_covered'] = extract_items_covered(items_text)

    # Extract Chemical Analysis
    chemical_section = re.search(r'CHEMICAL ANALYSIS.*?Percentage of element by mass(.*?)MECHANICAL TESTING', full_text, re.DOTALL)
    if chemical_section:
        chemical_text = chemical_section.group(1).strip()
        data['chemical_analysis'] = extract_chemical_analysis(chemical_text)

    # Extract Mechanical Testing (Restored to original logic)
    mechanical_section = re.search(r'MECHANICAL TESTING(.*?)(Yield Strength|TEST CATEGORY|BUNDLES|COMMENTS|ITEMS COVERED|Page\s+\d+|To view Measurement Uncertainty|$)', full_text, re.DOTALL)
    if mechanical_section:
        mechanical_text = mechanical_section.group(1).strip()
        data['mechanical_analysis'] = extract_mechanical_analysis(mechanical_text)

    data['comments'] = extract_comments(full_text)

    # Handle any missing data
    data = handle_missing_data(data)

    print("Final Extracted data:", data)
    return data

def handle_missing_data(data):
    if not data.get('certificate_number'):
        data['certificate_number'] = "Unknown Certificate Number"
    if not data.get('manufacturer'):
        data['manufacturer'] = "Unknown Manufacturer"
    if 'chemical_analysis' not in data:
        data['chemical_analysis'] = {}
    if 'mechanical_analysis' not in data:
        data['mechanical_analysis'] = {}
    if 'items_covered' not in data:
        data['items_covered'] = [{
            'item_number': 'N/A',
            'materials_heat_no': 'N/A',
            'material_section': 'N/A',
            'material_grade': 'N/A'
        }]
    return data

def extract_value(pattern, text, flags=0):
    match = re.search(pattern, text, flags)
    if match:
        return match.group(2).strip()
    return ''

def extract_items_covered(text):
    items = []
    match = re.findall(
        r'Item\s*No\.\s*(\S+)\s+Heat\s*No\.\s*(\S+)\s+Steel Making\s*\S+\s*Customer Order\s*\S+\s*Material Description and Specification\s*([\w\s\.]+)\s*(.*)',
        text, re.IGNORECASE
    )
    if match:
        for m in match:
            item = {
                'item_number': m[0],
                'materials_heat_no': m[1],
                'material_section': m[2].strip() if m[2] else 'N/A',
                'material_grade': m[3].strip() if m[3] else 'N/A'
            }
            items.append(item)
    return items

def extract_chemical_analysis(text):
    chemical_data = {}
    lines = text.splitlines()
    
    elements = ["C", "P", "Mn", "Si", "S", "Ni", "Cr", "Mo", "Cu", "Al", "B", "Nb", "Ti", "V"]
    values = []

    for line in lines:
        values.extend(re.findall(r'(\.\d+)', line))

    for i, element in enumerate(elements):
        chemical_data[element] = values[i] if i < len(values) else "N/A"

    return chemical_data

# Restored original extract_mechanical_analysis function
def extract_mechanical_analysis(text):
    mechanical_data = {}
    lines = text.splitlines()

    # Mechanical properties in the order they appear in the certificate
    properties = ["YS", "UTS", "ELONGN"]
    values = []

    # Extract integer values which correspond to the mechanical properties
    for line in lines:
        values.extend(re.findall(r'\b\d+\b', line))

    # Align properties with values (last three values correspond to YS, UTS, and ELONGN)
    if len(values) >= 3:
        for i, prop in enumerate(properties):
            mechanical_data[prop] = values[-(3 - i)]

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
        return redirect(url_for('index'))

    file = request.files['pdfFile']
    if file.filename == '':
        return redirect(url_for('index'))

    if file and allowed_file(file.filename):
        filename = secure_filename(file.filename)
        file_path = os.path.join(app.config['UPLOAD_FOLDER'], filename)

        if not os.path.exists(app.config['UPLOAD_FOLDER']):
            os.makedirs(app.config['UPLOAD_FOLDER'])

        file.save(file_path)

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

    extracted_data = extract_data_from_pdf(pdf_full_path)

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

if __name__ == '__main__':
    app.run(debug=True, host='127.0.0.1', port=5000)
