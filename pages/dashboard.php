<?php
// Assume necessary manager classes are already instantiated in the main controller context

// 1. Retrieve Key Performance Indicators (KPIs)
$pendingAccounts = $reqObj->getPendingAccounts();
$pendingLeave = $applicationManager->getApplicationsByStatus('Pending');
$totalPending = count($pendingAccounts);
$totalLeaveList = count($pendingLeave);
$deptCounts = $employeeManager->getEmployeeCountByDepartment();

// 2. Retrieve and Structure Dashboard Analytics Data
$dashboardData = getDashboardData($employeeManager, $applicationManager); 

// Prepare department distribution data for visual representation
$numericCounts = array_map('intval', $deptCounts);
$totalEmployees = array_sum($numericCounts);

// Serialize chart data for JavaScript consumption
$statusData = json_encode(array_values($dashboardData['statusData']));
$statusLabels = json_encode(array_keys($dashboardData['statusData']));

$monthlyData = json_encode($dashboardData['monthlyData']['data']);
$monthlyLabels = json_encode($dashboardData['monthlyData']['months']);

// Prepare summary comparison data
$summaryLabels = json_encode(['Total Employees', 'On Approved Leave', 'Vacation Leave Users', 'Sick Leave Users']);
$summaryData = json_encode([
    $dashboardData['leaveSummary']['totalEmployees'],
    $dashboardData['leaveSummary']['onLeave'],
    $dashboardData['leaveSummary']['onLeaveVacation'],
    $dashboardData['leaveSummary']['onLeaveSick']
]);

// Define color palette for UI consistency
$primaryRed = '#dc2626'; 
$darkRed = '#b91c1c';   
$lightRed = '#f87171';  
$paleRed = '#fca5a5';   
$deptColors = ['#b91c1c', '#dc2626', '#f87171', '#fca5a5', '#e9d5ff', '#a78bfa']; 
?>

<div class="dashboard-cards">
    <div class="card">
        <h3>Pending Account Requests</h3>
        <p><?= $totalPending ?></p>
    </div>

    <div class="card">
        <h3>Pending Leave Requests</h3>
        <p><?= $totalLeaveList ?></p>
    </div>

    <div class="card" id="datetime-card" style="display: flex; flex-direction: column; justify-content: space-between;">
        <div style="margin-bottom: 25px;">
            <h3 style="color: var(--text-muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;">
                College Distribution
            </h3>
            
            <div style="display: flex; align-items: baseline; gap: 8px;">
                <span style="font-size: 2.5rem; font-weight: 800; color: var(--text-main); line-height: 1;">
                    <?= $totalEmployees ?>
                </span>
                <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">
                    Total Employees
                </span>
            </div>
        </div>

        <div style="width: 100%; height: 10px; background: #f3f4f6; border-radius: 5px; overflow: hidden; display: flex; margin-bottom: 25px; cursor: help;">
            <?php 
            $visibleSegments = 0;
            foreach ($deptCounts as $count) { if((int)$count > 0) $visibleSegments++; }
            $drawnSegments = 0;
            $colorIndex = 0; 

            // Render segmented bar representing department distribution
            foreach ($deptCounts as $deptName => $count): 
                $count = (int)$count;
                if ($count > 0 && $totalEmployees > 0):
                    $width = ($count / $totalEmployees) * 100;
                    $color = $deptColors[$colorIndex % count($deptColors)]; 
                    $drawnSegments++;
                    $borderStyle = ($drawnSegments < $visibleSegments) ? 'border-right: 2px solid #ffffff;' : '';
                    $dataAttributes = 'data-title="' . htmlspecialchars($deptName) . '" data-count="' . $count . '" data-color="' . $color . '"';
            ?>
                <div class="dept-segment" style="width: <?= $width ?>%; background-color: <?= $color ?>; <?= $borderStyle ?>" 
                    <?= $dataAttributes ?>></div>
                <?php 
                endif;
                $colorIndex++;
            endforeach; ?>
        </div>

        <div id="dept-tooltip" style="display: none; position: fixed; z-index: 10001; background-color: rgba(30, 30, 30, 0.95); color: #fff; padding: 8px 12px; border-radius: 6px; font-size: 0.85rem; pointer-events: none; box-shadow: 0 4px 12px rgba(0,0,0,0.2); transform: translate(-50%, -115%); white-space: nowrap; font-family: 'Inter', sans-serif;">
            <div id="tooltip-title" style="font-weight: 700; margin-bottom: 4px; font-size: 0.8rem; opacity: 0.9;"></div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <span id="tooltip-color" style="display: block; width: 10px; height: 10px; border: 1px solid #fff;"></span>
                <span id="tooltip-count" style="font-weight: 600; font-size: 0.95rem;"></span>
            </div>
            <div style="position: absolute; top: 100%; left: 50%; margin-left: -6px; border-width: 6px; border-style: solid; border-color: rgba(30, 30, 30, 0.95) transparent transparent transparent;"></div>
        </div>

        <script>
            // Initialize interactive tooltips for department segments
            document.addEventListener('DOMContentLoaded', function() {
                const segments = document.querySelectorAll('.dept-segment');
                const tooltip = document.getElementById('dept-tooltip');
                const tooltipTitle = document.getElementById('tooltip-title');
                const tooltipCount = document.getElementById('tooltip-count');
                const tooltipColor = document.getElementById('tooltip-color');

                if(tooltip) document.body.appendChild(tooltip); 

                segments.forEach(segment => {
                    segment.addEventListener('mouseover', function(e) {
                        tooltipTitle.textContent = this.dataset.title;
                        tooltipCount.textContent = `${this.dataset.count} Employees`;
                        tooltipColor.style.backgroundColor = this.dataset.color;
                        tooltip.style.display = 'block';
                    });
                    segment.addEventListener('mousemove', function(e) {
                        tooltip.style.left = e.clientX + 'px';
                        tooltip.style.top = e.clientY + 'px';
                    });
                    segment.addEventListener('mouseout', function() {
                        tooltip.style.display = 'none';
                    });
                });
            });
        </script>
    </div>
</div>

<section id="chart-section">
    
    <div class="chart-card" style="width: 100%; margin-bottom: 25px;">
        <h3>Monthly Leave Requests</h3>
        <canvas id="leaveTrendsChart" style="max-height: 400px;"></canvas>
    </div>

    <div class="chart-container">
        
        <div class="chart-card">
            <h3>Leave Status Distribution</h3>
            <canvas id="leaveStatusChart"></canvas>
        </div>

        <div class="chart-card">
            <h3>Users and Leave Type Comparison</h3>
            <canvas id="leaveSummaryChart"></canvas>
        </div>
        
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inject PHP data into JavaScript variables
        const statusLabels = <?= $statusLabels ?>;
        const statusData = <?= $statusData ?>;
        const monthlyLabels = <?= $monthlyLabels ?>;
        const monthlyData = <?= $monthlyData ?>;
        const summaryLabels = <?= $summaryLabels ?>;
        const summaryData = <?= $summaryData ?>;

        // Define color constants for charts
        const primaryRed = '#dc2626'; 
        const darkRed = '#b91c1c';   
        const lightRed = '#f87171';  
        const paleRed = '#fca5a5';   
        
        // Render Leave Status Doughnut Chart
        const statusCtx = document.getElementById('leaveStatusChart');
        if (statusCtx) {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusData,
                        backgroundColor: [
                            lightRed, 
                            '#10b981', 
                            primaryRed 
                        ], 
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true } },
                        tooltip: { backgroundColor: 'rgba(51, 51, 51, 0.9)', borderWidth: 1 }
                    }
                }
            });
        }

        // Render Monthly Trends Line Chart
        const trendsCtx = document.getElementById('leaveTrendsChart');
        if (trendsCtx) {
            new Chart(trendsCtx, {
                type: 'line', 
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'Total Applications',
                        data: monthlyData,
                        borderColor: primaryRed, 
                        backgroundColor: 'rgba(220, 38, 38, 0.1)', 
                        tension: 0.3, 
                        fill: true, 
                        pointBackgroundColor: darkRed,
                        pointRadius: 4,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Applications'
                            },
                            grid: { color: '#f3f4f6' },
                            ticks: { precision: 0 }
                        },
                        x: {
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { 
                            mode: 'index', 
                            intersect: false,
                            backgroundColor: 'rgba(51, 51, 51, 0.9)', 
                            borderWidth: 1
                        }
                    }
                }
            });
        }
        
        // Render Summary Comparison Bar Chart
        const summaryCtx = document.getElementById('leaveSummaryChart');
        if (summaryCtx) {
            new Chart(summaryCtx, {
                type: 'bar',
                data: {
                    labels: summaryLabels,
                    datasets: [{
                        label: 'Count',
                        data: summaryData,
                        backgroundColor: [
                            darkRed,    
                            primaryRed, 
                            lightRed,   
                            paleRed     
                        ],
                        borderColor: darkRed,
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Count of Users / Total'
                            },
                            grid: { color: '#f3f4f6' },
                            ticks: { precision: 0 }
                        },
                        y: {
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { backgroundColor: 'rgba(51, 51, 51, 0.9)', borderWidth: 1 }
                    }
                }
            });
        }
    });
</script>