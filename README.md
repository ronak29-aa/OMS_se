# Order Management System — VS Code / XAMPP

## Features
- Customer self-registration with hashed passwords and login.
- Product browsing and a persistent-in-session cart; nothing is ordered until the customer confirms checkout.
- Checkout confirmation, address, Cash on Delivery or Online payment selection.
- Customer order history and status tracking: Pending, Accepted, Rejected, Processing, Shipped, Delivered, Cancelled.
- Admin/sales can filter orders by customer, status, and date and accept/reject orders in bulk.
- Shipping roles can bulk ship selected processing orders and mark shipped orders delivered.
- Reports: customer-specific or all-customer totals and daily/calendar-wise order summary for a date range.
- Admin can create staff accounts with limited roles: shipping only, reports only, or order confirmation only.

## Run in VS Code
1. Install XAMPP and VS Code.
2. Extract this folder to `C:/xampp\htdocs\oms_project`.
3. Start Apache and MySQL in XAMPP.
4. Open `http://localhost/phpmyadmin`, create/import the database by importing `database.sql`.
5. Visit `http://localhost/oms_project/setup.php` once to create the default admin, then delete `setup.php`.
6. Open `http://localhost/oms_project/`.
7. Admin login: `admin@oms.com` / `admin123`.
8. Customers use Register on the login page. Admin creates staff accounts under Staff & Users.

## Role permissions
- `admin`: all functions and staff creation.
- `sales`: order confirmation, customer list, order filters.
- `confirmation_staff`: accept/reject and mark processing.
- `shipping_staff` / `warehouse`: ship and mark delivered.
- `report_staff`: reports only.
- `customer`: own cart, checkout, own orders and status.

## Notes
Online payment is a demo option only; it does not connect to a payment gateway or collect money. Database import recreates the OMS tables and removes prior data in those tables, so back up an existing database before importing. This is an academic prototype; use HTTPS, CSRF protection, stronger deployment configuration and real payment integration before production use.
