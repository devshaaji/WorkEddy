<?php
// views/partials/site/v2_header.php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'WorkEddy - Ergonomics Risk Assessment' ?></title>
    <!-- Favicon -->
    <link rel="icon" href="/assets/img/favicon.ico">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="/assets/css/core.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        /* Dark Theme Core V2 */
        :root {
            --bg-base: #0a0a0a;
            --panel-bg: #111827;
            --panel-border: #1f2937;
            --tech-accent: #38bdf8;
            --tech-success: #10b981;
            --tech-danger: #ef4444;
            --tech-warning: #f59e0b;
            --font-mono: 'JetBrains Mono', 'Courier New', monospace;
        }

        body {
            background-color: var(--bg-base);
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .font-mono {
            font-family: var(--font-mono);
        }

        .text-cyan {
            color: var(--tech-accent) !important;
        }

        .text-emerald {
            color: var(--tech-success) !important;
        }

        .text-muted {

            color: rgba(208, 214, 219, 0.75) !important;
        }

        /* Navigation */
        .marketing-nav {
            background: rgba(10, 10, 10, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--panel-border);
        }

        .nav-link {
            color: #e2e8f0 !important;
            transition: color 0.2s;
            font-weight: 500;
        }

        .nav-link:hover {
            color: #ffffff !important;
        }

        /* Buttons */
        .btn-tech {
            background: var(--tech-success);
            color: #0f172a;
            border: 1px solid var(--tech-success);
            border-radius: 4px;
            font-weight: 700;
            transition: all 0.2s;
        }

        .btn-tech:hover {
            background: transparent;
            color: var(--tech-accent);
            box-shadow: 0 0 15px rgba(56, 189, 248, 0.3);
        }

        .btn-outline-tech {
            background: transparent;
            color: #f8fafc;
            border: 1px solid var(--panel-border);
            border-radius: 4px;
            font-weight: 600;
        }

        .btn-outline-tech:hover {
            border-color: #475569;
            background: rgba(255, 255, 255, 0.02);
            color: #f8fafc;
        }

        /* Panels & Badges */
        .tech-panel {
            background: var(--panel-bg);
            border: 1px solid var(--panel-border);
            border-radius: 6px;
            position: relative;
        }

        .tech-badge {
            font-family: var(--font-mono);
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
        }

        /* Improved Page Header for Secondary Pages */
        .page-header {
            padding: 110px 0 50px 0;
            /* Reduced wasted spacing to improve design */
            background: linear-gradient(180deg, rgba(56, 189, 248, 0.04) 0%, transparent 100%);
            border-bottom: 1px solid var(--panel-border);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--tech-accent), transparent);
            opacity: 0.5;
        }

        /* Add a subtle grid to the header for that tech feel */
        .page-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
            background-size: 30px 30px;
            z-index: 0;
            pointer-events: none;
        }

        .hover-text-white:hover {
            color: #fff !important;
        }

        /* Policy Pages Custom Styles */
        .policy-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #cbd5e1;
            max-width: 800px;
            margin: 0 auto;
        }

        .policy-content h2 {
            font-family: var(--font-mono);
            font-weight: 700;
            color: #f8fafc;
            margin-top: 3.5rem;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            border-bottom: 1px solid var(--panel-border);
            padding-bottom: 0.75rem;
        }

        .policy-content h2:first-child {
            margin-top: 0;
        }

        .policy-content h3 {
            font-family: var(--font-mono);
            font-weight: 700;
            color: #e2e8f0;
            margin-top: 2.5rem;
            margin-bottom: 1rem;
            font-size: 1.15rem;
        }

        .policy-content p {
            margin-bottom: 1.25rem;
        }

        .policy-content ul,
        .policy-content ol {
            margin-bottom: 1.75rem;
            padding-left: 1.5rem;
        }

        .policy-content li {
            margin-bottom: 0.5rem;
        }

        .contact-box {
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid var(--panel-border);
            border-radius: 6px;
        }

        /* Founder Story Custom Styles */
        .founder-card {
            background: rgba(17, 24, 39, 0.8);
            border: 1px solid var(--panel-border);
            border-radius: 6px;
            padding: 2.5rem;
            margin-top: -60px;
            position: relative;
            z-index: 10;
            backdrop-filter: blur(8px);
        }

        .story-content {
            font-size: 1.15rem;
            line-height: 1.9;
            color: #cbd5e1;
        }

        .story-content p {
            margin-bottom: 2rem;
        }

        .story-content p:first-of-type {
            font-size: 1.35rem;
            font-weight: 500;
            color: #f8fafc;
            line-height: 1.7;
            border-left: 3px solid var(--tech-accent);
            padding-left: 1.5rem;
            margin-bottom: 3rem;
        }

        .strong-take {
            background: rgba(56, 189, 248, 0.05);
            border: 1px solid rgba(56, 189, 248, 0.15);
            border-radius: 6px;
            padding: 2.5rem;
            color: #e0f2fe;
            line-height: 1.6;
            margin-top: 4rem;
            margin-bottom: 2rem;
        }

        /* Hero section styles for index2.php */
        .landing-hero {
            padding: 140px 0 0 0;
            position: relative;
            overflow: hidden;
            background: radial-gradient(circle at 50% 10%, rgba(56, 189, 248, 0.05), transparent 60%);
        }

        .grid-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.01) 1px, transparent 1px),
                 linear-gradient(90deg, rgba(255, 255, 255, 0.01) 1px, transparent 1px);
            background-size: 40px 40px;
            z-index: 0;
            pointer-events: none;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -1px;
            color: #ffffff;
            margin-bottom: 1.5rem;
        }

        .hero-title span {
            color: var(--tech-accent);
        }

        .hero-subtitle {
            font-size: 1.125rem;
            color: #cbd5e1;
            margin-bottom: 2.5rem;
            font-weight: lighter;
            line-height: 1.6;
        }

        .stats-card {
            background: rgba(17, 24, 39, 0.4);
            border: 1px solid var(--panel-border);
            border-radius: 6px;
            padding: 1.5rem;
            text-align: center;
        }

        .stats-card:hover {
            background: rgba(17, 24, 39, 0.8);
            border-color: #334155;
        }

        .stat-value {
            font-family: var(--font-mono);
            font-size: 2rem;
            font-weight: 700;
            color: var(--tech-accent);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .feature-icon-wrapper {
            width: 48px;
            height: 48px;
            background: rgba(56, 189, 248, 0.1);
            border: 1px solid rgba(56, 189, 248, 0.2);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--tech-accent);
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
        }

        .tech-panel-header {
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid var(--panel-border);
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Industry Tabs */
        .industry-tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 1px solid var(--panel-border);
            padding-bottom: 1rem;
        }

        .ind-tab {
            background: transparent;
            border: 1px solid transparent;
            color: #94a3b8;
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
            border-radius: 4px;
            cursor: pointer;
            font-family: var(--font-mono);
            transition: all 0.2s;
        }

        .ind-tab.active {
            color: var(--tech-accent);
            border-color: var(--panel-border);
            background: rgba(255, 255, 255, 0.02);
        }

        .ind-tab:hover:not(.active) {
            color: #cbd5e1;
        }

        /* Data Points */
        .data-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px dashed var(--panel-border);
            font-size: 0.85rem;
        }

        .data-row:last-child {
            border-bottom: none;
        }

        /* Video Frame */
        .video-analyzer {
            position: relative;
            background: #000;
            border: 1px solid var(--panel-border);
            border-radius: 4px;
            overflow: hidden;
        }

        .bounding-box {
            position: absolute;
            border: 1px dashed var(--tech-accent);
            background: rgba(56, 189, 248, 0.05);
            top: 10%;
            left: 30%;
            right: 20%;
            bottom: 10%;
            pointer-events: none;
        }

        .corner-tl {
            position: absolute;
            top: -1px;
            left: -1px;
            width: 10px;
            height: 10px;
            border-top: 2px solid var(--tech-accent);
            border-left: 2px solid var(--tech-accent);
        }

        .corner-tr {
            position: absolute;
            top: -1px;
            right: -1px;
            width: 10px;
            height: 10px;
            border-top: 2px solid var(--tech-accent);
            border-right: 2px solid var(--tech-accent);
        }

        .corner-bl {
            position: absolute;
            bottom: -1px;
            left: -1px;
            width: 10px;
            height: 10px;
            border-bottom: 2px solid var(--tech-accent);
            border-left: 2px solid var(--tech-accent);
        }

        .corner-br {
            position: absolute;
            bottom: -1px;
            right: -1px;
            width: 10px;
            height: 10px;
            border-bottom: 2px solid var(--tech-accent);
            border-right: 2px solid var(--tech-accent);
        }

        /* Scanner Animation */
        .scanner-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: rgba(16, 185, 129, 0.5);
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.8);
            top: 0;
            animation: scanVertical 3s ease-in-out infinite alternate;
            z-index: 10;
        }

        @keyframes scanVertical {
            0% {
                top: 5%;
            }

            100% {
                top: 95%;
            }
        }

        .pricing-card {
            background: var(--panel-bg);
            border: 1px solid var(--panel-border);
            transition: border-color 0.3s;
        }

        .pricing-card:hover {
            border-color: #334155;
        }

        .pricing-card.pro {
            border: 1px solid rgba(56, 189, 248, 0.5);
            box-shadow: inset 0 0 20px rgba(56, 189, 248, 0.05);
        }

        /* Responsive adjustments (desktop-first) */
        @media (max-width: 992px) {
            .landing-hero .hero-title {
                font-size: 2rem;
            }

            .landing-hero .hero-subtitle {
                font-size: 1rem;
            }

            .tech-panel {
                max-width: 100%;
            }

            .video-analyzer {
                height: 200px;
            }

            .tech-panel-header {
                gap: 0.5rem;
            }
        }

        @media (max-width: 768px) {
            .tech-panel-header {
                align-items: flex-start;
            }

            .industry-tabs {
                gap: 0.4rem;
            }
        }

        @media (max-width: 576px) {
            .landing-hero .hero-title {
                font-size: 1.6rem;
            }

            .industry-tabs .ind-tab {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }

            .video-analyzer {
                height: 160px;
            }
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top marketing-nav py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none" href="/index2">
                <img src="/assets/img/workeddy.png" alt="WorkEddy" class="img-fluid" style="max-width: 150px;;">
            </a>
            <button class="navbar-toggler border-0 shadow-none text-white" type="button" data-bs-toggle="collapse" data-bs-target="#marketingNavbar">
                <i class="bi bi-list fs-2"></i>
            </button>
            <div class="collapse navbar-collapse" id="marketingNavbar">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 fw-medium font-mono text-uppercase" style="font-size: 0.85rem;">
                    <li class="nav-item"><a class="nav-link px-3" href="/index2#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="/index2#how-it-works">How it Works</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="/index2#pricing">Pricing</a></li>
                </ul>
                <div class="d-flex gap-3 flex-column flex-lg-row mt-3 mt-lg-0 font-mono" style="font-size:0.85rem;">
                    <a href="/login" class="text-white text-decoration-none d-flex align-items-center">Login</a>
                    <a href="/register" class="btn btn-tech px-4 py-2 text-uppercase">Get Started</a>
                </div>
            </div>
        </div>
    </nav>