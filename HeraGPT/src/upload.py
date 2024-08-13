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

def extract_data_from_pdf(pdf_path):
    data = {}
    output_string = StringIO()
    with open(pdf_path, 'rb') as f:
        extract_text_to_fp(f, output_string, laparams=LAParams(), output_type='text', codec=None)
    full_text = output_string.getvalue()
    
    # Print the full extracted text for debugging
    print(full_text)
    
    # Regex-based extraction
    data['certificate_number'] = extract_value(r'Certificate No\.\s*:\s*([\w\d]+)', full_text)
    data['manufacturer'] = extract_value(r'Customer:\s*([\w\s&]+)', full_text)
    
    # Use NER model to fill in the gaps
    doc = nlp(full_text)
    for ent in doc.ents:
        if ent.label_ == "CERTIFICATE_NUMBER" and not data.get('certificate_number'):
            data['certificate_number'] = ent.text
        elif ent.label_ == "CUSTOMER" and not data.get('manufacturer'):
            data['manufacturer'] = ent.text
        elif ent.label_ == "SUPPLIER" and not data.get('supplier'):
            data['supplier'] = ent.text
    
    # Continue with the rest of the extraction logic
    items_section = re.search(r'ITEMS COVERED BY THIS TEST CERTIFICATE(.*?)CHEMICAL ANALYSIS', full_text, re.DOTALL)
    if items_section:
        items_text = items_section.group(1).strip()
        data.update(extract_items_covered(items_text))

    chemical_section = re.search(r'CHEMICAL ANALYSIS(.*?)MECHANICAL TESTING', full_text, re.DOTALL)
    if chemical_section:
        chemical_text = chemical_section.group(1).strip()
        data['chemical_analysis'] = extract_chemical_analysis(chemical_text)

    mechanical_section = re.search(r'MECHANICAL TESTING(.*?)BUNDLES', full_text, re.DOTALL)
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
    return data

def extract_value(pattern, text, flags=0):
    match = re.search(pattern, text, flags)
    if match:
        return match.group(1).strip()
    return ''

def extract_items_covered(text):
    items = {}
    match = re.search(
        r'Item\s+No\s+(\S+)\s+Heat\s+No\s+(\S+)\s+Customer\s+Order\s+(\S+)\s+([\w\s\.]+)\s+(50MM\sX\s12MM\sS\.E\.\sFLAT)\s+(.+)',
        text, re.IGNORECASE
    )
    if match:
        items['item_number'] = match.group(1)
        items['materials_heat_no'] = match.group(2)
        items['customer_order'] = match.group(3)
        items['material_section'] = match.group(5).strip()
        items['material_grade'] = match.group(6).strip()
    return items

def extract_chemical_analysis(text):
    chemical_data = {}
    elements = ["C", "P", "Mn", "Si", "S", "Ni", "Cr", "Mo", "Cu", "Al-T", "Nb", "Ti", "B", "V"]

    lines = text.splitlines()

    for line in lines:
        # Check for the presence of any known element in the line
        if any(element in line for element in elements):
            # Split the line based on spaces to separate elements and values
            parts = re.split(r'\s+', line.strip())
            # Extract elements and corresponding values, ensuring that each element is paired with a value
            for i, part in enumerate(parts):
                if part in elements and i + 1 < len(parts):
                    value = parts[i + 1]
                    # Ensure that the value starts with a period, indicating a valid number
                    if value.startswith('.'):
                        chemical_data[part] = value
                    else:
                        print(f"Warning: Mismatched element and value for {part} in line: {line}")
    return chemical_data


def extract_mechanical_analysis(text):
    mechanical_data = {}
    lines = text.splitlines()

    properties = ["YS", "UTS", "ELONGN"]
    units = ["MPa", "MPa", "%"]

    for line in lines:
        for i, prop in enumerate(properties):
            # Search for property followed by value and unit
            match = re.search(rf'{prop}\s+([\d.]+)\s+{units[i]}', line)
            if match:
                mechanical_data[prop] = match.group(1)

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
