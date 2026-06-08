# Setup

## Requirements

- Docker Desktop / Docker Engine with Compose support
- Git

## Steps

1. Clone the repository and change into it.
2. Copy the local environment file:

```bash
cp .env.example .env
```

3. Start the containers:

```bash
docker compose up -d
```

4. Seed WordPress, activate the plugin, and load the demo landing page:

```bash
docker compose --profile setup run --rm wp-setup
```

5. Open the site and admin:

- Site: `http://localhost:8080`
- Admin: `http://localhost:8080/wp-admin`

6. Login with:

- Username: `admin`
- Password: `admin`

## What I verified / intended to verify

- Homepage renders at `http://localhost:8080`
- Admin renders at `http://localhost:8080/wp-admin`
- Agency Client Plugin is active
- Partner feed is visible
- Newsletter form is visible
- Sample Case Studies exist in wp-admin

## Environment note

I used the provided Docker setup and did not add any extra local services.

Working sequence on June 8, 2026:

1. `cp .env.example .env`
2. `docker compose up -d`
3. `docker compose --profile setup run --rm wp-setup`

The first `docker compose up -d` attempt hit a transient Docker Hub `504 Gateway Timeout`, but a retry completed successfully. The setup command then installed WordPress, activated `agency-client-plugin`, seeded sample case studies, and created the landing page.
