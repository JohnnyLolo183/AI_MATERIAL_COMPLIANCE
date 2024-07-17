document.addEventListener("DOMContentLoaded", function() {
    const uploadForm = document.getElementById("pdfUploadForm");
    const fileInput = document.getElementById("pdfFile");
    const resultDiv = document.geElementById("result");

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

            // Check if the response is ok
            if (!response.ok) {
                throw new Error("Network response was not ok");
            }

            // Get the result
            const result = await response.text();
            resultDiv.innerHTML = result;
        } catch (error) {
            resultDiv.innerHTML = `<p>Error: ${error.message}</p>`;
        }
    });
});
