# WorkEddy – Full Production Software Engineering Specification

Version: 1.0
Audience: Software Engineers, ML Engineers, DevOps, Product

---

# 1. Product Overview

WorkEddy is a SaaS platform designed to detect ergonomic and musculoskeletal risk factors in warehouse and logistics work environments.

The system allows organizations to perform **task risk scans** using two input sources:

1. Manual ergonomic task input
2. Video-based posture analysis

Both pipelines generate standardized ergonomic risk scores used for prevention, analytics, and research validation.

The platform supports:

* Multi-organization SaaS deployment
* Usage-based billing
* Worker task scanning
* Observer validation workflows
* Risk monitoring dashboards

---

# 2. Product Principles

System design follows these principles:

1. Input methods must remain independent
2. Risk scoring must be normalized
3. Processing must be asynchronous
4. System must scale horizontally
5. Privacy-first video handling
6. Transparent risk scoring

---

# 3. Core Functional Modules

The system consists of the following modules.

1 Authentication
2 Organization Management
3 User Management
4 Task Management
5 Scan Engine
6 Video Processing Pipeline
7 Risk Scoring Engine
8 Observer Rating Module
9 Dashboard & Analytics
10 Billing & Usage Tracking

---

# 4. System Architecture

High level architecture:

Client Applications

Web App (Bootstrap 5)
Mobile Browser Interface

Backend Services

API Server (PHP)
Video Processing Workers (Python)
Risk Engine

Infrastructure

MySQL Database
Redis Queue
Server File Storage (local or mounted network storage)

Architecture Diagram

Client
|
v
API Server (PHP)
|
+------ MySQL
|
+------ Redis Queue
|
+------ File Storage (/storage/uploads)

Worker Cluster (Python)
|
v
Pose Analysis Engine
|
v
Risk Scoring Engine

---

# 5. Technology Stack

Frontend

HTML Bootstrap 5 Vanilla JavaScript Fetch API Alpine.js

Backend

Language: PHP 8.2+
API Style: REST
Framework Style: Modular Plain PHP (no full framework)

Core Libraries

Routing

* FastRoute

Logging

* Monolog

Database Layer

* Doctrine DBAL (query builder + connection management)

Authentication

* firebase/php-jwt

Dependency Management

* Composer

These libraries provide a lightweight but production-grade backend stack while avoiding heavy frameworks.

Video Processing

Language: Python

Libraries:

OpenCV
MediaPipe Pose
NumPy
SciPy

Database

MySQL 8+

Queue

Redis

File Storage

Server-side filesystem storage.

Used for:

Video uploads
Processing artifacts
Temporary frames

Files stored under `/storage/uploads` and `/storage/processing`.

Infrastructure

Docker
Docker Compose
Nginx

---

# 6. SaaS Multi‑Tenant Model

The system operates as a multi-tenant SaaS platform.

Each customer is an Organization.

Tenant isolation method:

Shared database
Organization ID scoped data

All major tables include:

organization_id

---

# 7. Roles and Permissions

Roles

Admin
Supervisor
Worker
Observer

Admin Permissions

Manage organization
Manage users
View analytics
Manage billing

Supervisor Permissions

Create tasks
Run scans
View task reports

Worker Permissions

Perform scans
View personal scan results

Observer Permissions

Rate scans
Add ergonomic notes

---

# 8. Authentication System

Authentication uses secure token-based login.

Signup Flow

User registers
Organization created
User becomes Admin

Fields

name
email
password
organization_name

Login Flow

email
password

Security

Password hashing: bcrypt
Session token: JWT

Optional

2FA via OTP

---

# 9. Usage-Based Pricing Model

The system charges organizations based on **scan usage**.

A scan counts when:

Manual scan submitted
Video scan processed successfully

Pricing Model Example

Starter Plan

100 scans per month

Professional

500 scans per month

Enterprise

Unlimited scans

Usage Tracking Table

usage_records

Fields

id
organization_id
scan_id
usage_type
created_at

Billing Cycle

Monthly usage aggregation

---

# 10. Dashboard

The dashboard provides high-level insights.

Metrics

Total scans
High risk tasks
Moderate risk tasks
Risk distribution

Charts

Scan trends
Risk distribution
Department risk heatmap

---

# 11. Task Management

Tasks represent real-world activities performed by workers.

Example

Loading boxes
Sorting packages
Pallet stacking

Task Fields

id
organization_id
name
description
department
created_at

---

# 12. Scan System

A scan represents a risk assessment event.

Two scan types exist.

Manual Scan
Video Scan

Scan lifecycle

Created
Processing (video only)
Completed
Invalid

---

# 13. Manual Scan Engine

Manual scans use structured ergonomic questionnaires.

Fields

weight_lifted
lift_frequency
task_duration
trunk_flexion_estimate
twisting
repetition_rate
overhead_work

Manual Risk Algorithm

Example

score =
(weight_factor * weight)
+
(frequency_factor * repetition)
+
(duration_factor * duration)
+
(posture_factor * trunk_flexion)

---

# 14. Video Scan Workflow

Video Scans follow asynchronous processing.

Steps

1 Worker records video
2 Video uploaded
3 Job queued
4 Worker processes job
5 Pose estimation
6 Posture metrics calculated
7 Risk score generated
8 Scan marked complete

---

# 15. Pose Estimation Engine

Pose detection extracts human skeletal landmarks.

Landmarks

Head
Neck
Shoulders
Elbows
Wrists
Hips
Knees
Ankles

Frame Processing

Video decoded
Frames sampled
Pose estimation applied
Joint angles computed

Frame Sampling

Process every 4th frame to reduce load.

---

# 16. Posture Metrics

Derived metrics include:

max_trunk_angle
avg_trunk_angle
shoulder_elevation_duration
repetition_count
time_in_high_risk_posture

---

# 17. Risk Scoring Engine

Video metrics are translated into risk scores.

Example

Trunk angle > 60° = +30 points
Shoulder elevation > 30% time = +20
High repetition = +15

Scores normalized to:

0 – 100

---

# 18. Repeat Scan Tracking

Scans may reference earlier scans.

Example

scan_A = baseline
scan_B = after correction

System computes improvement delta.

---

# 19. Observer Validation Module

Observers provide expert ratings.

Used for research comparison.

Fields

observer_score
observer_category
notes

---

# 20. Database Schema

organizations

id
name
plan
created_at

users

id
organization_id
name
email
password_hash
role
created_at

plans

id
name
scan_limit
price

subscriptions

id
organization_id
plan_id
start_date
end_date
status

tasks

id
organization_id
name
description
department
created_at

scans

id
organization_id
user_id
task_id
scan_type
raw_score
normalized_score
risk_category
parent_scan_id
status
video_path
created_at

manual_inputs

scan_id
weight
frequency
duration
trunk_angle_estimate
twisting
overhead
repetition

video_metrics

scan_id
max_trunk_angle
avg_trunk_angle
shoulder_elevation_duration
repetition_count
processing_confidence

observer_ratings

id
scan_id
observer_id
observer_score
observer_category
notes

usage_records

id
organization_id
scan_id
usage_type
created_at

---

# 21. API Design

Auth

POST /auth/signup
POST /auth/login
POST /auth/logout

Tasks

POST /tasks
GET /tasks
GET /tasks/{id}

Scans

POST /scans/manual
POST /scans/video
GET /scans/{id}
GET /scans

Observer

POST /observer-rating

Dashboard

GET /dashboard

---

# 22. Video Processing Workers

Workers poll Redis queue.

Job payload

scan_id
video_path

Worker steps

Read video from server storage path
Extract frames
Run pose estimation
Calculate posture metrics
Generate risk score
Update scan record

---

# 23. Storage Design (Server File Storage)

Videos and processing artifacts are stored directly on the application server filesystem or on a mounted storage volume.

Directory Structure

/storage

uploads/

videos/

processed/

frames/

reports/

Storage Strategy

* Uploaded videos stored in `/storage/uploads/videos`
* Extracted frames stored temporarily in `/storage/uploads/frames`
* Processing outputs stored in `/storage/uploads/processed`

Privacy Handling

* Videos are not publicly accessible
* Access is controlled through authenticated API endpoints

Video Retention

Raw videos can be automatically deleted after processing depending on policy.

---

# 24. Data Retention

Video retention configurable.

Example:

30 days default.

After that:

Video deleted, analysis kept.

---

# 25. DevOps Architecture

Infrastructure components

Nginx
PHP API containers
Python worker containers
MySQL
Redis
server storage

Deployment via Docker Compose initially.

Kubernetes optional later.

---

# 26. Scaling Strategy

API servers scale horizontally.

Video processing workers scale independently.

Queue buffers processing load.

---

# 27. Logging

Centralized logs recommended.

Logs

API logs
Worker logs
Processing logs
Security logs

---

# 28. Testing Strategy

Unit Tests

Risk scoring engine
API validation

Integration Tests

Manual scan workflow
Video scan workflow

Load Testing

Video processing queue
Concurrent scan submissions

---

# 29. Development Workflow

Repository structure

/api
/workers
/frontend
/docs

Branch strategy

main
staging
develop

Pull request reviews required.

---

# 30. Initial Development Milestones

Milestone 1

Authentication
Organizations
User roles

Milestone 2

Task management
Manual scan engine
Dashboard

Milestone 3

Video upload
Queue system
Worker processing

Milestone 4

Observer validation
Usage billing
Analytics

---

# 31. Future Enhancements

Real-time video posture analysis
ML-based ergonomic risk prediction
Native mobile apps
Automated ergonomic recommendations

---

# UI / UX PAGE SPECIFICATION

## 1. Landing Page

Purpose: Explain product value and convert visitors.

Sections:

* Hero section (product summary)
* Explanation of musculoskeletal risk detection
* How WorkEddy works
* Pricing tiers (usage based)
* CTA: Sign Up
* CTA: Book Demo

Components:

* Navbar
* Footer
* Pricing cards

Frontend: Bootstrap 5 components.

---

## 2. Login Page

Fields:

* Email
* Password

Actions:

* Login
* Forgot password

Security:

* Rate limiting
* CAPTCHA (optional)

---

## 3. Signup Page

Fields:

* Organization Name
* Admin Name
* Email
* Password
* Confirm Password

Process:

1. Create organization
2. Create admin user
3. Assign trial credits

---

## 4. Dashboard

Purpose: Provide overview of activity.

Widgets:

* Total scans used
* Remaining usage credits
* High risk tasks
* Recent scans
* Tasks needing follow-up

Charts:

* Risk distribution
* Weekly scan usage

---

## 5. Tasks Page

Purpose: Manage warehouse tasks.

Features:

* Create task
* Edit task
* Assign department

Task fields:

* Task name
* Description
* Workstation
* Department

---

## 6. New Scan Page

User chooses:

Scan Type:

* Manual assessment
* Video analysis

---

## 7. Manual Scan Form

Sections:

Posture Assessment

* Trunk angle
* Neck angle
* Arm elevation

Load Assessment

* Weight handled

Repetition

* Repetitions per minute

Duration

* Exposure duration

System computes risk score immediately.

---

## 8. Video Upload Page

Upload fields:

* Video file
* Task
* Worker role

Supported formats:

* MP4
* MOV

After upload:
Video stored on server filesystem under `/storage/uploads/videos/`.

Processing status shown.

---

## 9. Scan Results Page

Displays:

* Risk score
* Risk category
* Body segment analysis

Visual components:

* Pose skeleton overlay
* Risk heatmap

---

## 10. Repeat Scan Comparison

Displays:

Before vs After risk score.

Charts:

* Risk reduction graph

---

## 11. Observer Validation Page

Purpose: Allow ergonomic observers to input rating.

Fields:

* RULA score
* REBA score

Comparison shown against WorkEddy score.

---

# API CONTRACT DEFINITIONS

All endpoints use JSON.

Base URL:

/api/v1/

Authentication:

Bearer token via Authorization header.

---

## Auth

POST /auth/signup

Request:

{
"organization_name": "Warehouse Ltd",
"email": "admin@company.com",
"password": "password"
}

Response:

{
"user_id": 1,
"token": "JWT_TOKEN"
}

---

POST /auth/login

Request:

{
"email": "admin@company.com",
"password": "password"
}

Response:

{
"token": "JWT_TOKEN"
}

---

## Tasks

GET /tasks

Returns list of tasks.

POST /tasks

Request:

{
"name": "Box Lifting",
"description": "Lift box from pallet"
}

---

## Scans

POST /scans/manual

Request:

{
"task_id": 3,
"trunk_angle": 45,
"neck_angle": 20,
"arm_angle": 30,
"weight": 15,
"repetition_rate": 10
}

Response:

{
"risk_score": 68,
"risk_category": "High"
}

---

POST /scans/video

Upload multipart form.

Returns:

{
"scan_id": 24,
"status": "processing"
}

---

GET /scans/{id}

Returns scan results.

---

# VIDEO POSE DETECTION ALGORITHM

Pipeline:

1. Video upload
2. Frame extraction
3. Pose detection
4. Angle calculation
5. Risk model scoring

Recommended library:

MediaPipe Pose

or

OpenPose

---

## Frame Processing

Video split into frames every:

10 fps

---

## Keypoints

Pose detection extracts:

* Shoulder
* Elbow
* Wrist
* Hip
* Knee
* Ankle

---

## Angle Calculation

Example: trunk flexion

Angle between:

hip -> shoulder vector
and
vertical axis

---

## Risk Mapping

Angles mapped to ergonomic risk bands.

Example:

Trunk flexion:

0–20° : Low

20–45° : Medium

> 45° : High

---

# QUEUE + WORKER SCALING MODEL

Video processing is CPU intensive.

Use queue-based architecture.

---

## Queue System

Recommended:

Redis + BullMQ

or

RabbitMQ

---

## Worker Types

Video workers

Responsible for:

* Frame extraction
* Pose detection

Risk workers

Responsible for:

* Angle calculation
* Risk scoring

---

## Worker Scaling

Horizontal scaling based on queue size.

Example:

If queue length > 100

Auto scale workers.

---

## Processing Pipeline

1. Upload video
2. Job added to queue
3. Worker pulls job
4. Frames extracted
5. Pose detection
6. Risk analysis
7. Results saved

---

# PERFORMANCE TARGETS

Video processing target:

1 minute video processed in < 90 seconds.

---

# FUTURE EXTENSIONS

Potential future modules:

* Real time camera analysis
* Mobile scanning
* AI risk prediction
* Integration with warehouse WMS systems

---

# 15. Project Structure

This project follows a **modular monolithic architecture** suitable for a PHP + Python worker environment. The web application handles UI, authentication, and API orchestration, while heavy video processing runs in isolated worker services.

```text
workedddy/
│
├── composer.json
├── composer.lock
│
├── app/
│   ├── config/
│   │   ├── app.php
│   │   ├── database.php
│   │   ├── queue.php
│   │   └── storage.php
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── ScanController.php
│   │   ├── TaskController.php
│   │   ├── WorkspaceController.php
│   │   └── BillingController.php
│   ├── services/
│   │   ├── ScanService.php
│   │   ├── RiskScoreService.php
│   │   ├── VideoProcessingService.php
│   │   ├── UsageMeterService.php
│   │   └── NotificationService.php
│   ├── repositories/
│   │   ├── UserRepository.php
│   │   ├── ScanRepository.php
│   │   ├── TaskRepository.php
│   │   └── WorkspaceRepository.php
│   ├── middleware/
│   │   ├── AuthMiddleware.php
│   │   ├── TenantMiddleware.php
│   │   └── RateLimitMiddleware.php
│   ├── models/
│   │   ├── User.php
│   │   ├── Workspace.php
│   │   ├── Task.php
│   │   ├── Scan.php
│   │   └── ScanResult.php
│   └── helpers/
│       ├── Response.php
│       ├── Validator.php
│       └── Auth.php
├── public/
│   ├── index.php
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   └── uploads/
├── routes/
│   ├── web.php
│   └── api.php
├── views/
│   ├── layouts/
│   │   ├── main.php
│   │   └── auth.php
│   ├── auth/
│   │   ├── login.php
│   │   ├── register.php
│   │   └── forgot-password.php
│   ├── dashboard/
│   │   └── index.php
│   ├── scans/
│   │   ├── new-manual.php
│   │   ├── new-video.php
│   │   └── results.php
│   └── tasks/
│       ├── index.php
│       └── view.php
├── storage/
│   ├── logs/
│   ├── temp/
│   └── exports/
├── workers/
│   ├── video-worker/
│   │   ├── worker.py
│   │   ├── pose_detector.py
│   │   ├── frame_extractor.py
│   │   └── risk_calculator.py
│   └── queue-listener/
│       └── worker_runner.py
├── ml/
│   ├── models/
│   │   └── pose_model.onnx
│   ├── processing/
│   │   ├── pose_estimation.py
│   │   ├── angle_calculation.py
│   │   └── ergonomic_rules.py
├── infrastructure/
│   ├── docker/
│   │   ├── php.dockerfile
│   │   ├── worker.dockerfile
│   │   └── nginx.conf
│   └── queue/
│       └── redis.conf
├── scripts/
│   ├── deploy.sh
│   ├── migrate.php
│   └── seed.php
├── tests/
│   ├── api/
│   ├── services/
│   └── workers/
└── README.md
```

## Key Directory Responsibilities

### app/

Core backend business logic.

### workers/

Isolated video analysis services running independently from the web app.

### ml/

Machine learning utilities and ergonomic analysis logic.

### infrastructure/

Infrastructure configuration for Docker and queues.

### storage/

Server-side persistent storage for uploaded videos and generated artifacts.

### routes/

Defines all HTTP and API routes.

### views/

Bootstrap 5 server-rendered UI pages.

---

## Worker Communication Flow

1. User uploads video
2. File stored in server storage (`/storage/uploads/videos`)
3. Job pushed to Redis queue
4. Video worker retrieves job
5. Worker processes video
6. Pose detection executed
7. Risk score computed
8. Results saved in database
9. Dashboard updated

---

## Development Environments

### Local Development

Components:

* PHP Application
* MySQL
* Redis
* Python Workers

Run using Docker Compose.

### Production

Recommended architecture:

* Load balancer
* Multiple PHP web servers
* Redis cluster
* Worker autoscaling nodes

---

## Coding Conventions

Backend:

* PSR-style naming conventions
* Service-based business logic
* Repository pattern for DB

Frontend:

* Bootstrap 5
* Fetch API for async operations

Workers:

* Python 3.11
* Async job processing
