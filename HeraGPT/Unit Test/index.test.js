const { JSDOM } = require('jsdom');

// Mock the HTML structure
const html = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Certificate</title>
</head>
<body>
    <form id="pdfUploadForm" action="upload.php" method="post" enctype="multipart/form-data">
        <input type="file" name="pdfFile" id="pdfFile" accept="application/pdf" required>
        <button type="submit" class="upload-btn">Upload Certificate</button>
    </form>
</body>
</html>
`;

describe('PDF File Input and Form Submission', () => {
    let dom;
    let document;

    beforeEach(() => {
        dom = new JSDOM(html, { runScripts: "dangerously" });
        document = dom.window.document;
    });

    test('should have a file input that accepts only PDF files', () => {
        const fileInput = document.getElementById('pdfFile');
        expect(fileInput).not.toBeNull();
        expect(fileInput.getAttribute('accept')).toBe('application/pdf');
    });

    test('should require a file to be selected before submission', () => {
        const fileInput = document.getElementById('pdfFile');
        expect(fileInput.hasAttribute('required')).toBe(true);
    });

    test('should submit the form when a PDF file is selected', () => {
        const form = document.getElementById('pdfUploadForm');
        const fileInput = document.getElementById('pdfFile');

        // Mock a PDF file selection
        const mockFile = new File(['dummy content'], 'test.pdf', { type: 'application/pdf' });
        Object.defineProperty(fileInput, 'files', {
            value: [mockFile],
        });

        // Mock the form submission event
        const submitEvent = new dom.window.Event('submit');
        let formSubmitted = false;

        form.addEventListener('submit', (event) => {
            event.preventDefault(); // Prevent actual form submission
            formSubmitted = true;
        });

        // Trigger the form submission
        form.dispatchEvent(submitEvent);

        // Check if the form was submitted
        expect(formSubmitted).toBe(true);
    });

    test('should not submit the form if a non-PDF file is selected', () => {
        const form = document.getElementById('pdfUploadForm');
        const fileInput = document.getElementById('pdfFile');

        // Mock a non-PDF file selection (e.g., a .txt file)
        const mockFile = new File(['dummy content'], 'test.txt', { type: 'text/plain' });
        Object.defineProperty(fileInput, 'files', {
            value: [mockFile],
        });

        // Mock the form submission event
        const submitEvent = new dom.window.Event('submit');
        let formSubmitted = false;

        form.addEventListener('submit', (event) => {
            event.preventDefault(); // Prevent actual form submission
            formSubmitted = true;
        });

        // Trigger the form submission
        form.dispatchEvent(submitEvent);

        // Check if the form was submitted (it should not be)
        expect(formSubmitted).toBe(false);
    });
});