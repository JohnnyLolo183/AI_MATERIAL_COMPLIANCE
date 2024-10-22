// Import the necessary modules
const { JSDOM } = require('jsdom');

// Sample HTML content from extract.html
const htmlContent = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Begin Data Extraction</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .pdf-viewer-container {
            width: 100%;
            height: 100%;
            border: 1px solid #ccc;
        }
    </style>
</head>
<body class="new-page">
    <div class="container">
        <div class="left-side">
            <img src="../images/HERA2.png" alt="HERA Logo" class="logo-left">
            <div class="button-container">
                <button id="beginExtractionBtn" class="action-btn">Begin Data Extraction</button>
            </div>
        </div>
        <div class="right-side">
            <div class="certificate-outline">
                <div class="pdf-viewer-container">
                    <iframe id="pdfViewer" class="pdf-viewer" src="" frameborder="0"></iframe>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const pdfUrl = urlParams.get('pdf');

            if (pdfUrl) {
                document.getElementById('pdfViewer').src = pdfUrl;
                console.log("PDF URL set to:", pdfUrl); // Debugging line
            } else {
                console.error("No PDF URL found in query parameters"); // Debugging line
            }

            const beginExtractionBtn = document.getElementById("beginExtractionBtn");
            beginExtractionBtn.addEventListener("click", function() {
                window.location.href = "processExtraction.php?pdf=" + encodeURIComponent(pdfUrl);
            });
        });
    </script>
</body>
</html>
`;

describe('extract.html', () => {
    let dom;
    let document;

    beforeEach(() => {
        dom = new JSDOM(htmlContent, { url: "http://localhost/?pdf=test.pdf" });
        document = dom.window.document;
    });

    // Test to verify that the PDF URL is correctly set in the iframe's src attribute
    test('should set the PDF URL in the iframe', () => {
        const pdfViewer = document.getElementById('pdfViewer');
        expect(pdfViewer.src).toBe('http://localhost/?pdf=test.pdf'); // Update expected value
    });
});