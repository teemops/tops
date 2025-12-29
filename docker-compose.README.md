# Docker Compose Setup

This directory contains a Docker Compose configuration for running MySQL locally during development.

## Quick Start

1. **Start MySQL**:
   ```bash
   docker-compose up -d
   ```

2. **Check MySQL status**:
   ```bash
   docker-compose ps
   ```

3. **View MySQL logs**:
   ```bash
   docker-compose logs -f mysql
   ```

4. **Stop MySQL**:
   ```bash
   docker-compose down
   ```

5. **Stop and remove volumes** (⚠️ This deletes all data):
   ```bash
   docker-compose down -v
   ```

## Environment Variables

Create a `.env` file in the root directory with the following variables:

```env
MYSQL_ROOT_PASSWORD=rootpassword
MYSQL_DATABASE=cloudsecurity
MYSQL_USER=cloudsecurity
MYSQL_PASSWORD=cloudsecurity
MYSQL_PORT=3306
```

Or use the provided `.env.example` as a template.

## Database Connection

The MySQL container exposes port 3306 by default. Your `DATABASE_URL` should be:

```
mysql://cloudsecurity:cloudsecurity@localhost:3306/cloudsecurity
```

## Data Persistence

MySQL data is stored in the `./mysql-data` directory, which is gitignored. This ensures:
- Data persists between container restarts
- Data is not committed to git
- Easy to reset by deleting the directory

## Prisma Migrations

After starting MySQL, run Prisma migrations:

```bash
cd backend
npx prisma migrate dev
```

Or generate Prisma client:

```bash
cd backend
npx prisma generate
```

## Accessing MySQL

You can connect to MySQL using any MySQL client:

```bash
# Using MySQL CLI
mysql -h localhost -P 3306 -u cloudsecurity -pcloudsecurity cloudsecurity

# Or using Docker
docker-compose exec mysql mysql -u cloudsecurity -pcloudsecurity cloudsecurity
```

## Troubleshooting

### Port Already in Use

If port 3306 is already in use, change `MYSQL_PORT` in your `.env` file:

```env
MYSQL_PORT=3307
```

Then update your `DATABASE_URL` accordingly.

### Reset Database

To completely reset the database:

```bash
docker-compose down -v
rm -rf mysql-data
docker-compose up -d
```

### Check MySQL Health

```bash
docker-compose exec mysql mysqladmin ping -h localhost -u root -prootpassword
```

