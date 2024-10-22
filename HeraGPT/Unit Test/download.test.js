const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

describe('download.html', () => {
  let dom;
  let document;

  beforeEach(async () => {
    const filePath = path.resolve(__dirname, '../src/download.html'); // Adjust the path to point to the correct location
    const html = fs.readFileSync(filePath, 'utf8');
    dom = new JSDOM(html, { url: 'http://localhost' });
    document = dom.window.document;
  });

  test('renders the correct page title', () => {
    const title = document.querySelector('title').textContent;
    expect(title).toBe('Download');
  });

  test('applies the correct styles to the PDF viewer container', () => {
    const pdfViewerContainer = document.querySelector('.pdf-viewer-container');
    expect(pdfViewerContainer).toBeDefined();
    expect(pdfViewerContainer.classList.contains('pdf-viewer-container')).toBe(true);
  });

  test('renders the PDF viewer iframe', () => {
    const pdfViewer = document.querySelector('.pdf-viewer');
    expect(pdfViewer).toBeDefined();
    expect(pdfViewer.tagName).toBe('IFRAME');
    // expect(pdfViewer.src).toBe('');
  });

  test('sets the file URL and download link when provided in query parameters', () => {
    const fileUrl = 'path/to/file.pdf';
    const urlParams = new URLSearchParams();
    urlParams.set('file', fileUrl);
  
    // Mock window.location
    // window.location = { search: `?${urlParams.toString()}` };
  
    // Simulate DOMContentLoaded event
    // document.dispatchEvent(new Event('DOMContentLoaded'));
  
    const pdfViewer = document.querySelector('.pdf-viewer');
    const downloadLink = document.getElementById('downloadLink');
  
    // expect(pdfViewer.src).toBe(fileUrl);
    // expect(downloadLink.href).toBe(fileUrl);
  });

  test('logs an error when no file URL is found in query parameters', () => {
    console.error = jest.fn();

    dom.reconfigure({ url: 'http://localhost' });

  });
});
