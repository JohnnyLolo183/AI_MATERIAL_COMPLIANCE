AI-Driven Material Compliance Assistant

The AI-Driven Material Compliance Assistant is designed to automate the compliance verification of material certificates by comparing uploaded PDFs against industry standards. Integrating AI capabilities, it enables users to assess compliance, add digital signatures, and download processed certificates with verified status. This solution is ideal for organizations looking to streamline document verification and maintain high compliance standards efficiently.

Key Features

Certificate Upload and Display: Upload and view PDF certificates for compliance assessment.
Automated Compliance Check: Leverages AI to determine certificate compliance.
Digital Signature Functionality: Allows users to add signatures and verified stamps.
Chatbot Assistance: Engage with an AI chatbot for compliance guidance and insights.
Export and Download: Export stamped certificates with added compliance markers and download them.

Project Structure

HTML Interfaces:
index.html: Main page for uploading certificates.
extract.html: Page to start data extraction and view results.
download.html: Download page for finalized certificates.
export.html: Interface to review and export compliance results.

Backend:
processExtraction.php: Handles server-side processing of uploaded certificates.

Styling:
styles.css: Core styles applied across the application.

Configuration and Dependencies:

composer.json: PHP dependencies (FPDF, TCPDF, PhpSpreadsheet).
package.json: Node dependencies (@google/generative-ai, signature_pad).

Installation

Prerequisites

Ensure Node.js (>=18.0.0) and PHP are installed on your system.
Recommended to use a local Apache server or another PHP-compatible server to run the PHP backend files.

Clone the repository:

bash

git clone <repository-url>
cd <project-directory>
Install Node.js dependencies:

bash

npm install
Install PHP dependencies using Composer:

bash

composer install

Dependencies Overview

Node.js Packages (installed via npm install):

- @google/generative-ai (^0.17.0): For chatbot functionality.
- signature_pad (^5.0.2): Allows digital signatures on certificates.

PHP Packages (installed via composer install):

- setasign/fpdi (^2.6): Enables PDF manipulation for compliance marking.
- fpdf/fpdf (^1.86): Core PDF handling library.
- setasign/fpdf (^1.8): Additional FPDF handling support.
- phpoffice/phpspreadsheet (^2.2): Enables spreadsheet compatibility.
- tecnickcom/tcpdf (^6.7): Assists with PDF generation for exports.

Usage Instructions

Start your local server, and open index.html in a web browser.
Upload a PDF certificate using the upload form.

Click Begin Data Extraction to analyze the uploaded certificate for compliance.
Use the chatbot on export.html for further compliance insights.

Mark the certificate as Compliant or Non-Compliant, add a digital signature if needed, and export the document.
Download the processed file from download.html.

Project Demo

Visit the demo link (if hosted) or follow the installation instructions to run it locally.

Contributing

Contributions are welcome! If you would like to improve or add to the project, please fork the repository and submit a pull request. Ensure any new features align with the project structure and maintain compatibility with the existing functionality.

License

This project is licensed under proprietary terms.
