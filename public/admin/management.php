<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/middleware/SSOMiddleware.php';

SSOMiddleware::requireAdmin();

// Get statistics
try {
    $studentsCount = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $parentsCount = $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();
    $teachersCount = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
} catch (Exception $e) {
    $studentsCount = 0;
    $parentsCount = 0;
    $teachersCount = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Management Hub</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --strawberry-primary: #E91E63;
            --strawberry-secondary: #F8BBD0;
            --strawberry-accent: #AD1457;
            --matcha-primary: #4CAF50;
            --matcha-secondary: #C8E6C9;
            --matcha-accent: #2E7D32;
            --neutral-light: #F5F5F5;
            --neutral-dark: #424242;
            --neutral-white: #FFFFFF;
            --text-primary: #37474F;
            --text-secondary: #78909C;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--matcha-secondary) 0%, var(--strawberry-secondary) 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 1200px;
            width: 100%;
        }

        .header-card {
            background: var(--neutral-white);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-left: 6px solid var(--strawberry-primary);
            text-align: center;
        }

        .header-card h1 {
            color: var(--strawberry-primary);
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header-card p {
            color: var(--text-secondary);
            font-size: 1.2em;
            margin-bottom: 0;
        }

        .management-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .management-card {
            background: var(--neutral-white);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: all 0.4s ease;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            border: 3px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .management-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(135deg, var(--matcha-primary) 0%, var(--matcha-accent) 100%);
            transition: height 0.4s ease;
        }

        .management-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            border-color: var(--matcha-primary);
        }

        .management-card:hover::before {
            height: 100%;
        }

        .management-card:hover .card-content {
            position: relative;
            z-index: 1;
            color: var(--neutral-white);
        }

        .management-card:hover .card-icon {
            color: var(--neutral-white);
            transform: scale(1.2);
        }

        .management-card:hover .card-title {
            color: var(--neutral-white);
        }

        .management-card:hover .card-description {
            color: rgba(255, 255, 255, 0.9);
        }

        .management-card:hover .card-stats {
            background: rgba(255, 255, 255, 0.2);
            color: var(--neutral-white);
        }

        .card-content {
            transition: all 0.4s ease;
        }

        .card-icon {
            font-size: 4em;
            margin-bottom: 20px;
            transition: all 0.4s ease;
        }

        .card-icon.students {
            color: #2196F3;
        }

        .card-icon.parents {
            color: #FF9800;
        }

        .card-icon.teachers {
            color: #9C27B0;
        }

        .card-title {
            font-size: 1.8em;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-primary);
            transition: all 0.4s ease;
        }

        .card-description {
            color: var(--text-secondary);
            font-size: 1em;
            margin-bottom: 20px;
            transition: all 0.4s ease;
        }

        .card-stats {
            background: var(--neutral-light);
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.3em;
            color: var(--text-primary);
            transition: all 0.4s ease;
        }

        .card-stats .number {
            font-size: 1.5em;
            color: var(--strawberry-primary);
        }

        .back-button {
            background: var(--neutral-white);
            color: var(--text-primary);
            padding: 15px 30px;
            border: none;
            border-radius: 15px;
            font-weight: 600;
            font-size: 1.1em;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .back-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            background: var(--strawberry-primary);
            color: var(--neutral-white);
        }

        .footer-section {
            text-align: center;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .header-card h1 {
                font-size: 2em;
            }

            .management-grid {
                grid-template-columns: 1fr;
            }

            .card-icon {
                font-size: 3em;
            }

            .card-title {
                font-size: 1.5em;
            }
        }

        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .management-card {
            animation: fadeInUp 0.6s ease forwards;
        }

        .management-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .management-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .management-card:nth-child(3) {
            animation-delay: 0.3s;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header-card">
            <h1>🗂️ Data Management Hub</h1>
            <p>Choose a category to manage your school data</p>
        </div>

        <!-- Management Cards Grid -->
        <div class="management-grid">
            <!-- Students Management -->
            <a href="?page=student-management" class="management-card">
                <div class="card-content">
                    <div class="card-icon students">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="card-title">Students</div>
                    <div class="card-description">
                        Manage student records, grades, and class assignments
                    </div>
                    <div class="card-stats">
                        <span class="number"><?php echo number_format($studentsCount); ?></span> Total
                    </div>
                </div>
            </a>

            <!-- Parents Management -->
            <a href="?page=parent-management" class="management-card">
                <div class="card-content">
                    <div class="card-icon parents">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-title">Parents</div>
                    <div class="card-description">
                        Manage parent information and contact details
                    </div>
                    <div class="card-stats">
                        <span class="number"><?php echo number_format($parentsCount); ?></span> Total
                    </div>
                </div>
            </a>

            <!-- Teachers Management -->
            <a href="?page=teacher-management" class="management-card">
                <div class="card-content">
                    <div class="card-icon teachers">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="card-title">Teachers</div>
                    <div class="card-description">
                        Manage teacher profiles and teaching assignments
                    </div>
                    <div class="card-stats">
                        <span class="number"><?php echo number_format($teachersCount); ?></span> Total
                    </div>
                </div>
            </a>
        </div>

        <!-- Back Button -->
        <div class="footer-section">
            <a href="?page=admin" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>