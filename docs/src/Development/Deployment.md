# Production Deployment

This guide covers deploying HiveCache to production.
We use Coolify as an example, but the same principles apply to other Docker-based deployment systems.

## Deployment Method

This deployment method is Docker-based.

## Infrastructure Requirements

### Step 1: Database Setup

Deploy PostgreSQL 16 in your infrastructure:
- Use `postgres:16` image
- Make it accessible in the internal Docker network
- Configure persistent storage for the database

### Step 2: Build API Production Image

Build the API production image:

1. Define all required environment variables (see `.env` file in the repository for a list)
2. Build the image:
   ```bash
   docker build -t hivecache --target api-build -f infrastructure/docker/services/php/Dockerfile .
   ```
3. Start this image with the required environment variables

### Step 3: JWT Key Pair Generation

Generate a JWT key pair for authentication:

1. Run the Symfony console command:
   ```bash
   bin/console lexik:jwt:generate-keypair
   ```
2. Save the generated files to the server:
   - `/api/config/jwt/public.pem`
   - `/api/config/jwt/private.pem`

### Step 4: Expose the API

- If using Coolify: Expose the API on port 80
- If deploying on your own: Expose the `api:80` port to the internet via a reverse proxy/router (e.g., Nginx, Traefik, or Cloudflare)

## Environment Variables

Ensure all required environment variables are set. Key variables include:

- Database connection settings
- JWT configuration
- Application environment (prod)
- ActivityPub instance URL
- File storage configuration
- And others as defined in the `.env` file

## Additional Considerations

- SSL/TLS: Set up HTTPS using a reverse proxy or load balancer
- File Storage: Configure persistent storage for uploaded files and archives
- Backups: Set up regular database backups
- Monitoring: Consider adding monitoring and logging solutions
- Scaling: The current implementation is designed for up to 1000 users per instance to encourage decentralization

> [!NOTE]
> The system is designed for max 1000 users per instance to encourage decentralization.
