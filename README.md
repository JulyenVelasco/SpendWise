# SpendWise
### Web-Based Student Expense and Allowance Tracker System

## Project Description

SpendWise is a web-based expense and allowance tracking system designed to help college students manage their daily expenses and monitor their remaining allowance. It allows users to record expenses, organize spending by category, manage budgets, and monitor their financial activities through a dashboard.

The system aims to help students become more aware of their spending habits and avoid overspending before receiving their next allowance.

## Key Features

- **User Authentication** – User registration, login, and logout with password hashing and session management.
- **Dashboard** – Displays allowance information, total expenses, remaining balance, spending summaries, and recent transactions.
- **Allowance Management** – Allows users to set and monitor their allowance for a selected period.
- **Expense Management** – Allows users to add, view, edit, and delete expenses.
- **Custom Expense Categories** – Allows users to create, rename, and delete their own expense categories.
- **Expense Search and Filters** – Allows users to search and filter expenses by category and date.
- **Expense Export** – Allows users to download their expense records as a CSV file.
- **Charts and Visualization** – Presents spending information through a dashboard chart and category progress indicators.
- **Dark Mode** – Allows users to switch between light and dark themes, with their preference saved.
- **Local Storage** – Remembers expense search and filter selections.
- **Asynchronous Requests** – Uses JavaScript Fetch API to process selected expense and category actions without reloading the page.

## Technologies Used

- HTML5
- CSS3
- JavaScript
- PHP
- MySQL
- PDO (PHP Data Objects)
- XAMPP
- Visual Studio Code
- Git and GitHub

## Database

The system uses a MySQL database named `spendwise_db`.

The database contains the following main tables:

| Table | Description |
|---|---|
| `users` | Stores registered user information. |
| `allowances` | Stores users' allowance records. |
| `expenses` | Stores users' expense transactions. |
| `categories` | Stores customizable expense categories. |

## Installation and Setup

Follow these steps to run SpendWise locally:

1. Install **XAMPP** on your computer.
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Download or clone this repository.
4. Place the project folder inside the XAMPP `htdocs` directory.
5. Open phpMyAdmin through `http://localhost/phpmyadmin`.
6. Create a MySQL database named `spendwise_db`.
7. Import the project's database SQL file once it is included in the repository.
8. Configure your local `db.php` file with your MySQL database connection details.
9. Open your browser and visit:

   `http://localhost/spendwise/`

10. Register an account to begin using the system.

## Database Configuration

The `db.php` file contains the database connection settings. It is intentionally excluded from the public repository through `.gitignore` to prevent exposing local database credentials.

Users must configure their own local `db.php` file with the correct database host, database name, username, and password.

## Project Folder Structure

```text
spendwise/
│
├── .gitignore
├── index.php
├── login.php
├── register.php
├── logout.php
├── db.php
├── dashboard.php
├── allowance.php
├── expenses.php
├── expense_actions.php
├── edit_expense.php
├── delete_expense.php
├── categories.php
└── export_expenses.php
```

## Project Purpose

SpendWise was developed as an individual academic project for the Web Systems Technology course. It demonstrates the application of front-end and back-end web development concepts, database management, user authentication, CRUD operations, and modern web application features.

---

**Project Name:** SpendWise  
**Project Type:** Web Application Development  
**Target Users:** College Students  
**Database:** MySQL  
**Backend:** PHP