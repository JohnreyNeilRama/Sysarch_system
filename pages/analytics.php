<?php
session_start();

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    header("Location: /SYSARCH/login.php");
    exit;
}

// Include database connection
include '../includes/connect.php';

// Set timezone to Philippines (Asia/Manila)
date_default_timezone_set('Asia/Manila');

// Get basic stats for initial load if needed, but we'll fetch most via AJAX for "real-time" feel
$student_count = $conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0];
$sitin_count = $conn->query("SELECT COUNT(*) FROM sit_in")->fetch_row()[0];
$res_count = $conn->query("SELECT COUNT(*) FROM reservations")->fetch_row()[0];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Analytics - CCS Sit-in Monitoring System</title>
    <link rel="stylesheet" href="/SYSARCH/assets/css/admin_dashboard.css">
    <link rel="icon" type="image/png" href="../assets/images/uclogo.png">
    <style>
        .analytics-container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .analytics-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .analytics-header h1 {
            color: #1a3a5f;
            font-size: 28px;
            margin: 0;
        }

        .refresh-btn {
            background: #0f5bbe;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .refresh-btn:hover {
            background: #0d4fa1;
            transform: translateY(-2px);
        }

        .analytics-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-bottom: 40px;
        }

        .summary-item {
            background: white;
            padding: 30px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #eef2f7;
            transition: transform 0.3s ease;
        }

        .summary-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .summary-label {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .summary-value {
            font-size: 36px;
            font-weight: 800;
            color: #0f5bbe;
        }

        .analytics-charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }

        .ana-chart-card {
            background: white;
            border: 1px solid #eef2f7;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .ana-chart-card h3 {
            font-size: 18px;
            color: #1e293b;
            margin: 0 0 20px 0;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ana-chart-container {
            height: 300px;
            position: relative;
        }

        .top-students-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .top-student-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: #f8fafc;
            border-radius: 12px;
            border-left: 5px solid #0f5bbe;
            transition: all 0.2s ease;
        }

        .top-student-item:hover {
            background: #f1f5f9;
            transform: translateX(5px);
        }

        .student-info {
            font-weight: 600;
            color: #334155;
            font-size: 15px;
        }

        .student-count {
            background: #0f5bbe;
            color: #fff;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
    </style>
</head>

<body class="admin-dashboard-page">

<!-- Dashboard Navigation -->
<nav class="dashboard-navbar">
    <div class="dashboard-left">
        <img class="admin-logo" src="/SYSARCH/assets/images/uclogo.png" alt="UC Logo">
        <span class="admin-title">Admin Dashboard</span>
    </div>
    <ul class="dashboard-right">    
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="analytics.php" class="active">Analytics</a></li>
        <li><a href="manage_students.php">Manage Students</a></li>
        <li><a href="manage_sitin.php">Sit-in Logs</a></li>
        <li><a href="manage_reservations.php">Reservations</a></li>
        <li><a href="feedback_reports.php">Feedback Reports</a></li>
        <li><a href="#">Settings</a></li>
        <li><a href="/SYSARCH/logout.php" class="logout-btn">Log Out</a></li>
    </ul>
</nav>

<div class="analytics-container">
    <div class="analytics-header">
        <h1>📊 System Analytics</h1>
        <button class="refresh-btn" onclick="fetchAnalytics()">
            <span>🔄</span> Refresh Data
        </button>
    </div>

    <!-- Analytics Summary Cards -->
    <div class="analytics-summary-grid">
        <div class="summary-item">
            <div class="summary-label">Total Students</div>
            <div class="summary-value" id="ana-total-students"><?php echo $student_count; ?></div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Total Sit-ins</div>
            <div class="summary-value" id="ana-total-sitin"><?php echo $sitin_count; ?></div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Total Reservations</div>
            <div class="summary-value" id="ana-total-res"><?php echo $res_count; ?></div>
        </div>
    </div>

    <div class="analytics-charts-grid">
        <!-- Activity Trend -->
        <div class="ana-chart-card">
            <h3>📈 Weekly Activity Trend</h3>
            <div class="ana-chart-container">
                <canvas id="activityTrendChart"></canvas>
            </div>
        </div>

        <!-- Peak Hours -->
        <div class="ana-chart-card">
            <h3>🕒 Peak Usage Hours</h3>
            <div class="ana-chart-container">
                <canvas id="peakHoursChart"></canvas>
            </div>
        </div>

        <!-- Lab Distribution -->
        <div class="ana-chart-card">
            <h3>🏢 Lab Usage Distribution</h3>
            <div class="ana-chart-container">
                <canvas id="labUsageChart"></canvas>
            </div>
        </div>

        <!-- Top Students -->
        <div class="ana-chart-card">
            <h3>🏆 Most Active Students</h3>
            <div class="top-students-list" id="topStudentsList">
                <!-- Populated by JS -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let analyticsCharts = {};

async function fetchAnalytics() {
    try {
        const response = await fetch('/SYSARCH/pages/api/get_analytics_data.php');
        const data = await response.json();
        
        if (data.error) return;

        // Update counts
        document.getElementById('ana-total-students').textContent = data.counts.students;
        document.getElementById('ana-total-sitin').textContent = data.counts.sitin;
        document.getElementById('ana-total-res').textContent = data.counts.reservations;

        // Update Charts
        updateActivityChart(data.activity_trends);
        updatePeakHoursChart(data.peak_hours);
        updateLabUsageChart(data.lab_usage);
        updateTopStudentsList(data.top_students);

    } catch (error) {
        console.error('Error fetching analytics:', error);
    }
}

function updateActivityChart(trendData) {
    const ctx = document.getElementById('activityTrendChart').getContext('2d');
    const labels = trendData.map(d => d.date);
    const counts = trendData.map(d => d.count);

    if (analyticsCharts.activity) analyticsCharts.activity.destroy();

    analyticsCharts.activity = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Sit-ins',
                data: counts,
                borderColor: '#0f5bbe',
                backgroundColor: 'rgba(15, 91, 190, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 6,
                pointHoverRadius: 8,
                pointBackgroundColor: '#0f5bbe',
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(26, 58, 95, 0.9)',
                    padding: 12,
                    titleFont: { size: 14 },
                    bodyFont: { size: 13 }
                }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                x: { grid: { display: false } }
            }
        }
    });
}

function updatePeakHoursChart(peakData) {
    const ctx = document.getElementById('peakHoursChart').getContext('2d');
    const labels = Array.from({length: 24}, (_, i) => {
        let h = i % 12 || 12;
        let ampm = i < 12 ? 'AM' : 'PM';
        return `${h}${ampm}`;
    });
    
    if (analyticsCharts.peak) analyticsCharts.peak.destroy();

    analyticsCharts.peak = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Usage Sessions',
                data: peakData,
                backgroundColor: 'rgba(25, 118, 210, 0.7)',
                hoverBackgroundColor: '#0f5bbe',
                borderRadius: 6,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                x: { grid: { display: false }, ticks: { font: { size: 9 } } }
            }
        }
    });
}

function updateLabUsageChart(labData) {
    const ctx = document.getElementById('labUsageChart').getContext('2d');
    const labels = labData.map(d => 'Lab ' + d.lab);
    const counts = labData.map(d => d.count);
    
    if (analyticsCharts.lab) analyticsCharts.lab.destroy();

    analyticsCharts.lab = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: [
                    '#0f5bbe', '#1976D2', '#4caf50', '#ff9800', '#f44336', '#9c27b0', '#00bcd4'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    position: 'right',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: { size: 12 }
                    }
                }
            },
            cutout: '65%'
        }
    });
}

function updateTopStudentsList(students) {
    const list = document.getElementById('topStudentsList');
    list.innerHTML = '';
    
    if (students.length === 0) {
        list.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No activity recorded yet.</p>';
        return;
    }

    students.forEach(s => {
        const item = document.createElement('div');
        item.className = 'top-student-item';
        item.innerHTML = `
            <span class="student-info">${s.student_name}</span>
            <span class="student-count">${s.count} sessions</span>
        `;
        list.appendChild(item);
    });
}

// Initial load
document.addEventListener('DOMContentLoaded', fetchAnalytics);
</script>

</body>
</html>
