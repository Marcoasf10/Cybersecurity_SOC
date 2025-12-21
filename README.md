# VCard

VCard is a **reactive web application** inspired by services like **MBWay**, built with **Laravel** and **Vue.js**.
It allows users to manage money, track expenses, and send or request payments, while administrators can monitor platform usage via dashboards.

---

## 🏗 Project Components

The project is divided into three main parts:

| Component | Technology    | Purpose                                                             |
| --------- | ------------- | ------------------------------------------------------------------- |
| Backend   | Laravel (PHP) | REST API, authentication, business logic, OAuth2 (Laravel Passport) |
| Frontend  | Vue.js + Vite | Reactive UI, charts, dashboards                                     |
| Database  | MySQL         | Users, transactions, logs                                           |

The entire stack runs using **Docker Compose** to ensure a consistent development environment.

---

## ⚙️ Project Setup (Development)

### Prerequisites

* [Docker](https://www.docker.com/get-started)
* [Docker Compose](https://docs.docker.com/compose/)

---

### 1️⃣ Clone the repository

```bash
git clone https://github.com/Marcoasf10/Cybersecurity_SOC.git
cd Cybersecurity_SOC
```

---

### 2️⃣ Initial backend setup

Before starting Docker for the first time, install PHP dependencies in the backend:

```bash
cd backend
composer install
cd ..
```

This ensures the `vendor` folder is created.

---

### 3️⃣ Start the project

Run the full stack (backend, frontend, websocket server, and MySQL):

```bash
docker compose up
```

Or in detached mode:

```bash
docker compose up -d
```

This will:

* Start Laravel (backend)
* Start MySQL
* Start the Vue.js frontend (Vite)
* Start the WebSocket server
* Run migrations and seeders
* Install and configure Laravel Passport
* Automatically create `.env` from `.env.example` if missing

⚠️ If you want to use a custom `.env` in `/backend`, it will be used instead.

Once you see the message `INFO Server running on...`, the application is ready.

---

### 4️⃣ Access the application

| Service               | URL                                            |
| --------------------- | ---------------------------------------------- |
| Backend (Laravel API) | [http://localhost](http://localhost)           |
| Frontend (Vue + Vite) | [http://localhost:5173](http://localhost:5173) |
| MySQL                 | localhost:3306                                 |

You can register a new user or log in using an admin account:

* Username: `a1@mail.pt`
* Password: `123`

---

### 5️⃣ Useful Docker Commands

| Command                                 | Description                      |
| --------------------------------------- | -------------------------------- |
| `docker compose up`                     | Start all services               |
| `docker compose up -d`                  | Start services in background     |
| `docker compose down`                   | Stop all services                |
| `docker compose down -v`                | Stop services and remove volumes |
| `docker compose logs -f`                | View logs                        |
| `docker compose exec laravel.test bash` | Enter backend container          |
| `docker compose exec frontend sh`       | Enter frontend container         |

---

## 🔐 Authentication

* Authentication is handled using **Laravel Passport (OAuth2)**
* Password Grant is enabled for API login
* Tokens are issued via `/oauth/token`

Passport is automatically installed when the containers start.

---

## 🧪 Development Notes

* Database is seeded automatically for testing
* Use `docker compose down -v` for a clean reset
* Logs are available at `storage/logs/laravel.log`
* Frontend runs in a dedicated Node.js container

---

## 📄 License

This project is for educational and internal use.