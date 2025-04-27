<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs | Epictetus Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles/home.css">
    <style>
        .log-content {
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 500px;
            overflow-y: auto;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .log-line {
            padding: 3px 0;
            border-bottom: 1px solid #eee;
        }
        .log-line:hover {
            background-color: #f1f1f1;
        }
        .error {
            color: #dc3545;
        }
        .warning {
            color: #ffc107;
        }
        .info {
            color: #0d6efd;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

    <?php include 'Partials/Header.php'; ?>

    <div class="container my-5">
        <div class="row mb-3">
            <div class="col">
                <h2><i class="fas fa-file-alt me-2"></i>System Logs</h2>
                <p class="text-muted">Review system logs for troubleshooting and monitoring.</p>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="list-group" id="log-tabs">
                    <a class="list-group-item list-group-item-action active" data-bs-toggle="list" href="#error-logs">
                        <i class="fas fa-exclamation-circle me-2"></i>Error Logs
                    </a>
                    <a class="list-group-item list-group-item-action" data-bs-toggle="list" href="#request-logs">
                        <i class="fas fa-exchange-alt me-2"></i>Request Logs
                    </a>
                    <a class="list-group-item list-group-item-action" data-bs-toggle="list" href="#system-info">
                        <i class="fas fa-info-circle me-2"></i>System Info
                    </a>
                </div>
                
                <div class="mt-4">
                    <button id="refreshBtn" class="btn btn-outline-primary w-100">
                        <i class="fas fa-sync-alt me-2"></i>Refresh Logs
                    </button>
                    <button id="downloadBtn" class="btn btn-outline-success w-100 mt-2">
                        <i class="fas fa-download me-2"></i>Download Logs
                    </button>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0" id="current-log-title">Error Logs</h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="autoRefresh">
                            <label class="form-check-label" for="autoRefresh">Auto-refresh</label>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="error-logs">
                                <div id="errorLogsContent" class="log-content">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-3">Loading error logs...</p>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="request-logs">
                                <div id="requestLogsContent" class="log-content">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-3">Loading request logs...</p>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="system-info">
                                <div id="systemInfoContent" class="log-content">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-3">Loading system info...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'Partials/Footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
            const refreshBtn = document.getElementById('refreshBtn');
            const downloadBtn = document.getElementById('downloadBtn');
            const autoRefreshToggle = document.getElementById('autoRefresh');
            let refreshInterval;

            // Initial load
            loadLogs();

            // Tab switching logic
            const logTabs = document.querySelectorAll('#log-tabs a');
            logTabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    document.getElementById('current-log-title').textContent = this.textContent.trim();
                });
            });

            // Refresh button
            refreshBtn.addEventListener('click', loadLogs);

            // Auto-refresh toggle
            autoRefreshToggle.addEventListener('change', function() {
                if (this.checked) {
                    refreshInterval = setInterval(loadLogs, 10000); // Refresh every 10 seconds
                } else {
                    clearInterval(refreshInterval);
                }
            });

            // Download button
            downloadBtn.addEventListener('click', function() {
                const activeTabId = document.querySelector('#log-tabs a.active').getAttribute('href').substring(1);
                let content = '';
                
                if (activeTabId === 'error-logs') {
                    content = document.getElementById('errorLogsContent').innerText;
                } else if (activeTabId === 'request-logs') {
                    content = document.getElementById('requestLogsContent').innerText;
                } else if (activeTabId === 'system-info') {
                    content = document.getElementById('systemInfoContent').innerText;
                }
                
                const blob = new Blob([content], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${activeTabId}.txt`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });

            function loadLogs() {
                axios.get('/api/v1/admin/logs', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                })
                .then(response => {
                    // First check if we have a valid response structure
                    if (!response.data || !response.data.data) {
                        throw new Error('Invalid response format from server');
                    }
                    
                    // Handle error logs
                    const errorLogsContainer = document.getElementById('errorLogsContent');
                    if (response.data.data.errors) {
                        if (typeof response.data.data.errors === 'string') {
                            errorLogsContainer.innerHTML = `<p class="text-danger">${response.data.data.errors}</p>`;
                        } else {
                            errorLogsContainer.innerHTML = '';
                            response.data.data.errors.forEach(log => {
                                const logLine = document.createElement('div');
                                logLine.className = 'log-line';
                                
                                // Add appropriate class based on log content
                                if (log.includes('ERROR') || log.includes('Fatal') || log.includes('Exception')) {
                                    logLine.classList.add('error');
                                } else if (log.includes('WARNING') || log.includes('Warning')) {
                                    logLine.classList.add('warning');
                                } else if (log.includes('INFO') || log.includes('Notice')) {
                                    logLine.classList.add('info');
                                }
                                
                                logLine.textContent = log;
                                errorLogsContainer.appendChild(logLine);
                            });
                        }
                    } else {
                        errorLogsContainer.innerHTML = '<p class="text-muted">No error logs available</p>';
                    }
                    
                    // Handle request logs
                    const requestLogsContainer = document.getElementById('requestLogsContent');
                    if (response.data.data.requests) {
                        if (typeof response.data.data.requests === 'string') {
                            requestLogsContainer.innerHTML = `<p class="text-danger">${response.data.data.requests}</p>`;
                        } else {
                            requestLogsContainer.innerHTML = '';
                            response.data.data.requests.forEach(log => {
                                const logLine = document.createElement('div');
                                logLine.className = 'log-line';
                                logLine.textContent = log;
                                requestLogsContainer.appendChild(logLine);
                            });
                        }
                    } else {
                        requestLogsContainer.innerHTML = '<p class="text-muted">No request logs available</p>';
                    }

                    // Generate system info
                    loadSystemInfo();
                })
                .catch(error => {
                    console.error('Error loading logs:', error);
                    const errorMessage = error.response?.data?.message || error.message || 'Unknown error';
                    
                    document.getElementById('errorLogsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading logs: ${errorMessage}
                        </div>
                    `;
                    document.getElementById('requestLogsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading logs: ${errorMessage}
                        </div>
                    `;
                });
            }

            function loadSystemInfo() {
                const systemInfoContainer = document.getElementById('systemInfoContent');
                
                // Just a placeholder - in a real implementation you might want to 
                // fetch this from a separate endpoint that provides system metrics
                const info = {
                    'Server Time': new Date().toLocaleString(),
                    'Browser': navigator.userAgent,
                    'Screen Resolution': `${window.screen.width}x${window.screen.height}`,
                    'Local Storage Available': !!localStorage,
                    'Cookies Enabled': navigator.cookieEnabled
                };
                
                systemInfoContainer.innerHTML = '';
                
                for (const [key, value] of Object.entries(info)) {
                    const infoLine = document.createElement('div');
                    infoLine.className = 'log-line';
                    infoLine.innerHTML = `<strong>${key}:</strong> ${value}`;
                    systemInfoContainer.appendChild(infoLine);
                }
            }
        });
    </script>
</body>
</html>