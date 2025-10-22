<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSO Login Required - PTM Portal</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
        }
        .sso-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 650px;
            padding: 50px;
            text-align: center;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h1 {
            color: #1c1e21;
            font-weight: 700;
            margin-bottom: 15px;
        }
        .instructions {
            background: #f0f2f5;
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0;
            text-align: left;
        }
        .step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
        }
        .step-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .btn-msp {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 48px;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-msp:hover {
            transform: translateY(-2px);
            color: white;
        }
    </style>
</head>
<body>
    <div class="sso-card">
        <div class="icon">🔐</div>
        <h1>Login Through MySchoolPortal</h1>
        <p class="lead">PTM Portal uses secure Single Sign-On (SSO) for parents and teachers.</p>
        
        <div class="instructions">
            <h5>How to Access PTM Portal:</h5>
            
            <div class="step">
                <div class="step-number">1</div>
                <div>
                    <strong>Log into MySchoolPortal</strong><br>
                    <small>Visit <?= htmlspecialchars(MSP_DOMAIN) ?></small>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">2</div>
                <div>
                    <strong>Find PTM Portal Link</strong><br>
                    <small>Look for "PTM Portal" in your dashboard</small>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">3</div>
                <div>
                    <strong>Click to Access</strong><br>
                    <small>You'll be automatically logged in!</small>
                </div>
            </div>
        </div>
        
        <a href="https://<?= htmlspecialchars(MSP_DOMAIN) ?>" class="btn-msp">
            Go to MySchoolPortal →
        </a>
        
        <hr style="margin: 40px 0;">
        
        <small>School administrators: <a href="?page=admin-login">Admin Login</a></small>
    </div>
</body>
</html>