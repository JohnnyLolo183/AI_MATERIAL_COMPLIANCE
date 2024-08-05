import os
import sys
from flask import Flask, redirect, url_for

app = Flask(__name__)
UPLOAD_FOLDER = 'uploads'

def delete_files(dir_path):
    if not os.path.isdir(dir_path):
        return
    files = os.listdir(dir_path)
    for file in files:
        file_path = os.path.join(dir_path, file)
        if os.path.isfile(file_path):
            os.remove(file_path)

@app.route('/delete_uploads')
def delete_uploads():
    uploads_dir = UPLOAD_FOLDER
    delete_files(uploads_dir)
    return redirect(url_for('index'))

if __name__ == '__main__':
    # Ensure Flask runs the app context for URL generation
    with app.app_context():
        delete_uploads()
