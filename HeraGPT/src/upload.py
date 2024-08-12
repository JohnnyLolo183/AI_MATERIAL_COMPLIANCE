import os
import re
import pdfplumber
import json
from flask import Flask, request, redirect, url_for, render_template, send_from_directory, jsonify
from werkzeug.utils import secure_filename
from urllib.parse import urlparse, unquote

app = Flask(__name__)
app.secret_key = "your_secret_key_here"
UPLOAD_FOLDER = "uploads"
ALLOWED_EXTENSIONS = {'pdf'}

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
app.config['MAX_CONTENT_LENGTH'] = 10 * 1024 * 1024  # 10 MB

def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

def extract_data_from_pdf(pdf_path):
    data = {}
    with pdfplumber.open(pdf_path) as pdf:
        full_text = ''
        for page_num, page in enumerate(pdf.pages, start=1):
            page_text = page.extract_text()
            full_text += page_text
            print(f"Page {page_num} Text: {page_text}")

        # Extracting specific values
        data['manufacturer'] = extract_value(r'Customer:\s*(.+?)\s+Supplier:', full_text)
        data['certificate_number'] = extract_value(r'Certificate No\.\s*:\s*([A-Za-z0-9\-]+)', full_text)
        
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

    print("Final Extracted data:", data)
    return data

def extract_value(pattern, text):
    match = re.search(pattern, text, re.IGNORECASE)
    if match:
        return match.group(1).strip()
    return ''

def extract_items_covered(text):
    items = {}
    match = re.search(
        r'Item\s+No\s+(\S+)\s+Heat\s+No\s+(\S+)\s+Customer\s+Order\s+(\S+)\s+(.+?)\s+Grade\s+(.+)',
        text, re.IGNORECASE
    )
    if match:
        items['item_number'] = match.group(1)
        items['materials_heat_no'] = match.group(2)
        items['customer_order'] = match.group(3)
        items['material_section'] = match.group(4).strip()
        items['material_grade'] = match.group(5).strip()
    return items

def extract_chemical_analysis(text):
    chemical_data = {}
    chemical_elements = ["C", "Mn", "P", "S", "Ni", "Cr", "Mo", "Cu", "Al", "Nb", "Ti", "B", "V"]
    for element in chemical_elements:
        match = re.search(rf'{element}\s*(\.\d+|\d+\.\d+|\d+)', text)
        if match:
            chemical_data[element] = match.group(1)
    return chemical_data

def extract_mechanical_analysis(text):
    mechanical_data = {}
    match_ys = re.search(r'YS\s+(\d+)\s+MPa', text)
    match_uts = re.search(r'UTS\s+(\d+)\s+MPa', text)
    match_elongn = re.search(r'ELONGN\s+(\d+)\s+\%', text)
    
    if match_ys:
        mechanical_data['YS'] = match_ys.group(1)
    if match_uts:
        mechanical_data['UTS'] = match_uts.group(1)
    if match_elongn:
        mechanical_data['ELONGN'] = match_elongn.group(1)
    
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

    # Extract data directly from the PDF
    extracted_data = extract_data_from_pdf(pdf_full_path)

    # Save the extracted data to a JSON file
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
