import os
import re
from pdfminer.high_level import extract_text

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
    # Define patterns to extract values for common elements
    elements = ['C', 'Mn', 'P', 'S', 'Si', 'Cr', 'Mo', 'Ni', 'Cu']
    data = {}
    
    for element in elements:
        # Regular expression to find the element and its value
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
        return "Compliant: This certificate complies with the standard."
    else:
        result = "Non-Compliant: This certificate fails to comply with the standard."
        for item in non_compliant_items:
            result += f"\nNon-Compliant Item: {item[0]}, Certificate Value: {item[1]}, Standard Range: {item[2][0]} - {item[2][1]}"
        return result

def main(pdf_path):
    pdf_content = extract_text_from_pdf(pdf_path)

    # Identify the mentioned standard
    match = re.search(r'AS\s*/?\s*NZS\s*\d{4}', pdf_content)
    if not match:
        return "No standard mentioned in the certificate."

    standard_name = match.group(0)

    # Read the specific standard from the local directory
    standards_directory = './NzStandards'
    standard_content = read_standard(standard_name, standards_directory)
    if not standard_content:
        return "Standard not found in the local directory."

    cert_data = extract_data(pdf_content)
    std_data = extract_data(standard_content)

    result = compare_data(cert_data, std_data)

    return result

if __name__ == "__main__":
    import sys
    if len(sys.argv) < 2:
        print("Usage: python script.py <path_to_pdf>")
        sys.exit(1)

    pdf_path = sys.argv[1]
    if not os.path.isfile(pdf_path):
        print(f"File not found: {pdf_path}")
        sys.exit(1)

    result = main(pdf_path)
    print(result)
