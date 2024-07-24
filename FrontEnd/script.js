// Include the PDF.js library
const pdfjsLib = window['pdfjs-dist/build/pdf'];

// Specify the workerSrc property
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://mozilla.github.io/pdf.js/build/pdf.worker.js';

async function extractPdfData() {
    const url = '/FrontEnd/images/030065 (1).pdf';
    const loadingTask = pdfjsLib.getDocument(url);
    
    const pdf = await loadingTask.promise;
    const numPages = pdf.numPages;
    let textContent = '';
    
    for (let pageNum = 1; pageNum <= numPages; pageNum++) {
        const page = await pdf.getPage(pageNum);
        const text = await page.getTextContent();
        textContent += text.items.map(item => item.str).join(' ') + ' ';
    }

    parsePdfText(textContent);
}

function parsePdfText(text) {
    const chemicalAnalysisSection = document.getElementById('chemical-analysis-table').getElementsByTagName('tbody')[0];
    
    // Dummy logic to find chemical elements in the text
    const elements = ['Carbon', 'Manganese', 'Chromium', 'Molybdenum', 'Vanadium', 'Nickel', 'Copper'];
    const elementPercentages = {'Carbon': '0.21', 'Manganese': '0.82', 'Chromium': '0.14', 'Molybdenum': '0.03', 'Vanadium': '0.003', 'Nickel': '0.08', 'Copper': '0.02'};
    
    elements.forEach(element => {
        const row = document.createElement('tr');
        
        const cell1 = document.createElement('td');
        cell1.textContent = element;
        row.appendChild(cell1);
        
        const cell2 = document.createElement('td');
        const input = document.createElement('input');
        input.type = 'text';
        input.value = elementPercentages[element] || '';  // Pre-fill value if available
        cell2.appendChild(input);
        row.appendChild(cell2);
        
        chemicalAnalysisSection.appendChild(row);
    });
}
