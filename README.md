# Course Monitoring & Analytics Plugin (local_courseanalytics)

A comprehensive Moodle local plugin for course monitoring and engagement analytics.

## Features
- **Course Dashboard**: High-level overview of all courses with participation and completion charts.
- **Weekly Content Analysis**: Deep dive into course sections, activities, and resources.
- **Student Metrics**: Track student participation, last access dates, and completion status.
- **Export**: Export data to CSV for external processing.
- **Role-Based Access**: 
  - **Admins**: Full visibility across all courses.
  - **Teachers**: Access limited to courses they are enrolled in.

## Installation

1. Copy the `local_courseanalytics` folder to your Moodle's `local/` directory.
   ```bash
   cp -r local_courseanalytics /path/to/moodle/local/
   ```
2. Log in as an administrator.
3. Access the 'Notifications' page in Moodle to trigger the installation of the plugin.
4. Grant the `local/courseanalytics:view` capability to the desired roles (Manager, Editing Teacher, etc.).

## Deployment (VPS / Docker)
This project includes a `docker-compose.yml` and `Dockerfile` for quick deployment.
1. Ensure Docker is installed on your VPS.
2. Run:
   ```bash
   docker-compose up -d
   ```
3. Access Moodle at `http://your-vps-ip:8080`.

## Technical Details
- **Moodle Versions**: Compatible with Moodle 4.x.
- **Language**: English.
- **Libraries**: Chart.js (via Moodle core).

## Developed by
Developed by | KKDES [https://kkdes.co.ke/](https://kkdes.co.ke/)
