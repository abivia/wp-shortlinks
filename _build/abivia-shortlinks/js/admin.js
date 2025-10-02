/**
 * Custom Link Shortener - Admin JavaScript
 * Version: 1.4.2
 */

// Redirect function for success messages
function abislRedirectToAnalytics() {
    setTimeout(function() {
        window.location.href = abislAdmin.analyticsUrl;
    }, 1500);
}

// CSV Export function for overview page
function abislCSVOverview() {
    var rows = [...document.querySelectorAll('.wp-list-table tbody tr')].map(tr => {
        var cells = [...tr.children];
        return [
            cells[0].querySelector('strong a') ? cells[0].querySelector('strong a').textContent.trim() : '',
            cells[1].textContent.trim(),
            cells[2].textContent.trim(),
            cells[3].textContent.trim()
        ];
    });

    if (rows.length === 0) {
        alert('No data to export');
        return;
    }

    var csv = 'Short URL,Total Clicks,Last Click,Type\n' + rows.map(r => r.join(',')).join('\n');
    var blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'analytics_overview_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// CSV Export function for detailed analytics
function abislCSVdetail() {
    var table = document.querySelector('.wp-list-table:not(.daily-stats)');
    if (!table) {
        alert('No detail data to export');
        return;
    }

    var rows = [...table.querySelectorAll('tbody tr')].map(tr =>
        [...tr.children].map(td => td.textContent.trim())
    );

    if (rows.length === 0) {
        alert('No data to export');
        return;
    }

    var csv = 'Date,IP,Country,City,User Agent\n' + rows.map(r => r.join(',')).join('\n');
    var blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = abislAdmin.csvFilename || ('analytics_detail_' + new Date().toISOString().split('T')[0] + '.csv');
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Alternative server-side CSV export
function abislServerCSVExport(type, linkId, date) {
    var params = new URLSearchParams({
        action: 'abisl_export_csv',
        nonce: abislAdmin.nonce,
        type: type
    });

    if (linkId) params.append('linkId', linkId);
    if (date) params.append('date', date);

    var filename = type === 'overview' ?
        'analytics_overview_' + new Date().toISOString().split('T')[0] + '.csv' :
        'analytics_detail_' + (date || new Date().toISOString().split('T')[0]) + '.csv';

    params.append('filename', filename);

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = abislAdmin.ajaxUrl;
    form.style.display = 'none';

    params.forEach((value, key) => {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Initialize admin functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('ABISL Admin JS loaded');

    // Add click handlers for export buttons
    var overviewExportBtn = document.querySelector('.abisl-export-overview');
    if (overviewExportBtn) {
        overviewExportBtn.addEventListener('click', function(e) {
            e.preventDefault();
            abislCSVOverview();
        });
    }

    var detailExportBtn = document.querySelector('.abisl-export-detail');
    if (detailExportBtn) {
        detailExportBtn.addEventListener('click', function(e) {
            e.preventDefault();
            abislCSVdetail();
        });
    }

    // Initialize clipboard functionality
    if (typeof ClipboardJS !== 'undefined') {
        new ClipboardJS('.abisl-copy').on('success', function(e) {
            var originalText = e.trigger.textContent;
            e.trigger.textContent = 'Copied!';
            setTimeout(function() {
                e.trigger.textContent = originalText;
            }, 2000);
            e.clearSelection();
        });
    }

    // Handle random post checkbox toggle
    var randomPostCheckbox = document.getElementById('abisl_random_post');
    var destinationsContainer = document.getElementById('abisl_destinations_container');
    var destinationsField = document.getElementById('abisl_destinations');
    var rotationCheckbox = document.getElementById('abisl_rotate');

    if (randomPostCheckbox && destinationsContainer && destinationsField) {
        function toggleDestinationsField() {
            if (randomPostCheckbox.checked) {
                destinationsContainer.style.display = 'none';
                destinationsField.removeAttribute('required');
                destinationsField.value = ''; // Clear the field
                if (rotationCheckbox) {
                    rotationCheckbox.checked = false;
                    rotationCheckbox.disabled = true;
                }
            } else {
                destinationsContainer.style.display = 'block';
                destinationsField.setAttribute('required', 'required');
                if (rotationCheckbox) {
                    rotationCheckbox.disabled = false;
                }
            }
        }

        randomPostCheckbox.addEventListener('change', toggleDestinationsField);
        // Run on page load to set initial state
        toggleDestinationsField();

        // Prevent form submission if destinations are required but empty
        var form = destinationsField.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!randomPostCheckbox.checked && !destinationsField.value.trim()) {
                    e.preventDefault();
                    alert('Please provide at least one destination URL or enable Random Post Redirect.');
                }
            });
        }
    }
});
