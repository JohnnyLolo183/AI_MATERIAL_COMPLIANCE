from flask import Flask, request, redirect, url_for, flash, render_template, send_from_directory, jsonify
import os
from werkzeug.utils import secure_filename
import mimetypes

app = Flask(__name__)
app.secret_key = 'default_secret_key'  # Replace with a secure secret key if needed
UPLOAD_FOLDER = 'uploads'
ALLOWED_EXTENSIONS = {'pdf'}
MAX_FILE_SIZE = 10 * 1024 * 1024  # 10 MB

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
app.config['MAX_CONTENT_LENGTH'] = MAX_FILE_SIZE

def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

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
        
        if not os.path.exists(UPLOAD_FOLDER):
            os.makedirs(UPLOAD_FOLDER)

        file.save(file_path)

        # Check if the file is a PDF
        mime_type, _ = mimetypes.guess_type(file_path)
        if mime_type != 'application/pdf':
            os.remove(file_path)
            flash('Uploaded file is not a PDF.')
            return jsonify({'error': 'Uploaded file is not a PDF.'}), 400
        
        return jsonify({'result': True, 'pdfUrl': file_path})

    return jsonify({'error': 'Invalid file type'}), 400

@app.route('/extract')
def extract():
    pdf_path = request.args.get('pdf')
    if not pdf_path or not os.path.isfile(pdf_path):
        return "File not found", 404
    return render_template('extract.html', pdf=pdf_path)

@app.route('/process_extraction')
def process_extraction():
    pdf_path = request.args.get('pdf')
    if not pdf_path or not os.path.isfile(pdf_path):
        return "File not found", 404

    # Your processing logic here
    # For now, just redirect to a placeholder page
    return f"Processing {pdf_path}..."

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
    app.run(debug=True)
