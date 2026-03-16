<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/functions.php';
requireRole('siswa');

$pageTitle  = 'Statistik Belajar';
$activePage = 'analytics';
$base = '/TUGASPAKDANIL/ABSENSITALENTA';
$studentId = currentUser()['id'];

// Get quiz scores (Line Chart)
$quizScores = db()->query("
    SELECT q.title, qa.score, qa.submitted_at 
    FROM quiz_answers qa
    JOIN quizzes q ON q.id = qa.quiz_id
    WHERE qa.student_id = $studentId
    ORDER BY qa.submitted_at ASC LIMIT 10
")->fetchAll();

$quizLabels = [];
$quizData = [];
foreach ($quizScores as $q) {
    $quizLabels[] = substr($q['title'], 0, 15) . '...';
    $quizData[] = (float)$q['score'];
}

// Get attendance stats (Doughnut Chart)
$currMonth = date('Y-m');
$totalPresent = db()->query("
    SELECT COUNT(*) FROM attendance_records
    WHERE student_id = $studentId AND DATE_FORMAT(attended_at, '%Y-%m') = '$currMonth'
")->fetchColumn();

$totalLeave = db()->query("
    SELECT COUNT(*) FROM leave_permissions lp 
    JOIN attendance_records ar ON ar.id=lp.attendance_id 
    WHERE lp.student_id = $studentId AND DATE_FORMAT(ar.attended_at, '%Y-%m') = '$currMonth'
")->fetchColumn();

// Average score
$avgScore = count($quizData) > 0 ? array_sum($quizData) / count($quizData) : 0;

include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem">
  <h2><i class="fas fa-chart-line text-primary"></i> Statistik Belajar Bulanan</h2>
</div>

<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom:1.5rem">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
    <div>
        <div class="stat-value"><?= $totalPresent ?></div>
        <div class="stat-label">Kehadiran (Bulan Ini)</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fas fa-door-open"></i></div>
    <div>
        <div class="stat-value"><?= $totalLeave ?></div>
        <div class="stat-label">Izin Pulang (Bulan Ini)</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fas fa-star"></i></div>
    <div>
        <div class="stat-value"><?= number_format($avgScore, 1) ?></div>
        <div class="stat-label">Rata-rata Ulangan (Terbaru)</div>
    </div>
  </div>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:1.5rem; flex-wrap:wrap">
    <!-- Chart: Ulangan -->
    <div class="card" style="min-width:300px;">
        <div class="card-header">
            <h3><i class="fas fa-chart-area text-accent"></i> Grafik Nilai Ulangan</h3>
        </div>
        <div style="padding:1rem; position:relative; height: 300px;">
            <canvas id="quizChart"></canvas>
        </div>
    </div>

    <!-- Chart: Kehadiran -->
    <div class="card" style="min-width:300px;">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie text-accent"></i> Proporsi Kehadiran</h3>
        </div>
        <div style="padding:1rem; position:relative; height: 300px; display:flex; justify-content:center">
            <canvas id="attendanceChart"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Quiz Line Chart
    const ctxQuiz = document.getElementById('quizChart').getContext('2d');
    const quizLabels = <?= json_encode($quizLabels) ?>;
    const quizData = <?= json_encode($quizData) ?>;
    
    // Gradient styling for Chart.js
    let gradient = ctxQuiz.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(67, 97, 238, 0.5)'); // primary
    gradient.addColorStop(1, 'rgba(67, 97, 238, 0.0)');
    
    if(quizData.length > 0) {
        new Chart(ctxQuiz, {
            type: 'line',
            data: {
                labels: quizLabels,
                datasets: [{
                    label: 'Nilai Ulangan',
                    data: quizData,
                    borderColor: '#4361ee',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#4361ee',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { min: 0, max: 100 }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    } else {
        document.getElementById('quizChart').parentNode.innerHTML = '<div class="empty-state">Belum ada data nilai ulangan.</div>';
    }

    // 2. Attendance Doughnut Chart
    const ctxAtt = document.getElementById('attendanceChart').getContext('2d');
    const present = <?= $totalPresent ?>;
    const leave = <?= $totalLeave ?>;
    
    if (present > 0 || leave > 0) {
        new Chart(ctxAtt, {
            type: 'doughnut',
            data: {
                labels: ['Hadir Penuh', 'Izin Pulang'],
                datasets: [{
                    data: [(present - leave), leave],
                    backgroundColor: ['#2a9d8f', '#e9c46a'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    } else {
        document.getElementById('attendanceChart').parentNode.innerHTML = '<div class="empty-state">Belum ada data kehadiran bulan ini.</div>';
    }
});
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/footer.php'; ?>
