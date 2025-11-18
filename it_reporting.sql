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
