# VCard

VCard is a **reactive web application** inspired by services like **MBWay**, built with **Laravel** and **Vue.js**.  
It allows users to manage money, track expenses, and send or request payments, while administrators can monitor platform usage via dashboards.

---

## 🏗 Project Components

The project is divided into three main parts:

| Component  | Technology        | Purpose |
|-----------|-------------------|--------|
| Backend   | Laravel (PHP)     | REST API, authentication, business logic, OAuth2 (Laravel Passport) |
| Frontend  | Vue.js + Vite     | Reactive UI, charts, dashboards |
| Database  | MySQL             | Users, transactions, logs |

The entire stack runs using **Docker Compose** to ensure a consistent development environment.

---

## ⚙️ Project Setup (Development)

### Prerequisites

- [Docker](https://www.docker.com/get-started)
- [Docker Compose](https://docs.docker.com/compose/)

---

### 1️⃣ Clone the repository

```bash
git clone <REPO_URL>
cd VCard
```

---

### 2️⃣ Configure environment variables

Copy the example environment file:

```bash
cp .env.example .env
```

Edit `.env` as needed:

```env
APP_NAME=VCard
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=vcard
DB_USERNAME=sail
DB_PASSWORD=password
```

⚠️ The `DB_*` values **must match** the MySQL service defined in `docker-compose.yml`.

---

### 3️⃣ Start the project

To start the full stack (backend, frontend, database):

```bash
docker compose up
```

Or run in detached mode:

```bash
docker compose up -d
```

This will:

- Start Laravel (backend)
- Start MySQL
- Start the Vue.js frontend (Vite)
- Run migrations and seeders
- Install and configure Laravel Passport

---

### 4️⃣ Access the application

| Service   | URL |
|---------|-----|
| Backend (Laravel API) | http://localhost |
| Frontend (Vue + Vite) | http://localhost:5173 |
| MySQL                 | localhost:3306 |

---

### 5️⃣ Useful Docker Commands

| Command | Description |
|-------|-------------|
| `docker compose up` | Start all services |
| `docker compose up -d` | Start services in background |
| `docker compose down` | Stop all services |
| `docker compose down -v` | Stop services and remove volumes |
| `docker compose logs -f` | View logs |
| `docker compose exec laravel.test bash` | Enter backend container |
| `docker compose exec frontend sh` | Enter frontend container |

---

## 🔐 Authentication

- Authentication is handled using **Laravel Passport (OAuth2)**
- Password Grant is enabled for API login
- Tokens are issued via `/oauth/token`

Passport is automatically installed when the containers start.

---

## 🧪 Development Notes

- Database is seeded automatically for testing
- Use `docker compose down -v` for a clean reset
- Logs are available at `storage/logs/laravel.log`
- Frontend runs in a dedicated Node.js container

---

## 📄 License

This project is for educational and internal use.