<?php
require_once __DIR__ . '/../config/helpers.php';
if (function_exists('init_academic_session')) {
    init_academic_session();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School SaaS - Admin Dashboard</title>
    
    <!-- Google Fonts (Metrophobic, Poppins, Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Metrophobic&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    
    <!-- Tabler Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    
    <!-- Bootstrap 5.3 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Phosphor Icons CDN (Regular, Light, Bold, and Fill weights) -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/light/style.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/bold/style.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css" />
    
    <!-- Custom Style Sheets -->
    <link href="<?php echo BASE_URL; ?>assets/css/main.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/responsive.css" rel="stylesheet">
    
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Glowing background blur blobs -->
    <div class="glass-bg-blob blob-primary-glow"></div>
    <div class="glass-bg-blob blob-success-glow"></div>
    <div class="app-layout">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <!-- Main Wrapper -->
        <div class="main-wrapper">
            <!-- Topbar Navigation -->
            <?php include __DIR__ . '/topbar.php'; ?>
            
            <!-- Page Content Viewport -->
            <main class="content-area">
