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
    elements = ['C', 'Mn', 'P', 'S', 'Si', 'Cr', 'Mo', 'Ni', 'Cu']
    data = {}
    
    for element in elements:
        match = re.search(rf'\b{element}\b\s*[:=]?\s*(\d+(\.\d+)?)', text, re.IGNORECASE)
        if match:
            data[element] = float(match.group(1))

    return data

def compare_data(cert_data, std_data):
    compliant = True
    non_compliant_items = []

    for element, cert_value in cert_data.items():
        std_value = std_data.get(element)
        if std_value is not None:
            min_val, max_val = std_value
            if not (min_val <= cert_value <= max_val):
                compliant = False
                non_compliant_items.append((element, cert_value, std_value))

    if compliant:
        result = "Compliant: This certificate complies with the standard."
    else:
        result = "Non-Compliant: This certificate fails to comply with the standard."
        for item in non_compliant_items:
            result += f"\nNon-Compliant Item: {item[0]}, Certificate Value: {item[1]}, Standard Range: {item[2][0]} - {item[2][1]}"
    
    return {
        "compliant": compliant,
        "result": result,
        "non_compliant_items": non_compliant_items
    }

@app.route('/process_extraction')
def process_extraction():
    pdf_path = request.args.get('pdf')
    if not pdf_path or not os.path.isfile(pdf_path):
        return "File not found", 404

    # Extract text from the uploaded PDF
    pdf_content = extract_text_from_pdf(pdf_path)

    if not pdf_content:
        return "Failed to extract text from the certificate PDF.", 500

    # Identify the mentioned standard
    match = re.search(r'AS\s*/?\s*NZS\s*\d{4}', pdf_content)
    if not match:
        return "No standard mentioned in the certificate.", 400

    standard_name = match.group(0)

    # Read the specific standard from the local directory
    standards_directory = './NzStandards'
    standard_content = read_standard(standard_name, standards_directory)
    if not standard_content:
        return "Standard not found in the local directory.", 404

    # Extract data and compare
    cert_data = extract_data(pdf_content)
    std_data = extract_data(standard_content)
    comparison_result = compare_data(cert_data, std_data)

    # Save the result to a JSON file
    result_path = os.path.join('uploads', f"{os.path.basename(pdf_path)}_result.json")
    with open(result_path, 'w') as f:
        json.dump(comparison_result, f, indent=4)

    return redirect(url_for('export', result=result_path, pdf=pdf_path))

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
