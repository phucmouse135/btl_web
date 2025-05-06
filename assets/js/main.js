/**
 * Main JavaScript file for Dormitory Management System
 */

// Document Ready Event
document.addEventListener('DOMContentLoaded', function() {
    // Tooltips initialization
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Confirm Delete Action
    const deleteButtons = document.querySelectorAll('.btn-delete');
    if (deleteButtons) {
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const deleteUrl = this.getAttribute('href');
                const itemName = this.getAttribute('data-item-name') || 'this item';
                
                Swal.fire({
                    title: 'Are you sure?',
                    text: `You are about to delete ${itemName}. This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e74a3b',
                    cancelButtonColor: '#858796',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = deleteUrl;
                    }
                });
            });
        });
    }
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    
    if (forms.length) {
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
        });
    }
    
    // Status Filters
    const statusFilters = document.querySelectorAll('.status-filter');
    if (statusFilters.length) {
        statusFilters.forEach(filter => {
            filter.addEventListener('click', function() {
                const status = this.getAttribute('data-status');
                const tableRows = document.querySelectorAll('.datatable tbody tr');
                
                tableRows.forEach(row => {
                    const rowStatus = row.getAttribute('data-status');
                    
                    if (status === 'all' || rowStatus === status) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Update active filter
                statusFilters.forEach(f => f.classList.remove('active'));
                this.classList.add('active');
            });
        });
    }
    
    // Image Preview for File Inputs
    const imageInputs = document.querySelectorAll('.image-input');
    if (imageInputs.length) {
        imageInputs.forEach(input => {
            input.addEventListener('change', function() {
                const previewId = this.getAttribute('data-preview');
                const preview = document.getElementById(previewId);
                
                if (preview && this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
    }
    
    // Toggle Password Visibility
    const togglePasswordButtons = document.querySelectorAll('.toggle-password');
    if (togglePasswordButtons.length) {
        togglePasswordButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                
                if (passwordInput) {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Change icon
                    this.innerHTML = type === 'password' ? 
                        '<i class="fas fa-eye"></i>' : 
                        '<i class="fas fa-eye-slash"></i>';
                }
            });
        });
    }
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible.auto-close');
    if (alerts.length) {
        alerts.forEach(alert => {
            setTimeout(() => {
                const closeButton = alert.querySelector('.btn-close');
                if (closeButton) {
                    closeButton.click();
                }
            }, 5000);
        });
    }
    
    // Room Selection
    const roomSelects = document.querySelectorAll('.room-select');
    if (roomSelects.length) {
        roomSelects.forEach(select => {
            select.addEventListener('change', function() {
                const buildingId = this.value;
                const roomSelect = document.getElementById(this.getAttribute('data-room-select'));
                
                if (buildingId && roomSelect) {
                    // Fetch available rooms for the selected building
                    fetch(`/LTW/api/get_rooms.php?building_id=${buildingId}`)
                        .then(response => response.json())
                        .then(data => {
                            roomSelect.innerHTML = '<option value="">Select Room</option>';
                            
                            if (data.length > 0) {
                                data.forEach(room => {
                                    const option = document.createElement('option');
                                    option.value = room.id;
                                    option.textContent = `${room.room_number} (${room.current_occupancy}/${room.capacity})`;
                                    
                                    if (room.current_occupancy >= room.capacity) {
                                        option.disabled = true;
                                    }
                                    
                                    roomSelect.appendChild(option);
                                });
                                
                                roomSelect.disabled = false;
                            } else {
                                roomSelect.disabled = true;
                            }
                        })
                        .catch(error => console.error('Error fetching rooms:', error));
                } else {
                    roomSelect.innerHTML = '<option value="">Select Room</option>';
                    roomSelect.disabled = true;
                }
            });
        });
    }
    
    // Dynamic form fields (add/remove)
    const addFieldButtons = document.querySelectorAll('.add-field');
    if (addFieldButtons.length) {
        addFieldButtons.forEach(button => {
            button.addEventListener('click', function() {
                const container = document.getElementById(this.getAttribute('data-container'));
                const template = document.getElementById(this.getAttribute('data-template'));
                
                if (container && template) {
                    const clone = template.content.cloneNode(true);
                    const fieldIndex = container.children.length;
                    
                    // Update IDs and names with the new index
                    const elements = clone.querySelectorAll('[id], [name]');
                    elements.forEach(el => {
                        if (el.hasAttribute('id')) {
                            el.id = el.id.replace('__INDEX__', fieldIndex);
                        }
                        if (el.hasAttribute('name')) {
                            el.name = el.name.replace('__INDEX__', fieldIndex);
                        }
                    });
                    
                    // Add remove button functionality
                    const removeButton = clone.querySelector('.remove-field');
                    if (removeButton) {
                        removeButton.addEventListener('click', function() {
                            const fieldGroup = this.closest('.field-group');
                            if (fieldGroup) {
                                fieldGroup.remove();
                            }
                        });
                    }
                    
                    container.appendChild(clone);
                }
            });
        });
    }
    
    // Date range picker initialization
    const dateRangePickers = document.querySelectorAll('.date-range-picker');
    if (dateRangePickers.length && typeof daterangepicker !== 'undefined') {
        dateRangePickers.forEach(picker => {
            $(picker).daterangepicker({
                opens: 'left',
                autoApply: true,
                locale: {
                    format: 'YYYY-MM-DD'
                }
            });
        });
    }
    
    // Single date picker initialization
    const datePickers = document.querySelectorAll('.date-picker');
    if (datePickers.length && typeof daterangepicker !== 'undefined') {
        datePickers.forEach(picker => {
            $(picker).daterangepicker({
                singleDatePicker: true,
                showDropdowns: true,
                minYear: 1901,
                maxYear: parseInt(moment().format('YYYY'), 10) + 10,
                locale: {
                    format: 'YYYY-MM-DD'
                }
            });
        });
    }
    
    // File input name display
    const fileInputs = document.querySelectorAll('.custom-file-input');
    if (fileInputs.length) {
        fileInputs.forEach(input => {
            input.addEventListener('change', function() {
                const label = this.nextElementSibling;
                if (label && this.files && this.files[0]) {
                    label.textContent = this.files[0].name;
                }
            });
        });
    }
    
    // Table search functionality
    const tableSearchInputs = document.querySelectorAll('.table-search-input');
    if (tableSearchInputs.length) {
        tableSearchInputs.forEach(input => {
            input.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const tableId = this.getAttribute('data-table');
                const table = document.getElementById(tableId);
                
                if (table) {
                    const rows = table.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    // Update "no results" message
                    const noResults = document.getElementById(`${tableId}-no-results`);
                    if (noResults) {
                        const visibleRows = table.querySelectorAll('tbody tr[style=""]').length;
                        noResults.style.display = visibleRows === 0 ? 'block' : 'none';
                    }
                }
            });
        });
    }
    
    // Export table data to CSV
    const exportButtons = document.querySelectorAll('.export-csv');
    if (exportButtons.length) {
        exportButtons.forEach(button => {
            button.addEventListener('click', function() {
                const tableId = this.getAttribute('data-table');
                const fileName = this.getAttribute('data-filename') || 'exported-data';
                const table = document.getElementById(tableId);
                
                if (table) {
                    exportTableToCSV(table, `${fileName}.csv`);
                }
            });
        });
    }
    
    // Print table data
    const printButtons = document.querySelectorAll('.print-table');
    if (printButtons.length) {
        printButtons.forEach(button => {
            button.addEventListener('click', function() {
                const tableId = this.getAttribute('data-table');
                const title = this.getAttribute('data-title') || 'Printed Data';
                const table = document.getElementById(tableId);
                
                if (table) {
                    printTable(table, title);
                }
            });
        });
    }
});

/**
 * Display a notification using SweetAlert2
 * @param {string} type - 'success', 'error', 'warning', 'info'
 * @param {string} title - Notification title
 * @param {string} message - Notification message
 */
function showNotification(type, title, message) {
    Swal.fire({
        icon: type,
        title: title,
        text: message,
        timer: 3000,
        timerProgressBar: true
    });
}

/**
 * Format currency
 * @param {number} amount - Amount to format
 * @param {string} currency - Currency code (default: VND)
 * @returns {string} Formatted currency string
 */
function formatCurrency(amount, currency = 'VND') {
    return new Intl.NumberFormat('vi-VN', { 
        style: 'currency', 
        currency: currency
    }).format(amount);
}

/**
 * Format date
 * @param {string} dateString - Date string to format
 * @param {string} format - Output format ('short', 'long', 'full')
 * @returns {string} Formatted date string
 */
function formatDate(dateString, format = 'short') {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    
    switch (format) {
        case 'long':
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        case 'full':
            return date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        case 'short':
        default:
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
    }
}

/**
 * Export table data to CSV file
 * @param {HTMLTableElement} table - Table element to export
 * @param {string} filename - Name of the exported file
 */
function exportTableToCSV(table, filename) {
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Skip columns with 'no-export' class
            if (!cols[j].classList.contains('no-export')) {
                // Replace commas with spaces to avoid CSV formatting issues
                let data = cols[j].innerText.replace(/,/g, ' ');
                // Wrap in quotes if contains line breaks
                if (data.includes('\n')) {
                    data = `"${data}"`;
                }
                row.push(data);
            }
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV file
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, filename);
    } else {
        link.href = URL.createObjectURL(blob);
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

/**
 * Print table data
 * @param {HTMLTableElement} table - Table element to print
 * @param {string} title - Title of the printed page
 */
function printTable(table, title) {
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
        <head>
            <title>${title}</title>
            <style>
                body { font-family: Arial, sans-serif; }
                table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                h1 { text-align: center; }
                .print-date { text-align: right; margin-bottom: 20px; }
                .no-print { display: none; }
            </style>
        </head>
        <body>
            <h1>${title}</h1>
            <div class="print-date">Printed on: ${new Date().toLocaleString()}</div>
            ${table.outerHTML.replace(/<([^>]+) class="no-print"[^>]*>.*?<\/\1>/g, '')}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    // Add a slight delay to ensure content is loaded
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}

/**
 * Generate a random password
 * @param {number} length - Length of the password (default: 10)
 * @param {boolean} includeSpecial - Whether to include special characters
 * @returns {string} Generated password
 */
function generateRandomPassword(length = 10, includeSpecial = true) {
    const lowercase = 'abcdefghijklmnopqrstuvwxyz';
    const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const numbers = '0123456789';
    const special = includeSpecial ? '!@#$%^&*()_+-=[]{}|;:,.<>?' : '';
    
    const allChars = lowercase + uppercase + numbers + special;
    let password = '';
    
    // Ensure at least one character from each required set
    password += lowercase.charAt(Math.floor(Math.random() * lowercase.length));
    password += uppercase.charAt(Math.floor(Math.random() * uppercase.length));
    password += numbers.charAt(Math.floor(Math.random() * numbers.length));
    
    if (includeSpecial) {
        password += special.charAt(Math.floor(Math.random() * special.length));
    }
    
    // Fill the rest of the password
    for (let i = password.length; i < length; i++) {
        password += allChars.charAt(Math.floor(Math.random() * allChars.length));
    }
    
    // Shuffle the password characters
    return password.split('').sort(() => 0.5 - Math.random()).join('');
}