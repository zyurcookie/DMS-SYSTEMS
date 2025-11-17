// Auto-clear alerts after 5 seconds
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.remove();
    });
}, 5000);

// Close modal after submitting form
document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('form');
    form.addEventListener('submit', function() {
        var modal = bootstrap.Modal.getInstance(document.getElementById('createAccountModal'));
        modal.hide();
    });
});

// Function to open the modal and display document details
function showDocumentDetails(doc_id) {
    // Get the modal element and content container
    var modal = document.getElementById('documentModal');
    var documentDetails = document.getElementById('documentDetails');

    // Make the modal visible
    modal.style.display = 'block';

    // Find the document from the page using the doc_id
    var document = document.querySelector(`[data-doc-id="${doc_id}"]`);
    
    if (document) {
        // Get document data from the page (using the document's data attributes)
        var docName = document.querySelector('.doc-name').innerText;
        var docDesc = document.querySelector('.doc-desc').innerText;
        var docStatus = document.querySelector('.doc-status').innerText;

        // Populate the modal with the document details
        documentDetails.innerHTML = `
            <div><strong>Document Name:</strong> ${docName}</div>
            <div><strong>Description:</strong> ${docDesc}</div>
            <div><strong>Status:</strong> ${docStatus}</div>
        `;
    }
}

// Close the modal when the user clicks the close button
document.querySelector('.close-btn').onclick = function() {
    document.getElementById('documentModal').style.display = 'none';
}

// Close the modal if the user clicks outside of it
window.onclick = function(event) {
    var modal = document.getElementById('documentModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
