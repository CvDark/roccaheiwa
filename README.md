# Roccaheiwa Smart Locker System v2.0

![Version](https://img.shields.io/badge/version-2.0-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![Author](https://img.shields.io/badge/author-Opall-orange)

A modern, dual-mode smart locker management platform designed for both commercial businesses and educational institutions. **Roccaheiwa** provides a secure, simple, and smart way to manage physical locker assets through a unified web interface.

---
### 🛠️ Developed by [Opall](https://opall.site)
This project is the original work of **Opall**. Unauthorized redistribution, commercialization without credit, or claiming this work as your own is strictly prohibited.
---

## 🌟 Key Features

### 🏢 Commercial Mode
Tailored for offices, gyms, hotels, and organizations requiring flexible user management.
- **Access Control:** Secure Email & Password authentication.
- **Smart Access:** Integrated QR Code system for locker operation.
- **Management:** Robust multi-user management dashboard.
- **Insights:** Detailed activity logs and usage analytics.

### 🎓 Institutional Mode
Designed for schools, colleges, and universities with high-volume student needs.
- **Hardware Integration:** Supports student card scanning (QR, Barcode, and NFC).
- **Flexibility:** Manual Student ID login for fallback access.
- **Admin Panel:** Centralized institutional control for administrators.
- **Efficiency:** Bulk locker assignment tools for semester rollouts.

## 🛠️ Technology Stack

- **Backend:** PHP 8.x
- **Frontend:** HTML5, Vanilla CSS3 (Custom Design System)
- **Database:** MySQL / MariaDB
- **Hardware Compatibility:** ESP32, PN532 NFC Reader, QR Scanners
- **Environment:** XAMPP / Apache

## 🚀 Getting Started

### Prerequisites
- PHP >= 7.4
- MySQL / MariaDB
- Apache Web Server (XAMPP recommended for local development)

### Installation

1. **Clone the Repository**
   ```bash
   git clone https://github.com/opall-repo/roccaheiwa.git
   cd roccaheiwa
   ```

2. **Database Setup**
   - Import `u632352078_commercial.sql` into your commercial database.
   - Import `u632352078_institution.sql` into your institution database.

3. **Configuration**
   - Configure your environment variables in the `.env` file located in the `institution/` directory.
   - Update `config.php` in both `commercial/` and `institution/` folders with your database credentials.

4. **Deployment**
   - Move the project folder to your web server root (e.g., `htdocs/`).
   - Access the system via `http://localhost/roccaheiwa`.

## 🔒 Security & Copyright
Roccaheiwa prioritizes data integrity and secure access. 

**Copyright © 2026 Opall. All rights reserved.**
This software is provided "as-is" under the MIT License. However, the branding and design assets are property of Opall. 

## 📄 License
This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---
*Made with ❤️ by [Opall](https://opall.site) · 2026*
