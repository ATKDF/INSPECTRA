# 🔍 Inspectra — Vehicle Rental & Inspection Platform

> Inspectra is a PHP-based rental and inspection management system designed for vehicle leasing companies, fleets, and rental agencies.

---

## 🚀 Project Summary

Inspectra provides a complete digital workflow for vehicle rental, booking, payment, inspection, damage reporting, and customer management.

- Public users can browse inventory, view vehicle details, and access informational pages.
- Registered customers can book vehicles, pay online, view bookings, and access inspection reports.
- Admins and agents can manage fleet inventory, bookings, inspections, payments, refunds, and notifications.

---

## ✅ Current Features

### Public / Guest Experience
- Browse vehicles and filter by category, price, or availability, with a live star-rating badge per vehicle
- View vehicle detail pages with descriptions, metadata, images, and published customer reviews
- Homepage hero, stats, and testimonials pull real data from the database (registered customers, fleet size, dispute resolution rate, actual reviews)
- Access FAQ, About, Terms, Privacy, and Contact pages — the Contact form persists to the database and notifies admins in-app and by email
- Redirects to registration/login for booking actions

### Customer Experience
- Customer registration, login, logout, password reset, and Google OAuth support
- Booking flow with pickup/return dates, total cost calculation, and deposit handling
- Online payment via Paystack and Stripe, with **flexible partial payments**: customers can pay any amount from the minimum deposit up to the full total, and a "Pay Remaining Balance" option stays available on the booking (whatever its status — paid, active, returned, or completed) until the full amount is settled
- Customer dashboard with booking history, payment history, inspections, refunds, and notifications
- Post-trip inspection comparison (pickup vs return), with the ability to accept a computed charge or file a dispute against it
- Refund request submission and status tracking
- Leave a star rating + written review after a completed trip; view admin responses to reviews
- Extended profile: phone, date of birth, address/city, ID document type & number, and emergency contact — alongside avatar and password management

### Admin / Agent Experience
- Admin dashboard with rental and revenue overview
- Fleet management for vehicles, vehicle photos, and status control
- Booking management including booking lifecycle, payment reconciliation, and a per-customer booking filter
- Inspection workflow with photo upload, damage reporting, notes, and signatures
- Post-trip charge resolution: approve, adjust, or waive the computed charge, and resolve any customer-filed dispute tied to it
- Dedicated Dispute Management list (filterable by status, searchable) linking into the per-booking resolution view
- Review moderation: publish/hide customer reviews and post a public response
- Contact message inbox: view full message details, track status (new/read/replied), and set internal notes
- Refund review and processing
- Customer management, including a full customer detail page (identification info, stats, recent bookings) and a booking list scoped to that one customer
- Audit Log covering major actions from **both** admins and customers — registrations, logins, bookings, payments, cancellations, reviews, disputes, refunds, vehicle changes, and account status changes — filterable by role and action
- System settings and notification management

### Inspection & Reporting
- Pickup and return inspection records with type tracking
- Inspection photo uploads and camera-enabled evidence collection
- Damage reports with type, severity, location, and optional image
- Digital signature capture for inspections
- Automated pickup-vs-return comparison that computes any damage/mileage/fuel charge
- Printable inspection reports and PDF-ready outputs

---

## 🧩 Functional Requirements

| Module | Requirement Description | Priority |
| --- | --- | --- |
| Public browsing | Browse inventory, view vehicle details, and access contact/faq pages without login | High |
| Authentication | Register, login, password reset, Google OAuth, logout | High |
| Vehicle booking | Create and manage bookings, calculate totals, apply deposits | High |
| Payment processing | Process payments via Paystack and Stripe, handle verification callbacks | High |
| Customer portal | Provide booking, payment, refund, inspection, and profile views | High |
| Admin portal | Manage vehicles, bookings, inspections, payments, refunds, and customers | High |
| Inspection workflow | Capture inspection photos, damage reports, notes, and signatures | High |
| Reporting | Generate inspection reports and provide printable summaries | Medium |
| Refund management | Submit, track, and approve/reject refund requests | Medium |
| Notifications | Notify users of booking, payment, inspection, and refund events | Medium |
| Reviews & ratings | Let customers rate/review completed trips; admin moderation and response | Medium |
| Dispute & charge resolution | Compute post-trip charges, let customers dispute them, admin resolves | High |
| Contact messages | Persist public contact-form submissions and notify admins | Medium |
| Settings management | Configure payment keys, email settings, and app parameters | Medium |
| Security | Prevent SQL injection, XSS, enforce RBAC, and secure sessions | High |
| Audit logging | Record major admin and customer actions across the platform | Medium |

---

## 📐 Non-Functional Requirements

- Security: session hardening via secure/httponly/samesite cookies, password hashing with bcrypt cost 12, role-based access control, and prepared statements for database queries. File uploads are filtered by allowed MIME types and extensions in helper utilities, but production should enforce HTTPS and stronger server-side content-type validation.
- Performance: the app uses responsive layout and optimized page structure with capped upload limits; however, no measured page-load or upload-time benchmarks are included in this repo. Real performance depends on deployment, asset delivery, and server configuration.
- Maintainability: the codebase is organized into shared includes and route modules, with reusable helpers for auth, DB access, and utilities. It is not a strict MVC framework, and some page scripts still contain business logic.
- Scalability: the database design uses normalized tables and indexed lookup fields. Current PHP deployment is monolithic, so scaling to higher concurrency will require infrastructure improvements such as session storage, caching, and horizontal web workers.
- Availability: payment verification and inspection completion flows use database transactions and webhook handling. Uptime targets are operational requirements rather than enforced by application code.
- Usability: the interface includes responsive design and mobile-friendly pages. User acceptance testing metrics, SUS scores, and timed workflow measurements are not yet captured in the repository.
- Compatibility: designed for PHP 8+ on MySQL/MariaDB with Apache/Nginx hosting, and uses responsive CSS and viewport support for modern browsers including Chrome, Firefox, and Samsung Internet.

---

## 📘 Academic Assessment of the Current Implementation

The current implementation demonstrates a strong alignment with the proposed design and methodology, particularly in the delivery of core functional modules such as authentication, booking, inspection capture, damage reporting, digital acknowledgment, and report generation. Evidence from the workspace shows that the system has progressed beyond a conceptual prototype to a working web application with structured PHP modules, role-based access control, database-backed inspection workflows, and responsive interfaces. However, the implementation remains only partially validated against the full methodological standard set out in the chapter. While the system provides functional support for the main workflow, the repository does not yet contain consolidated evidence of formal performance benchmarking, load testing for 100 concurrent sessions, user acceptance testing results, or System Usability Scale scores that would substantiate the stated non-functional targets. Consequently, the system should be regarded as functionally implemented and promising, but not yet fully verified against all of the measurable quality criteria proposed in the methodology.

---

## 🏛️ Architecture Pattern

Inspectra follows a modular PHP application architecture with the following characteristics:

- `config.php`: application constants, database credentials, payment keys, OAuth, SMTP, session settings. Never committed — copy `config.example.php` and fill in real values.
- `includes/bootstrap.php`: loads configuration, Composer autoload, the `DB` class, `Auth` class, and `helpers.php`, then starts the session.
- `includes/db.php`: the `DB` class — a PDO-based static data access layer (fetchOne/fetchAll/insert/update/transactions).
- `includes/auth.php`: authentication, authorization, session helpers, and role enforcement.
- `includes/helpers.php`: shared utilities, sanitization, and presentation helpers.
- `public/`, `auth/`, `customer/`, `admin/`, `api/`: separate route modules by user type and purpose.
- `assets/css/`: page-specific and shared styling assets.
- `uploads/`: persistent storage for vehicle media, inspection photos, and signatures.

This is a custom MVC-inspired architecture where page controllers and business logic are implemented in page scripts and shared services live in `includes/`.

---

## 🗂️ Current System Structure

```
inspectra/
├── admin/                   # Admin portal pages and inspection controls
├── api/                     # Service endpoints and webhook handlers
├── auth/                    # Login, registration, password reset, and OAuth
├── customer/                # Customer dashboard and portal pages
├── includes/                # Shared bootstrap, auth, DB, helpers, layout fragments
├── public/                  # Public website pages and vehicle browse/detail views
├── assets/                  # CSS, JS, and UI assets
├── uploads/                 # Uploaded media for vehicles and inspections
├── config.php               # Application configuration and secrets
├── database/                # The single complete schema file
├── README.md                # Project documentation
└── index.php                # Public homepage entry point
```

### Important folders
- `admin/`: management pages for vehicles, bookings, inspections, payments, refunds, customers, and settings.
- `api/`: AJAX and backend endpoints including auth, inspections, payments, notifications, receipts, refunds, and reports.
- `customer/`: customer portal views for bookings, payments, inspections, and notifications.
- `auth/`: authentication-related pages and callbacks.

---

## 🧠 Core Classes & Diagrams

### Core classes

| Class / Module | File | Responsibility |
| --- | --- | --- |
| `DB` | `includes/db.php` | PDO singleton, query execution, fetch helpers, insert/update, transactions |
| `Auth` | `includes/auth.php` | session handling, login/logout, role checks, user retrieval, password hashing |
| Shared helpers | `includes/helpers.php` | `log_audit()`, `create_notification()`/`queue_email()`, `render_stars()`, `get_vehicle_rating_summary()`, `compare_inspections()`, `status_badge()`, `paginate()`, and other cross-cutting utilities used by page scripts directly (no separate service classes) |
| Paystack / Stripe integration | `api/payments/*.php` | Payment initiation and webhook/verification handling, called directly via cURL against each provider's REST API |

There is intentionally no `Database`, `Audit`, `Notifications`, or `Paystack` class — earlier drafts of this
codebase had a second, incompatible implementation built around those classes and a different
config/bootstrap path; it was never wired into the live app and has since been removed. Everything
listed above is the one implementation actually in use.

### Class diagram (text)

```
[bootstrap.php] --> [config.php]
[bootstrap.php] --> [DB]
[bootstrap.php] --> [Auth]
[bootstrap.php] --> [helpers.php]
[Auth] --> [DB]
[helpers.php] --> [DB]
[page scripts (admin/customer/api/public)] --> [bootstrap.php]
```

---

## 🗄️ Core Database Design

### Main entities
- `users`: system accounts with roles `admin` and `customer`, including identification fields (date of birth, address, city, ID document, emergency contact)
- `vehicles`: fleet inventory metadata and pricing
- `vehicle_photos`: vehicle image gallery
- `bookings`: rental transactions, dates, pricing, and status
- `payments`: payment records linked to bookings (supports multiple partial payments per booking)
- `refund_requests`: refund lifecycle and admin processing
- `inspections`: pickup/return inspection sessions
- `inspection_photos`: evidence images for inspections
- `damage_reports`: reported damage details during inspection
- `signatures`: captured digital signatures for inspections
- `inspection_reports`: generated inspection report metadata
- `booking_charges`: the computed post-trip charge for a booking and its resolution (accepted/approved/adjusted/waived/disputed)
- `disputes`: a customer's dispute against a computed charge, with category/priority/status
- `dispute_messages`: messages and evidence attachments exchanged on a dispute
- `reviews`: a customer's rating (overall + cleanliness/service/value) and comment for a completed booking, plus optional admin response
- `contact_messages`: public contact-form submissions with status (new/read/replied) and internal notes
- `notifications`: in-app notification records
- `audit_logs`: recorded major admin and customer actions (who, what, when, before/after values)
- `settings`: app-wide configuration values

### Relationship overview
- `users` 1 → * `bookings` via `customer_id`
- `vehicles` 1 → * `bookings`
- `bookings` 1 → * `payments`
- `bookings` 1 → * `refund_requests`
- `bookings` 1 → 1+ `inspections` (pickup/return)
- `inspections` 1 → * `inspection_photos`
- `inspections` 1 → * `damage_reports`
- `inspections` 1 → * `signatures`
- `bookings` 1 → 1 `inspection_reports`
- `bookings` 1 → 1 `booking_charges`
- `bookings` 1 → * `disputes`, `disputes` 1 → * `dispute_messages`
- `bookings` 1 → 1 `reviews`
- `users` 1 → * `audit_logs`

### Indexing strategy
- `users`: indexes on `email`, `role`, `uuid`
- `vehicles`: indexes on `status`, `category`, `uuid`
- `bookings`: indexes on `status`, `customer_id`, `vehicle_id`, `uuid`, `booking_ref`
- `payments`: indexes on `booking_id`, `status`, `provider_reference`
- `inspections`: indexes on `booking_id`, `type`, `uuid`
- `email_verifications` and `password_resets`: indexed by token and email

These indexes are intended to accelerate portal queries, booking lookups, payment reconciliation, and inspection history searches.

---

## 🔁 Sequence Diagram

### Booking payment sequence

```
Guest -> Public browse -> select vehicle
Guest -> Login/Register -> Auth
Customer -> Vehicle detail -> submit booking
App -> bookings table -> create booking record
Customer -> Payment selection -> Paystack / Stripe
Gateway -> callback/webhook -> verify payment
App -> payments table -> store payment record
App -> bookings table -> update status paid
Admin/Agent -> inspection workflow -> create inspection record
```

### Inspection workflow sequence

```
Admin/Agent -> select booking -> inspections page
App -> inspections table -> create pickup/return inspection
Admin/Agent -> upload photos -> inspection_photos
Admin/Agent -> add damage -> damage_reports
Admin/Agent -> save signature -> signatures
Admin/Agent -> complete inspection -> update inspection status
App -> inspection_reports -> generate report record
Customer/Admin -> view report
```

### Post-trip charge & dispute sequence

```
App -> compare pickup vs return inspection -> compute charge (damage/mileage/fuel)
Customer -> view comparison -> accept charge OR file dispute
Customer -> file dispute -> disputes table -> booking_charges.status = disputed
App -> notify admins -> new dispute filed
Admin -> review dispute + evidence -> respond / change status
Admin -> finalize charge -> approve / adjust / waive -> booking_charges updated
App -> resolve any open dispute tied to the booking -> notify customer
```

---

## 🎯 Use Case Diagram

### Actors
- Guest
- Customer
- Admin

### Use cases
- Browse vehicles
- View vehicle details and published reviews
- Submit a contact message
- Register / login / logout
- Book vehicle
- Pay for booking (in full or in installments)
- View booking history
- Request refund
- Leave a review after a completed trip
- Dispute a post-trip charge
- Conduct inspection
- Upload inspection evidence
- Generate inspection report
- Manage fleet inventory
- Process refunds
- Resolve disputes and finalize charges
- Moderate reviews
- View a customer's identification details and booking history
- Manage system settings
- Review notifications and audit log

### Use case map (text)

```
[Guest] ---> (Browse vehicles)
[Guest] ---> (View vehicle details / reviews)
[Guest] ---> (Submit contact message)
[Guest] ---> (Register / Login)
[Customer] ---> (Book vehicle)
[Customer] ---> (Pay for booking / pay remaining balance)
[Customer] ---> (View booking history)
[Customer] ---> (Request refund)
[Customer] ---> (Leave a review)
[Customer] ---> (Dispute a charge)
[Customer] ---> (View inspection reports)
[Admin] ---> (Manage fleet inventory)
[Admin] ---> (Manage bookings)
[Admin] ---> (Conduct inspection)
[Admin] ---> (Process refunds)
[Admin] ---> (Resolve disputes / finalize charges)
[Admin] ---> (Moderate reviews)
[Admin] ---> (View customer details)
[Admin] ---> (Manage settings)
[Admin] ---> (Review audit log)
```

---

## 🛠️ Development Sprint Plan

### Sprint 1 — Core Platform
- Initialize project structure and config
- Build database connection and shared helpers
- Implement authentication and registration
- Build public vehicle listing and detail pages
- Create customer portal basics

### Sprint 2 — Booking & Payment
- Implement booking creation and lifecycle status
- Add Paystack and Stripe payment processing
- Build customer booking history and payment pages
- Add booking-related notifications

### Sprint 3 — Inspection Workflow
- Implement pickup/return inspection processes
- Add photo upload and damage report capture
- Add signature capture and inspection notes
- Generate inspection reports and admin review pages

### Sprint 4 — Admin Controls and Stabilization
- Add fleet, customer, payment, refund, and notification management
- Harden access control and security guards
- Polish responsive layouts and UX
- Add documentation, QA checks, and deployment notes

### Sprint 5 — Post-Trip Resolution, Reviews, and Trust Features
- Add automated pickup-vs-return charge computation, customer disputes, and admin resolution
- Add customer reviews/ratings, surfaced on vehicle pages and the homepage
- Add a working contact form with admin notification and a message inbox
- Add a real audit log covering major admin and customer actions
- Extend customer/admin profiles with identification and emergency contact fields
- Allow flexible partial/installment payments until a booking is fully paid
- Remove a dead, never-wired parallel implementation that had accumulated alongside the real one

---

## 🧭 Inspections Activity Diagram

1. Agent selects an active booking
2. Open inspection flow page
3. Choose inspection type (`pickup` or `return`)
4. Capture inspection photos and evidence
5. Add damage records and severity details
6. Record fuel level, mileage, and notes
7. Capture digital signature(s)
8. Save inspection data
9. Mark the inspection as completed
10. Generate and view inspection report

---

## 🔧 Setup Instructions

> For a full step-by-step walkthrough (prerequisites, troubleshooting, first-run smoke test), see
> **[INSTALL.md](INSTALL.md)**. The summary below covers the same ground more tersely.

### Dependencies
```bash
composer install
```

### Database
Import the full schema (this is the authoritative, up-to-date schema — includes bookings,
inspections, disputes, reviews, contact messages, and every other table the app uses):
```sql
mysql -u root -p inspectra_db < database/inspectra_db_complete_schema.sql
```
If you're catching up an existing, older database that's missing newer tables or columns, the
simplest fix is to drop it and re-import this file fresh.

### Configuration
- Copy `config.php` and update it with your own database, app URL, payment gateway, OAuth, and
  SMTP values — never commit real secrets to version control
- Confirm `APP_ENV` is `development` locally
- Upload paths (`uploads/vehicles/`, `uploads/inspections/`, `uploads/signatures/`, etc.) are
  created automatically on first upload; just make sure `uploads/` itself is writable

### Default accounts
- Admin: `admin@inspectra.com` / `Admin@1234`
- Customer: `kofi@example.com` / `Customer@1234`

> Note: replace default credentials and API keys before production.

---

## 🔐 Security Notes

- Uses PDO prepared statements for database operations
- Session cookies use secure, httponly, and samesite flags
- Passwords are hashed using bcrypt
- Role-based access control for admin and customer areas
- `.htaccess` protects sensitive paths and prevents direct execution

---

## 📌 Notes

The README reflects the current workspace structure and database schema in
`database/inspectra_db_complete_schema.sql`.
Review `includes/bootstrap.php`, `config.php`, `auth/`, `customer/`, `admin/`, and `api/` for implementation details.
