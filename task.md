You are a senior Laravel 11 architect and DevOps engineer.

Build a complete production-ready Laravel 11 application using Docker for a tool called "AI Client Finder".

Use latest Laravel 11 structure (no Kernel.php, bootstrap/app.php configuration).

--------------------------------------------------

1. Tech Stack:
- Laravel 11 (latest)
- PHP 8.2+
- MySQL 8
- Redis (queue + cache)
- Docker (apache + php-fpm + mysql + redis)
- OpenAI API (for AI email generation)
- SMTP (for sending emails)

--------------------------------------------------

2. Features:

- Keyword input (Blade UI)
- Lead Finder (Google scraping using HTTP client or Guzzle)
- Website scraper (fetch HTML)
- Email extractor (regex)
- AI outreach generator (OpenAI API)
- Email sender (Laravel Mail + Queue)
- Lead status tracking:
    - new
    - sent
    - failed
    - replied
- CSV export

--------------------------------------------------

3. Architecture (IMPORTANT):

Use Service-based architecture:

- app/Services/LeadFinderService.php
- app/Services/ScraperService.php
- app/Services/EmailExtractorService.php
- app/Services/AIService.php
- app/Services/EmailSenderService.php

Use Dependency Injection everywhere.

--------------------------------------------------

4. Database:

Create migration for "leads" table:

- id
- company_name
- website
- email
- linkedin (nullable)
- status (default: new)
- created_at
- updated_at

--------------------------------------------------

5. UI (Blade):

Create simple UI:

- Input field (keyword)
- Button: "Find Leads"
- Table:
    - Company
    - Website
    - Email
    - Status
    - Action (Send Email)

--------------------------------------------------

6. Routes:

- GET /
- POST /search
- POST /send-email/{id}

--------------------------------------------------

7. Controllers:

- LeadController:
    - index()
    - search()
    - sendEmail()

--------------------------------------------------

8. Artisan Commands:

- php artisan leads:find {keyword}
- php artisan emails:send

--------------------------------------------------

9. Email System:

- Use Laravel Mail
- Queue emails using Redis
- Add delay between emails (30–60 sec)
- Retry failed jobs

--------------------------------------------------

10. Docker Setup:

Create full Docker environment:

- docker-compose.yml
- apache
- php-fpm (PHP 8.2)
- mysql
- redis

Expose ports using .env variables.

--------------------------------------------------

11. .env.example:

Include:

- DB config
- Redis config
- Mail config
- OpenAI API key

--------------------------------------------------

12. Code Quality:

- Clean code
- No fake/dummy logic
- Working implementation
- Proper folder structure
- Comments where needed

--------------------------------------------------

IMPORTANT:

Generate FULL WORKING PROJECT:

- Folder structure
- All files
- Controllers
- Services
- Migrations
- Blade UI
- Docker config

Do NOT skip anything.
Do NOT give pseudo code.