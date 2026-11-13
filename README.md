
# SpendWise
### Web-Based Student Expense and Allowance Tracker System

## Live Website

SpendWise is deployed online and accessible through the Internet.

**Live Demo:** https://spendwise-tracker.xo.je

**GitHub Repository:** https://github.com/JulyenVelasco/SpendWise

**Hosting Platform:** InfinityFree  
**Backend:** PHP  
**Database:** MySQL

The application allows college students to register, log in, manage their allowances, record expenses, customize expense categories, and monitor their spending through an interactive dashboard.

---

## Project Description

SpendWise is a web-based student expense and allowance tracking system designed to help college students manage their daily financial activities.

Students often spend their allowances on food, transportation, school supplies, dormitory essentials, and other personal needs. Without proper monitoring, they may overspend before receiving their next allowance.

SpendWise addresses this problem by providing a centralized platform where students can record expenses, organize spending into categories, set allowance periods, and monitor their remaining budgets.

The system encourages responsible spending habits, financial awareness, and better allowance management.

---

## Project Objectives

The main objective of SpendWise is to develop a functional, responsive, and secure web application that helps college students track their allowances and expenses.

Specifically, the system aims to:

1. Allow students to register and securely log in.
2. Provide an interactive dashboard displaying financial information.
3. Allow users to manage their allowance amounts and periods.
4. Enable users to add, view, edit, and delete expenses.
5. Allow users to create and manage custom expense categories.
6. Calculate total expenses and remaining allowance automatically.
7. Display spending information using charts and visual indicators.
8. Provide expense searching, filtering, and CSV exporting.
9. Support responsive viewing on desktop, tablet, and mobile devices.
10. Deploy the application online for Internet access.

---

## Target Users

The primary target users of SpendWise are college students who want to monitor their expenses and manage their allowances.

The system is especially useful for students who regularly spend money on:

- Food and beverages
- Transportation
- School supplies
- Grocery items
- Dormitory essentials
- Laundry
- Internet and utility bills
- Personal necessities

---

## Key Features

### 1. User Authentication

- User registration
- Secure login and logout
- Password hashing
- PHP session management
- Protected pages for authenticated users

### 2. Interactive Dashboard

- Total allowance
- Total expenses
- Remaining balance
- Today's expenses
- Budget status indicator
- Allowance spending progress
- Average daily spending
- Suggested daily spending budget
- Spending breakdown by category
- Recent expenses
- Student budgeting tips

### 3. Allowance Management

- Set allowance amount
- Select allowance start and end dates
- View allowance information
- Track spending within the active allowance period

### 4. Expense Management

- Add new expenses
- View expense history
- Edit existing expenses
- Delete expenses
- Assign expenses to categories
- Automatically update expense totals

### 5. Custom Expense Categories

- Create personalized categories
- Rename existing categories
- Delete custom categories
- Organize expenses based on student needs

### 6. Expense Search and Filtering

- Search expenses by description or category
- Filter expenses by category
- Filter expenses by date
- Clear selected filters

### 7. Expense Export

- Export expense records
- Download transaction history as a CSV file

### 8. Charts and Visualization

- Category spending donut chart
- Category spending progress bars
- Allowance usage progress indicator
- Budget status visualization

### 9. Dark Mode

- Switch between light and dark themes
- Save selected theme using browser localStorage
- Maintain theme preference across authenticated pages

### 10. Modern JavaScript Features

- DOM manipulation
- Event listeners
- JavaScript ES6+ syntax
- Fetch API
- Asynchronous requests
- Dynamic content updates
- Browser localStorage

---

## Technologies Used

### Frontend

- HTML5
- CSS3
- JavaScript ES6+
- Responsive Web Design

### Backend

- PHP
- PDO (PHP Data Objects)

### Database

- MySQL

### Development Tools

- Visual Studio Code
- XAMPP
- phpMyAdmin
- Git
- GitHub

### Cloud Deployment

- InfinityFree
- Hosted MySQL Database

---

## Modern Web Technologies Implemented

### 1. Web Storage API

The system uses localStorage to save user preferences such as dark mode and expense filter selections.

### 2. Fetch API and Asynchronous JavaScript

The application uses the JavaScript Fetch API to communicate with PHP backend endpoints for selected expense and category operations without requiring a full page reload.

### 3. Data Visualization

The dashboard uses charts, progress bars, and budget indicators to display financial information in an understandable format.

### 4. Responsive Web Design

CSS media queries and flexible layouts allow the application to adapt to different screen sizes.

---

## Database Design

SpendWise uses a MySQL relational database.

**Local Database Name:** `spendwise_db`

The database contains four main tables:

| Table | Description |
|---|---|
| `users` | Stores registered users and authentication information. |
| `allowances` | Stores allowance amounts and date periods. |
| `expenses` | Stores expense transactions associated with users. |
| `categories` | Stores user-created expense categories. |

The database structure is available in:

`database/database.sql`

Each user's financial records are associated with their account to keep information separated between users.

---

## Project Folder Structure

```text
spendwise/
|
|-- .gitignore
|-- README.md
|-- index.php
|-- login.php
|-- register.php
|-- logout.php
|-- dashboard.php
|-- allowance.php
|-- expenses.php
|-- expense_actions.php
|-- edit_expense.php
|-- delete_expense.php
|-- categories.php
|-- export_expenses.php
|
`-- database/
    `-- database.sql
```

**Note:** The `db.php` file is required to run the application but is intentionally excluded from the public GitHub repository to prevent exposing database credentials.

---

## Installation and Local Setup

Follow these steps to run SpendWise on a local computer.

### Step 1: Install XAMPP

Download and install XAMPP, which provides Apache, PHP, MySQL, and phpMyAdmin.

### Step 2: Start the Servers

Open XAMPP Control Panel and start:

- Apache
- MySQL

### Step 3: Download the Project

Clone the GitHub repository:

```bash
git clone https://github.com/JulyenVelasco/SpendWise.git
```

Alternatively, download the repository as a ZIP file and extract it.

### Step 4: Place the Project in htdocs

Move the project folder to:

```text
C:\xampp\htdocs\spendwise
```

### Step 5: Create the Database

Open:

http://localhost/phpmyadmin

Create a database named:

```text
spendwise_db
```

### Step 6: Import the SQL File

1. Select the `spendwise_db` database.
2. Click the Import tab.
3. Choose `database/database.sql`.
4. Click Import.

The database tables should be created.

### Step 7: Configure the Database Connection

Create a file named `db.php` inside the main `spendwise` folder.

For a typical local XAMPP setup, use:

```php
<?php
$host = "localhost";
$dbname = "spendwise_db";
$username = "root";
$password = "";

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("Unable to connect to the database.");
}
?>
```

Update these settings if your local MySQL configuration is different.

Do not commit passwords or private database credentials to GitHub.

### Step 8: Run the Application

Open:

http://localhost/spendwise/

The application should redirect to the login page.

### Step 9: Register an Account

Create an account and log in to start managing your allowances and expenses.

---

## Cloud Deployment

SpendWise is deployed using InfinityFree.

**Public URL:** https://spendwise-tracker.xo.je

The deployment uses:

- InfinityFree web hosting
- PHP for server-side processing
- MySQL for database storage
- phpMyAdmin for database administration

The application PHP files were uploaded to the hosting account's `htdocs` directory.

The database structure was imported into the hosted MySQL database, and the online `db.php` file was configured with the hosting provider's database connection details.

The deployed application supports registration, authentication, allowance management, expense management, custom categories, and dashboard reporting.

---

## Security Features

SpendWise implements several security measures:

- Password hashing
- Session-based authentication
- Protected application pages
- PDO prepared statements
- Input validation
- Output escaping
- CSRF protection for supported state-changing operations
- User-specific database queries
- Database credentials excluded from GitHub

These measures help protect user accounts and financial information.

---

## Responsive Design

SpendWise is designed for use on different devices, including:

- Desktop computers
- Tablets
- Mobile phones

The interface uses CSS media queries, flexible layouts, responsive cards, and horizontally scrollable tables where necessary.

---

## System Testing

The following system functions have been tested during development:

- User registration
- User login and logout
- Dashboard loading
- Allowance management
- Expense creation
- Expense editing
- Expense deletion
- Custom category management
- Expense search and filtering
- Dashboard financial calculations
- Dark mode switching
- CSV export
- Responsive layouts

The application was also tested after deployment to InfinityFree.

---

## Project Purpose

SpendWise was developed as an individual academic final project for the Web Systems Technology course.

The project demonstrates the integration of:

- HTML5
- CSS3
- JavaScript ES6+
- DOM manipulation
- Fetch API
- PHP backend development
- MySQL database integration
- CRUD operations
- User authentication
- Responsive Web Design
- Modern web technologies
- Git and GitHub version control
- Cloud deployment

The purpose of the project is to create a practical and accessible web application that helps college students improve their financial management habits.

---

## Project Information

**Project Name:** SpendWise  
**Project Type:** Web Application Development  
**Project Theme:** Student Expense and Allowance Tracking  
**Target Users:** College Students  
**Frontend:** HTML5, CSS3, JavaScript  
**Backend:** PHP  
**Database:** MySQL  
**Development Environment:** XAMPP and Visual Studio Code  
**Version Control:** Git and GitHub  
**Cloud Hosting:** InfinityFree  
**Project Category:** Individual Academic Final Project

---

## Repository

Source code is available at:

https://github.com/JulyenVelasco/SpendWise

The working web application is available at:

https://spendwise-tracker.xo.je
