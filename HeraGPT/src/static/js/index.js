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
            const response = await fetch("/upload", {
                method: "POST",
                body: formData
            });

            // Get the response JSON
            const responseData = await response.json();

            // Check if the response is ok
            if (responseData.result) {
                window.location.href = "/extract?pdf=" + encodeURIComponent(responseData.pdfUrl);
            } else {
                resultDiv.innerHTML = responseData.error;
            }
        } catch (error) {
            resultDiv.innerHTML = `<p>Error: ${error.message}</p>`;
        }
    });
});
