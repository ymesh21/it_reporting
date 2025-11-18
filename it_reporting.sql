CREATE DATABASE it_reporting
    DEFAULT CHARACTER SET = 'utf8mb4';

USE it_reporting;

CREATE TABLE woredas (
    id INT PRIMARY KEY AUTO_INCREMENT, -- PK
    name VARCHAR(255) NOT NULL UNIQUE -- woreda name
);

CREATE TABLE training_categories (
    id INT PRIMARY KEY AUTO_INCREMENT, -- PK
    name VARCHAR(255) NOT NULL UNIQUE -- category name
);

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT, -- PK
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    sex ENUM('Male', 'Female') NOT NULL,
    district_id INT NOT NULL, -- FK
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    position VARCHAR(100),
    role ENUM('Zone', 'Woreda', 'Admin') NOT NULL,
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (district_id) REFERENCES woredas(id) ON DELETE RESTRICT
);

CREATE TABLE training_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT, -- PK
    district_id INT NOT NULL, -- FK
    category_id INT NOT NULL, -- FK
    title VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_by INT NOT NULL, -- FK (User who created the session)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (district_id) REFERENCES woredas(id) ON DELETE RESTRICT,
    FOREIGN KEY (category_id) REFERENCES training_categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE trainees (
    id INT PRIMARY KEY AUTO_INCREMENT, -- PK
    fullname VARCHAR(200) NOT NULL,
    gender ENUM('Male', 'Female'),
    phone VARCHAR(20),
    organization VARCHAR(255),
    session_id INT NOT NULL, -- FK
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE
);

CREATE TABLE devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(50) UNIQUE,
    name VARCHAR(150) NOT NULL,
    brand VARCHAR(100),
    model VARCHAR(100),
    serial_number VARCHAR(100) UNIQUE,
    device_type VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE maintenances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT UNSIGNED NOT NULL,
    user_id INT NULL,
    district_id INT NOT NULL,
    issue_description TEXT NOT NULL,
    action_taken TEXT,
    status ENUM('Pending','In Progress','Completed','Not Fixable') DEFAULT 'Pending',
    maintenance_date DATETIME NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_maintenance_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    CONSTRAINT fk_maintenance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_maintenance_district FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE CASCADE
);


