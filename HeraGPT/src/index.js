document.addEventListener("DOMContentLoaded", function() {
    const uploadForm = document.getElementById("pdfUploadForm");
    const fileInput = document.getElementById("pdfFile");
    const resultDiv = document.getElementById("result");

    uploadForm.addEventListener("submit", async function(event) {
        event.preventDefault();
        
        const formData = new FormData();
        const pdfFile = fileInput.files[0];
        formData.append("pdfFile", pdfFile);

        try {
            // Display loading message
            resultDiv.innerHTML = "<p>Uploading and processing, please wait...</p>";

            // Send the file to the server
            const response = await fetch("upload.php", {
                method: "POST",
                body: formData
            });

            // Get the response text
            const responseText = await response.text();

            // Log the response text to the console
            console.log(responseText);

            // Check if the response is ok
            if (!response.ok) {
                throw new Error(responseText);
            }

            // Parse the JSON response
            const resultData = JSON.parse(responseText);

            // If the response contains a success message, redirect to DataExtract.html
            if (resultData.result) {
                window.location.href = "DataExtract.html?pdf=" + encodeURIComponent(resultData.pdfUrl);
            } else {
                resultDiv.innerHTML = resultData.error;
            }
        } catch (error) {
            resultDiv.innerHTML = `<p>Error: ${error.message}</p>`;
        }
    });
});
