/**
 * AJAX Utility Functions for Dormitory Management System
 */

/**
 * Send an AJAX request
 * @param {string} url - The URL to send the request to
 * @param {string} method - The HTTP method (GET, POST, etc.)
 * @param {Object|FormData} data - The data to send with the request
 * @param {Function} successCallback - The function to call on success
 * @param {Function} errorCallback - The function to call on error
 */
function sendAjaxRequest(url, method, data, successCallback, errorCallback) {
    // Create a new XMLHttpRequest object
    const xhr = new XMLHttpRequest();
    
    // Configure the request
    xhr.open(method, url, true);
    
    // Set up event handlers
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 300) {
            // Success
            let response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                response = xhr.responseText;
            }
            successCallback(response, xhr.status);
        } else {
            // Error
            errorCallback(xhr.responseText, xhr.status);
        }
    };
    
    xhr.onerror = function() {
        errorCallback('Network error occurred', 0);
    };
    
    // Set appropriate headers and send the request
    if (data instanceof FormData) {
        // FormData is already properly formatted for file uploads
        xhr.send(data);
    } else {
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(objectToFormData(data));
    }
}

/**
 * Convert an object to URL-encoded form data
 * @param {Object} obj - The object to convert
 * @return {string} URL-encoded form data
 */
function objectToFormData(obj) {
    const params = new URLSearchParams();
    
    for (const key in obj) {
        if (obj.hasOwnProperty(key)) {
            params.append(key, obj[key]);
        }
    }
    
    return params.toString();
}

/**
 * Submit a form via AJAX
 * @param {HTMLElement} form - The form element to submit
 * @param {Function} successCallback - The function to call on success
 * @param {Function} errorCallback - The function to call on error
 */
function submitFormAjax(form, successCallback, errorCallback) {
    // Create a FormData object to handle the form data (including file uploads)
    const formData = new FormData(form);
    formData.append('ajax', '1'); // Add a flag to indicate this is an AJAX request
    
    // Send the AJAX request
    sendAjaxRequest(
        form.action || window.location.href,
        form.method || 'POST',
        formData,
        successCallback,
        errorCallback
    );
}

/**
 * Display a notification
 * @param {string} type - The type of notification ('success', 'error', 'warning', 'info')
 * @param {string} message - The message to display
 * @param {string} containerId - The ID of the container to add the notification to
 */
function showNotification(type, message, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    // Create alert div
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.setAttribute('role', 'alert');
    
    // Add icon based on type
    let icon = 'info-circle';
    if (type === 'success') icon = 'check-circle';
    if (type === 'error' || type === 'danger') icon = 'exclamation-triangle';
    if (type === 'warning') icon = 'exclamation-circle';
    
    // Set content
    alertDiv.innerHTML = `
        <i class="fas fa-${icon} me-2"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Add to container (at the beginning)
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        alertDiv.classList.remove('show');
        setTimeout(() => alertDiv.remove(), 150);
    }, 5000);
}
