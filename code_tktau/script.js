// Global variables
let currentUser = null;
let userLockers = [];
let userDevices = [];
let currentStream = null;

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    loadUserData();
    loadLockers();
    loadDevices();
    loadDashboardStats();
    loadRecentActivity();
    
    // Register current device if not already registered
    registerCurrentDevice();
});

// Navigation
function showSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.sidebar-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected section and activate button
    document.getElementById(sectionId).classList.add('active');
    event.target.classList.add('active');
}

// Device Management
function showDeviceManager() {
    document.getElementById('deviceManager').classList.remove('hidden');
    loadDeviceManagerContent();
}

function closeDeviceManager() {
    document.getElementById('deviceManager').classList.add('hidden');
}

async function registerDevice() {
    const deviceName = document.getElementById('deviceName').value;
    const deviceType = document.getElementById('deviceType').value;
    
    if (!deviceName) {
        showNotification('Please enter a device name', 'error');
        return;
    }
    
    try {
        const response = await fetch('api/add_device.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                device_name: deviceName,
                device_type: deviceType
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Device registered successfully!', 'success');
            document.getElementById('deviceName').value = '';
            loadDevices();
            loadDeviceManagerContent();
        } else {
            showNotification(result.message, 'error');
        }
    } catch (error) {
        showNotification('Error registering device', 'error');
        console.error('Device registration error:', error);
    }
}

async function registerCurrentDevice() {
    // Check if current device is already registered
    const deviceId = getCurrentDeviceId();
    
    try {
        const response = await fetch('api/check_device.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                device_id: deviceId
            })
        });
        
        const result = await response.json();
        
        if (!result.registered) {
            // Auto-register current device
            await fetch('api/add_device.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    device_name: 'Auto-detected Device',
                    device_type: 'desktop',
                    auto_register: true
                })
            });
        }
    } catch (error) {
        console.error('Auto device registration error:', error);
    }
}

function getCurrentDeviceId() {
    let deviceId = localStorage.getItem('device_id');
    if (!deviceId) {
        deviceId = 'device_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('device_id', deviceId);
    }
    return deviceId;
}

async function loadDevices() {
    try {
        const response = await fetch('api/get_devices.php');
        const result = await response.json();
        
        if (result.success) {
            userDevices = result.devices;
            updateDevicesList();
            updateDeviceCount();
        }
    } catch (error) {
        console.error('Error loading devices:', error);
    }
}

function updateDevicesList() {
    const container = document.getElementById('devicesListContainer');
    container.innerHTML = '';
    
    userDevices.forEach(device => {
        const deviceElement = document.createElement('div');
        deviceElement.className = 'device-item';
        deviceElement.innerHTML = `
            <div>
                <strong>${device.device_name}</strong>
                <div class="device-info">
                    Type: ${device.device_type} | 
                    Last active: ${formatDate(device.last_login)}
                </div>
            </div>
            <button onclick="removeDevice('${device.device_id}')" class="btn-danger">
                Remove
            </button>
        `;
        container.appendChild(deviceElement);
    });
}

async function removeDevice(deviceId) {
    if (!confirm('Are you sure you want to remove this device?')) {
        return;
    }
    
    try {
        const response = await fetch('api/remove_device.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                device_id: deviceId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Device removed successfully', 'success');
            loadDevices();
        } else {
            showNotification(result.message, 'error');
        }
    } catch (error) {
        showNotification('Error removing device', 'error');
    }
}

// Locker Management
async function loadLockers() {
    try {
        const response = await fetch('api/get_lockers.php');
        const result = await response.json();
        
        if (result.success) {
            userLockers = result.lockers;
            updateLockerSelects();
            updateLockerList();
            updateLockerCount();
        }
    } catch (error) {
        console.error('Error loading lockers:', error);
    }
}

function updateLockerSelects() {
    const selects = [
        'qrLockerSelect',
        'manualLockerSelect', 
        'keyLockerSelect',
        'historyLockerFilter'
    ];
    
    selects.forEach(selectId => {
        const select = document.getElementById(selectId);
        select.innerHTML = '<option value="">Select Locker</option>';
        
        userLockers.forEach(locker => {
            const option = document.createElement('option');
            option.value = locker.id;
            option.textContent = `${locker.name} - ${locker.location}`;
            select.appendChild(option);
        });
    });
}

function updateLockerList() {
    const container = document.getElementById('lockerList');
    container.innerHTML = '';
    
    userLockers.forEach(locker => {
        const lockerElement = document.createElement('div');
        lockerElement.className = 'sidebar-btn';
        lockerElement.innerHTML = `
            <div>${locker.name}</div>
            <small>${locker.location}</small>
        `;
        lockerElement.onclick = () => selectLocker(locker.id);
        container.appendChild(lockerElement);
    });
}

// QR Code Generation
async function generateQRCode() {
    const lockerId = document.getElementById('qrLockerSelect').value;
    
    if (!lockerId) {
        showNotification('Please select a locker', 'error');
        return;
    }
    
    try {
        const response = await fetch('api/generate_qr.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                locker_id: lockerId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            const qrContainer = document.getElementById('qrCodeContainer');
            qrContainer.classList.remove('hidden');
            
            // Generate QR code
            const qrcode = new QRCode(document.getElementById("qrcode"), {
                text: result.qr_data,
                width: 200,
                height: 200,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        } else {
            showNotification(result.message, 'error');
        }
    } catch (error) {
        showNotification('Error generating QR code', 'error');
    }
}

function printQRCode() {
    const printWindow = window.open('', '_blank');
    const qrContent = document.getElementById('qrcode').innerHTML;
    
    printWindow.document.write(`
        <html>
            <head>
                <title>Print QR Code</title>
                <style>
                    body { text-align: center; padding: 2rem; }
                    .qr-code { margin: 1rem 0; }
                </style>
            </head>
            <body>
                <h2>Locker Access QR Code</h2>
                <div class="qr-code">${qrContent}</div>
                <p>Scan this code with the locker camera to unlock</p>
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Manual Key Access
async function unlockWithKey() {
    const lockerId = document.getElementById('manualLockerSelect').value;
    const key = document.getElementById('manualKey').value;
    
    if (!lockerId || !key) {
        showNotification('Please select locker and enter key', 'error');
        return;
    }
    
    try {
        const response = await fetch('api/check_access.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                locker_id: lockerId,
                key: key,
                method: 'manual'
            })
        });
        
        const result = await response.json();
        
        if (result.access_granted) {
            showNotification('Locker unlocked successfully!', 'success');
            document.getElementById('manualKey').value = '';
            loadRecentActivity();
        } else {
            showNotification('Invalid access key', 'error');
        }
    } catch (error) {
        showNotification('Error unlocking locker', 'error');
    }
}

// Camera Access
async function startCameraScan() {
    const container = document.getElementById('cameraContainer');
    container.classList.remove('hidden');
    
    try {
        currentStream = await navigator.mediaDevices.getUserMedia({ 
            video: { facingMode: "environment" } 
        });
        document.getElementById('video').srcObject = currentStream;
        
        // Simulate QR code detection for demo
        setTimeout(() => {
            simulateQRDetection();
        }, 3000);
        
    } catch (err) {
        showNotification('Camera access denied', 'error');
        console.error('Camera error:', err);
    }
}

function stopCamera() {
    if (currentStream) {
        currentStream.getTracks().forEach(track => track.stop());
    }
    document.getElementById('cameraContainer').classList.add('hidden');
}

function simulateQRDetection() {
    // This would be replaced with actual QR scanning logic
    showNotification('QR code detected! Locker unlocked.', 'success');
    loadRecentActivity();
}

// Key Management
async function addAccessKey() {
    const lockerId = document.getElementById('keyLockerSelect').value;
    const keyName = document.getElementById('newKeyName').value;
    const keyValue = document.getElementById('newKeyValue').value;
    
    if (!lockerId || !keyName || !keyValue) {
        showNotification('Please fill all fields', 'error');
        return;
    }
    
    try {
        const response = await fetch('api/add_key.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                locker_id: lockerId,
                key_name: keyName,
                key_value: keyValue
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Access key added successfully!', 'success');
            document.getElementById('newKeyName').value = '';
            document.getElementById('newKeyValue').value = '';
            loadKeysList();
        } else {
            showNotification(result.message, 'error');
        }
    } catch (error) {
        showNotification('Error adding access key', 'error');
    }
}

async function loadKeysList() {
    try {
        const response = await fetch('api/get_keys.php');
        const result = await response.json();
        
        if (result.success) {
            updateKeysList(result.keys);
        }
    } catch (error) {
        console.error('Error loading keys:', error);
    }
}

function updateKeysList(keys) {
    const container = document.getElementById('keysListContainer');
    container.innerHTML = '';
    
    keys.forEach(key => {
        const keyElement = document.createElement('div');
        keyElement.className = 'key-item';
        keyElement.innerHTML = `
            <div>
                <strong>${key.key_name}</strong>
                <div class="key-info">
                    Locker: ${key.locker_name} | 
                    Key: <code>${key.key_value}</code> |
                    Created: ${formatDate(key.created_at)}
                </div>
            </div>
            <button onclick="removeKey(${key.id})" class="btn-danger">
                Remove
            </button>
        `;
        container.appendChild(keyElement);
    });
}

// Dashboard Functions
async function loadDashboardStats() {
    // Update counts
    document.getElementById('lockerCount').textContent = userLockers.length;
    document.getElementById('deviceCount').textContent = userDevices.length;
    
    // Load today's access count
    try {
        const response = await fetch('api/get_today_access.php');
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('todayAccess').textContent = result.count;
        }
    } catch (error) {
        console.error('Error loading today access:', error);
    }
}

async function loadRecentActivity() {
    try {
        const response = await fetch('api/get_recent_activity.php');
        const result = await response.json();
        
        if (result.success) {
            updateRecentActivity(result.activity);
        }
    } catch (error) {
        console.error('Error loading recent activity:', error);
    }
}

function updateRecentActivity(activities) {
    const container = document.getElementById('recentActivity');
    container.innerHTML = '';
    
    activities.forEach(activity => {
        const activityElement = document.createElement('div');
        activityElement.className = 'activity-item';
        activityElement.innerHTML = `
            <div>
                <strong>${activity.locker_name}</strong>
                <div class="activity-info">
                    ${activity.access_method} | 
                    ${activity.device_name} | 
                    ${formatDate(activity.timestamp)}
                </div>
            </div>
            <span class="status-${activity.success ? 'success' : 'failed'}">
                ${activity.success ? '✅' : '❌'}
            </span>
        `;
        container.appendChild(activityElement);
    });
}

// Access History
async function loadAccessHistory() {
    const lockerId = document.getElementById('historyLockerFilter').value;
    const deviceId = document.getElementById('historyDeviceFilter').value;
    const dateFrom = document.getElementById('historyDateFrom').value;
    const dateTo = document.getElementById('historyDateTo').value;
    
    try {
        const response = await fetch('api/get_access_history.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                locker_id: lockerId,
                device_id: deviceId,
                date_from: dateFrom,
                date_to: dateTo
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            updateAccessHistory(result.history);
        }
    } catch (error) {
        console.error('Error loading access history:', error);
    }
}

function updateAccessHistory(history) {
    const container = document.getElementById('accessHistoryContainer');
    container.innerHTML = '';
    
    history.forEach(record => {
        const recordElement = document.createElement('div');
        recordElement.className = 'history-item';
        recordElement.innerHTML = `
            <div class="history-details">
                <strong>${record.locker_name}</strong>
                <div class="history-info">
                    Method: ${record.access_method} | 
                    Device: ${record.device_name} | 
                    Key: ${record.key_used} |
                    ${formatDate(record.timestamp)}
                </div>
            </div>
            <span class="status-${record.success ? 'success' : 'failed'}">
                ${record.success ? 'Success' : 'Failed'}
            </span>
        `;
        container.appendChild(recordElement);
    });
}

// Utility Functions
function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = `notification ${type}`;
    notification.classList.remove('hidden');
    
    setTimeout(() => {
        notification.classList.add('hidden');
    }, 5000);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString();
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php';
    }
}

// Update counts
function updateLockerCount() {
    document.getElementById('lockerCount').textContent = userLockers.length;
}

function updateDeviceCount() {
    document.getElementById('deviceCount').textContent = userDevices.length;
}

// Device Manager Content
async function loadDeviceManagerContent() {
    const container = document.getElementById('deviceManagerContent');
    
    let html = `
        <h3>Registered Devices</h3>
        <div class="devices-grid">
    `;
    
    userDevices.forEach(device => {
        html += `
            <div class="device-card">
                <h4>${device.device_name}</h4>
                <p>Type: ${device.device_type}</p>
                <p>Last Active: ${formatDate(device.last_login)}</p>
                <button onclick="removeDevice('${device.device_id}')" class="btn-danger">
                    Remove Device
                </button>
            </div>
        `;
    });
    
    html += `</div>`;
    container.innerHTML = html;
}