# Real-Time Billing API Backend

A stateless REST API backend built with Laravel 13, Sanctum authentication, Redis queue workers, and Laravel Reverb for real-time WebSockets event broadcasting.

Looking for the frontend application? Visit the [Billing Frontend Repository](https://github.com/quitenoisemaker/billing-frontend).

---

## Technical Stack & Services

The system is fully containerized using Docker Compose and consists of the following services:

1. **`app`**: The Laravel PHP 8.3-FPM application container.
2. **`web`**: Nginx web server routing standard HTTP requests to `app` (exposed on host port `8000`).
3. **`db`**: MySQL 8.0 database service (exposed on host port `3306`).
4. **`redis`**: Redis server acting as the queue driver, cache store, and Reverb scale driver.
5. **`reverb`**: Laravel Reverb WebSocket server running on port `8080` (exposed on host port `8080`).
6. **`queue`**: Background worker running `php artisan queue:work redis` to handle transaction processing asynchronously.

---

## Docker Compose Setup & Run

### 1. Build and Start Services
Run the following command in the root folder to start all containers:
```bash
docker-compose up -d --build
```

### 2. Configure Environment and Run Migrations
Copy `.env.example` to `.env` (or configure your variables) and run the database migrations and seeder inside the `app` container:
```bash
# Generate App Key
docker-compose exec app php artisan key:generate

# Run DB Migrations
docker-compose exec app php artisan migrate

# Seed Test User
docker-compose exec app php artisan db:seed
```

The database seeder registers a default test customer:
- **Email**: `test@example.com`
- **Password**: `password`

---

## API Endpoints (v1)

All routes require a `Content-Type: application/json` header and are prefixed with `/api/v1`.

> [!NOTE]
> A Postman Collection file is included at the root of the project: `billing_api_postman_collection.json`. Import it into Postman to instantly test all endpoints. Logging in automatically extracts and updates the dynamic `{{token}}` variable.

### Authentication (Public)
- **`POST /api/v1/auth/register`**: Register a new user.
  - Payload: `{ "name": "John Doe", "email": "john@example.com", "password": "password123" }`
- **`POST /api/v1/auth/login`**: Authenticate and retrieve a Sanctum token.
  - Payload: `{ "email": "test@example.com", "password": "password" }`
  - Returns: `{ "access_token": "...", "token_type": "Bearer", "user": { ... } }`

### Protected Routes (Requires `Authorization: Bearer <token>`)
- **`GET /api/v1/user`**: Get current user details.
- **`GET /api/v1/balance`**: Get aggregated total of **successful** payments.
  - Returns: `{ "balance": 150.0 }`
- **`POST /api/v1/payments`**: Initiate a transaction.
  - Payload: `{ "amount": 10.50 }`
  - Returns: A `PaymentResource` representing a `pending` state transaction. This dispatches an asynchronous payment processor job which sleeps for 2 seconds and resolves to `successful`, `failed`, or `refunded`.
- **`GET /api/v1/payments`**: Retrieve transaction history.

---

## WebSocket & Real-Time Setup

Broadcasting is driven by **Laravel Reverb**.

### Client Authentication Hook
To listen to private channels, clients must hit the broadcast authorization endpoint with a valid Sanctum Bearer token:
- **Endpoint**: `POST /api/v1/broadcasting/auth`
- **Headers**:
  - `Authorization: Bearer <your_access_token>`
- **Payload**:
  - `{ "channel_name": "private-customer.<user_id>", "socket_id": "<socket_id>" }`

### Channels & Events
- **Channel**: `private-customer.{userId}` (Subscribing to channels other than the authenticated user's ID will return `403 Forbidden`).
- **Event**: `App\Events\PaymentStatusChanged`
  - Payload format:
    ```json
    {
      "paymentId": 1,
      "status": "successful",
      "timestamp": "2026-06-10T22:11:30+00:00",
      "customerId": 1
    }
    ```

---

## Trade-Offs & Architectural Decisions

### Why Queue?
* **Non-blocking API**: Decouples intensive tasks (like simulated payment gateway delays) from the HTTP lifecycle, preventing requests from hanging.
* **Better scalability**: Allows the API to handle high request volumes by offloading work to background worker processes.
* **Better user experience**: Returns immediate "pending" status to the user, who receives real-time updates when processing completes.

### Why Private Channels?
* **User isolation**: Ensures broadcast events are only dispatched to the client who owns the transaction.
* **Security**: Enforces strict authorization policies preventing users from listening to other users' private channels.

### Why Service Layer?
* **Separation of concerns**: Keeps controllers thin and focused on HTTP request validation and response formats, encapsulating business logic elsewhere.
* **Easier testing**: Allows domain/business logic to be unit tested in isolation without HTTP wrappers.

### Why Redis?
* **Fast queue processing**: Low-latency, high-throughput in-memory data store ideal for managing queues.
* **Reverb integration**: Out-of-the-box support that pairs efficiently with Laravel Reverb for real-time WebSocket state distribution.

---

## Verification & Testing

Tests run using an in-memory SQLite database (`:memory:`) to verify API security, channel authorizations, aggregation logic, and broadcast setups:

Run tests inside the application container:
```bash
docker-compose exec app php artisan test
```

Or run them locally (assuming PHP and SQLite are installed):
```bash
php artisan test
```
