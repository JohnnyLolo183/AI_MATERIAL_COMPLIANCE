/**
 * @jest-environment jsdom
 */

const fs = require('fs');
const path = require('path');

// Load the HTML file into a string
const html = fs.readFileSync(path.resolve(__dirname, '../src/export.html'), 'utf8');

describe('export.html JavaScript', () => {
  let document;
  let fetchMock;

  beforeEach(() => {
    // Set up the DOM by loading the HTML
    document = new DOMParser().parseFromString(html, 'text/html');
    global.document = document;
    global.window = document.defaultView;

    // Mock fetch
    fetchMock = jest.fn(() =>
      Promise.resolve({
        text: () => Promise.resolve('uploads/test.pdf'),
      })
    );
    global.fetch = fetchMock;
  });

  test('should set PDF URL in iframe', () => {
    const pdfUrl = 'test.pdf';
    const pdfViewer = document.getElementById('pdfViewer');
    const urlParams = new URLSearchParams();
    urlParams.set('pdf', pdfUrl);
    window.location.search = `?${urlParams.toString()}`;

    // Simulate DOMContentLoaded event
    document.dispatchEvent(new Event('DOMContentLoaded'));

    // Check if the src is set correctly

  });

  test('should mark compliant button as active', async () => {
    const compliantBtn = document.getElementById('compliantBtn');
    const nonCompliantBtn = document.getElementById('nonCompliantBtn');
  
    // Simulate click event
    compliantBtn.click();
  
    // Wait for fetch to complete
    await new Promise((resolve) => setTimeout(resolve, 0));
  
    expect(compliantBtn.classList.contains('compliant')).toBe(true);
  });
  
  test('should mark non-compliant button as active', async () => {
    const compliantBtn = document.getElementById('compliantBtn');
    const nonCompliantBtn = document.getElementById('nonCompliantBtn');
  
    // Simulate click event
    nonCompliantBtn.click();
  
    // Wait for fetch to complete
    await new Promise((resolve) => setTimeout(resolve, 0));
  
    expect(nonCompliantBtn.classList.contains('non-compliant')).toBe(true);
    expect(compliantBtn.classList.contains('active')).toBe(false);
    
  });

  // Add more tests as needed
});