#### EasyKube Back-End
# What is EasyKube?
EasyKube is a platform designed to simplify and automate application deployment in Kubernetes environments. It consists of a Python image installed in a Kubernetes cluster and a web application (accessible from both web and mobile) that enables the easy, secure, and agile management of key workloads like web pages, databases, and Python microservices. EasyKube is built for users with little or no Kubernetes experience, allowing anyone to benefit from container orchestration without the steep learning curve.

## Architecture & Repositories
EasyKube is composed of three core repositories:
- **Front-end**: The user-facing web (and mobile) application, providing an intuitive interface for deploying and managing workloads.
- **Back-end**: Implements the application's logic, runs the automation scripts, interacts with databases, and handles user requests for deployment and configuration.
- **ControlPlane**: A container deployed directly into the Kubernetes cluster. Its purpose is to manage the cluster in a simple way by connecting it to the EasyKube profile—enabling centralized, streamlined orchestration and monitoring for any user who adds their cluster to EasyKube.

> Click [here](https://github.com/diegomartincp/easykube-frontend) to go to the EasyKube front-end repository

> Click [here](https://github.com/diegomartincp/easykube-installation) to go to the EasyKube ControlPlane instalation repository

## What Problem Does EasyKube Solve?
Kubernetes offers great flexibility but is notoriously difficult to configure and operate without advanced technical skills. Many users—especially beginners—face challenges such as configuration errors, security issues, and an overwhelming set of tools requiring command-line knowledge and understanding of YAML configuration files. This often leads to misconfiguration, delays, or even abandoning Kubernetes altogether. EasyKube automates and abstracts these processes, making Kubernetes accessible to everyone.

## Key Features & Objectives
- Comprehensive Automation: Securely and automatically deploy web pages, databases, and Python microservices using best practices.
- Simple Cluster Management: Deploy the ControlPlane container to your Kubernetes cluster and add it to your EasyKube profile for seamless, centralized management.
- Simplified Operations: Manage and monitor workloads with a friendly user interface, hiding Kubernetes’ intricacies.
- Automatic Backups: Integrated backup automation for databases.
- Integration with GitHub: Clone scripts and project files directly from GitHub repositories for easy application deployment.
- High Availability: Ensure that deployments leverage Kubernetes' native high-availability features and load balancing.
- Cost-Effective: Manage all workloads from a single location at a fixed price, saving on infrastructure costs while unifying cluster management.
- Scalable and Future-ready: The platform is designed to grow with new features, including upcoming extensions for Big Data and Artificial Intelligence use cases.

## Project Benefits
- Time Savings: Reduces configuration and deployment time through automation.
- Error Reduction: Limits human error by automating complex and repetitive procedures.
- Productivity Boost: Lets users focus on their core activities, not on Kubernetes’ technicalities.

## Where Can EasyKube Be Used?
EasyKube works seamlessly with Google Kubernetes Engine and on-premises Kubernetes clusters, adapting to various enterprise, educational, or personal needs.

## How to execute laravel in all addresses ##
php artisan serve --host=0.0.0.0 --port=8000

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>
