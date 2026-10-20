# Multi-Tenant HRMS API 

A high-performance, multi-tenant backend API for a Human Resource Management System (HRMS), built to handle concurrent enterprise workloads, strict data isolation, and asynchronous processing. 

This project serves as a showcase of modern backend architecture, focusing on system design principles such as pessimistic database locking, background job queues, and advanced role-based access control.

## 🚀 Tech Stack
* **Framework:** Laravel 13 (API-only architecture)
* **Language:** PHP 8.3+ (Utilizing readonly classes, typed properties, and enums)
* **Testing:** Pest PHP (Strict TDD approach with focus on tenant isolation)
* **Database:** PostgreSQL / MySQL 
* **Caching & Queues:** Redis
* **Core Packages:** `spatie/laravel-multitenancy`, `spatie/laravel-permission`, `laravel/sanctum`

## 🧠 Engineering Challenges & Architectural Decisions

This API was built to solve specific bottlenecks common in multi-tenant SaaS environments:

### 1. Multi-Tenant Data Isolation (Single Database)
Rather than introducing the orchestration overhead of a database-per-tenant model, this API uses a strictly enforced single-database multi-tenant architecture. Every relevant table incorporates a `tenant_id` foreign key. Global scopes are applied at the Eloquent level to ensure that data bleeding across tenant boundaries is mathematically impossible, validated by a rigorous Pest test suite.

### 2. Concurrency & Row Locking (Leave Management)
To prevent race conditions where an employee might submit simultaneous leave requests for the same dates, the Leave Management domain utilizes **Pessimistic Locking** (`lockForUpdate()`). All leave balance deductions and schedule insertions are wrapped in strict database transactions to guarantee data integrity under high concurrent API loads.

### 3. Asynchronous Processing (Attendance Generation)
Heavy read/write operations, such as generating end-of-month attendance reports for hundreds of employees, are decoupled from the HTTP request lifecycle. The API dispatches these workloads to **Redis-backed Laravel Queues**, utilizing exponential backoff for failed jobs to ensure the API remains highly responsive.

### 4. Cache Optimization (Holidays & Configurations)
High-read, low-write queries (such as checking tenant-specific company holidays during leave validation) are cached in Redis. Cache invalidation is strictly scoped per tenant, preventing the "cache stampede" effect while bypassing unnecessary database load.

## 🔐 Role-Based Access Control (RBAC)
The API enforces strict hierarchical permissions via middleware:
* **System Admin:** Manages global infrastructure and provisions new tenants.
* **HR Admin (Tenant-Level):** Approves/rejects leave requests and views aggregate tenant attendance data.
* **Employee:** Restricted strictly to self-serve operations (requesting leave, viewing personal attendance).

## 🧪 Testing Strategy
Code quality is enforced via **Pest PHP**. The test suite focuses heavily on domain logic and boundary testing:
* **Tenant Isolation Tests:** Asserts that an HR Admin in Tenant A receives a `403 Forbidden` or `404 Not Found` when attempting to access an Employee record in Tenant B.
* **Concurrency Tests:** Mocks rapid, simultaneous API requests to verify database transaction rollbacks.
* **Feature Tests:** End-to-end testing of the JSON API payloads and HTTP status codes (201 Created, 422 Unprocessable Entity, etc.).

## 📚 API Documentation

### Core Endpoints
* `POST /api/auth/login` - Issue Sanctum token.
* `GET /api/leaves` - List leave requests (Scoped to user or tenant based on RBAC).
* `POST /api/leaves` - Submit a new leave request.
* `PUT /api/leaves/{id}/approve` - Approve/Reject a request (HR Admin only).
* `POST /api/attendance/generate` - Dispatch background report generation.

## 🛠️ Local Setup (Using Laravel Herd or Sail)

```bash
# 1. Clone the repository
git clone [https://github.com/jadeja/hrms-api.git](https://github.com/jadeja/hrms-api.git)
cd hrms-api

# 2. Install dependencies
composer install

# 3. Environment configuration
cp .env.example .env
php artisan key:generate

# 4. Run migrations and seed the database (includes default tenants and roles)
php artisan migrate --seed

# 5. Run the test suite
php artisan test
