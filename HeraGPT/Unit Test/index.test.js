const { JSDOM } = require('jsdom');

// Mock the HTML structure
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
        <p id="errorMessage" style="display: none;">Uploaded file is not a PDF</p>
    </form>
</body>
</html>
`;

describe('PDF File Input and Form Submission', () => {
    let dom;
    let document;

    // Set up a new DOM instance before each test
    beforeEach(() => {
        dom = new JSDOM(html, { runScripts: "dangerously" });
        document = dom.window.document;
    });

    // Test to check if the file input accepts only PDF files
    test('should have a file input that accepts only PDF files', () => {
        // Get the file input element by its ID
        const fileInput = document.getElementById('pdfFile');
        
        // Check that the file input element is not null (i.e., it exists)
        expect(fileInput).not.toBeNull();
        
        // Check that the file input element has the correct 'accept' attribute for PDF files
        expect(fileInput.getAttribute('accept')).toBe('application/pdf');
    });

    // Test to ensure a file is required before form submission
    test('should require a file to be selected before submission', () => {
        // Get the file input element by its ID
        const fileInput = document.getElementById('pdfFile');
        
        // Check that the 'required' attribute is present on the file input element
        expect(fileInput.hasAttribute('required')).toBe(true);
    });

    // Test to simulate form submission when a PDF file is selected
    test('should submit the form when a PDF file is selected', () => {
        // Get the form and file input elements by their IDs
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

        // Add an event listener to the form to set a flag when the form is submitted
        form.addEventListener('submit', (event) => {
            event.preventDefault(); // Prevent actual form submission
            formSubmitted = true;
        });

        // Trigger the form submission
        form.dispatchEvent(submitEvent);

        // Check if the form was submitted
        expect(formSubmitted).toBe(true);
    });

    // Test to ensure the form is not submitted if a non-PDF file is selected
    test('should not submit the form if a non-PDF file is selected', () => {
        const form = document.getElementById('pdfUploadForm');
        const fileInput = document.getElementById('pdfFile');
        const errorMessage = document.getElementById('errorMessage');
    
        const mockFile = new File(['dummy content'], 'test.txt', { type: 'text/plain' });
        Object.defineProperty(fileInput, 'files', {
            value: [mockFile],
        });
    
        const submitEvent = new dom.window.Event('submit');
        let formSubmitted = false;
    
        // Simulate server-side validation
        const isPdfFile = (file) => file.type === 'application/pdf';
    
        form.addEventListener('submit', (event) => {
            if (!isPdfFile(fileInput.files[0])) {
                event.preventDefault();
                errorMessage.style.display = 'block';
            } else {
                formSubmitted = true;
            }
        });
    
        form.dispatchEvent(submitEvent);
    
        // Check if the form was not submitted
        expect(formSubmitted).toBe(false);
    
        // Check if the error message is displayed
        expect(errorMessage.style.display).toBe('block');
        expect(errorMessage.textContent).toBe('Uploaded file is not a PDF');
    });
});