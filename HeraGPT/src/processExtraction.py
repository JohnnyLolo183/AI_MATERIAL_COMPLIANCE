import os
import re
import json
from pdfminer.high_level import extract_text
from flask import Flask, request, jsonify, redirect, url_for, render_template

app = Flask(__name__)

def extract_text_from_pdf(pdf_path):
    text = extract_text(pdf_path)
    return text

def read_standard(standard_name, directory):
    files = [f for f in os.listdir(directory) if f.endswith('.pdf')]
    for file in files:
        if re.search(re.escape(standard_name).replace('/', '-'), file, re.IGNORECASE):
            return extract_text_from_pdf(os.path.join(directory, file))
    return None

def extract_data(text):
    data = {}
    # Assuming a similar structure to the mockup provided
    data['manufacturer'] = re.search(r'Manufacturer:\s*(.+)', text).group(1)
    data['certificate_number'] = re.search(r'Certificate number:\s*(.+)', text).group(1)
    data['material_standard'] = re.search(r'Material Standard:\s*(.+)', text).group(1)
    data['material_grade'] = re.search(r'Material Grade:\s*(.+)', text).group(1)
    data['description'] = re.search(r'Description:\s*(.+)', text).group(1)

    # Extract chemical analysis
    chemical_analysis = {}
    elements = ['C', 'Mn', 'P', 'S', 'Si', 'Cr', 'Mo', 'Ni', 'Cu']
    for element in elements:
        match = re.search(rf'\b{element}\b\s*[:=]?\s*(\d+(\.\d+)?)', text, re.IGNORECASE)
        if match:
            chemical_analysis[element] = match.group(1)
    data['chemical_analysis'] = chemical_analysis

    # Extract mechanical analysis
    mechanical_analysis = {}
    properties = ['Yield strength', 'Ultimate tensile', 'Elongation']
    for prop in properties:
        match = re.search(rf'\b{prop}\b\s*[:=]?\s*(\d+)', text, re.IGNORECASE)
        if match:
            mechanical_analysis[prop] = match.group(1)
    data['mechanical_analysis'] = mechanical_analysis

    return data

@app.route('/process_extraction')
def process_extraction():
    pdf_path = request.args.get('pdf')
    if not pdf_path or not os.path.isfile(pdf_path):
        return "File not found", 404

    # Extract text from the uploaded PDF
    pdf_content = extract_text_from_pdf(pdf_path)
    extracted_data = extract_data(pdf_content)

    # Save the result to a JSON file
    result_path = os.path.join('uploads', f"{os.path.basename(pdf_path)}_result.json")
    with open(result_path, 'w') as f:
        json.dump(extracted_data, f, indent=4)

    return render_template('export.html', pdf=pdf_path, data=extracted_data)

@app.route('/export')
def export():
    result_path = request.args.get('result')
    pdf_path = request.args.get('pdf')

    if not result_path or not os.path.isfile(result_path):
        return "Result file not found", 404

    with open(result_path, 'r') as f:
        result_data = json.load(f)

    return render_template('export.html', result=json.dumps(result_data, indent=4), pdf=pdf_path)

if __name__ == '__main__':
    app.run(debug=True)
