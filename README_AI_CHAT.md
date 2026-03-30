# Laravel 11 AI Chat System with CRM Integration

A production-ready AI-powered chat system that connects to an external CRM API using OpenAI's GPT-4o-mini with function calling capabilities.

## Features

- ✅ AI-powered chat interface with OpenAI GPT-4o-mini
- ✅ Function calling (tool use) - NO SQL generation
- ✅ External CRM API integration with Bearer token authentication
- ✅ Three CRM tools: get_tasks, get_clients, get_deals
- ✅ Clean, modular, production-ready code
- ✅ Beautiful Blade UI with Tailwind CSS
- ✅ Estonian language responses
- ✅ Comprehensive error handling

## Architecture

### Services

#### `CRMService` (`app/Services/CRMService.php`)
Handles all communication with the external CRM API:
- `getTasks($params)` - Fetch tasks with optional status and limit filters
- `getClients($params)` - Fetch clients with optional search and limit filters
- `getDeals($params)` - Fetch deals/projects with optional client_id, status, and limit filters

#### `OpenAIService` (`app/Services/OpenAIService.php`)
Manages AI interactions:
- Sends messages to OpenAI with tool definitions
- Detects and handles tool calls
- Executes CRM API calls when tools are invoked
- Returns human-readable responses in Estonian

### Controller

#### `ChatController` (`app/Http/Controllers/ChatController.php`)
- Endpoint: `POST /api/chat`
- Validates user input
- Delegates to OpenAIService
- Returns JSON response

## API Endpoints

### POST /api/chat

**Request:**
```json
{
  "message": "Näita mulle kõik ootel ülesanded"
}
```

**Response:**
```json
{
  "reply": "Siin on ootel ülesanded: ..."
}
```

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# CRM API Configuration
CRM_API_URL=https://your-crm-api.com
CRM_API_TOKEN=your_bearer_token_here

# OpenAI Configuration
OPENAI_API_KEY=sk-your-openai-api-key
OPENAI_MODEL=gpt-4o-mini
```

### CRM API Endpoints

The system expects these endpoints on your CRM:

- `GET /api/tasks?status={status}&limit={limit}`
- `GET /api/clients?search={search}&limit={limit}`
- `GET /api/deals?client_id={id}&status={status}&limit={limit}`

All requests include:
```
Authorization: Bearer {CRM_API_TOKEN}
```

## Installation

1. **Install dependencies:**
```bash
composer install
```

2. **Configure environment:**
```bash
cp .env.example .env
php artisan key:generate
```

3. **Add your API credentials to `.env`:**
   - CRM_API_URL
   - CRM_API_TOKEN
   - OPENAI_API_KEY

4. **Run migrations:**
```bash
php artisan migrate
```

5. **Start the development server:**
```bash
php artisan serve
```

6. **Access the chat UI:**
   - Open browser: `http://localhost:8000/chat`

## Usage Examples

### Via API

```bash
curl -X POST http://localhost:8000/api/chat \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"message": "Näita mulle kõik kliendid"}'
```

### Via Web UI

1. Navigate to `/chat`
2. Type your question in Estonian
3. The AI will use appropriate tools to fetch data from CRM
4. Receive a human-readable response

## Tool Definitions

### get_tasks
Retrieves tasks from CRM.

**Parameters:**
- `status` (string, optional) - Filter by status (e.g., "pending", "completed")
- `limit` (number, optional) - Maximum number of results

### get_clients
Retrieves clients from CRM.

**Parameters:**
- `search` (string, optional) - Search term for client names
- `limit` (number, optional) - Maximum number of results

### get_deals
Retrieves deals/projects from CRM.

**Parameters:**
- `client_id` (number, optional) - Filter by specific client
- `status` (string, optional) - Filter by deal status
- `limit` (number, optional) - Maximum number of results

## System Prompt

The AI assistant operates with this system prompt:

> "You are a CRM assistant. You DO NOT write SQL. You MUST use provided tools. Deals mean projects in CRM context. Answer in Estonian."

This ensures:
- No SQL injection risks
- Proper tool usage
- Estonian language responses
- Context-aware terminology (deals = projects)

## Error Handling

The system handles various error scenarios:

1. **CRM API failures** - Returns friendly error message
2. **OpenAI API failures** - Returns fallback error message
3. **Network issues** - Logs errors and returns user-friendly response
4. **Invalid input** - Laravel validation handles bad requests
5. **Missing configuration** - Service initialization will fail gracefully

## Security Considerations

- ✅ Bearer token authentication for CRM API
- ✅ CSRF protection on web routes
- ✅ Input validation on all requests
- ✅ API keys stored in environment variables
- ✅ No SQL generation (uses tools only)
- ✅ Error messages don't expose sensitive data

## File Structure

```
app/
├── Http/
│   └── Controllers/
│       └── ChatController.php
└── Services/
    ├── CRMService.php
    └── OpenAIService.php

config/
└── services.php (CRM & OpenAI config)

resources/
└── views/
    └── chat.blade.php

routes/
├── api.php (POST /api/chat)
└── web.php (GET /chat)
```

## Testing

Test the API endpoint:

```bash
# Test with a simple question
curl -X POST http://localhost:8000/api/chat \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"message": "Tere! Kes sa oled?"}'

# Test with a tool-requiring question
curl -X POST http://localhost:8000/api/chat \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"message": "Näita mulle viimased 5 ülesannet"}'
```

## Troubleshooting

### "CRM API connection failed"
- Check `CRM_API_URL` is correct and accessible
- Verify `CRM_API_TOKEN` is valid
- Check CRM API is running

### "OpenAI API request failed"
- Verify `OPENAI_API_KEY` is valid
- Check OpenAI API status
- Ensure you have API credits

### Chat UI not loading
- Run `php artisan route:list` to verify routes
- Clear cache: `php artisan config:clear`
- Check browser console for errors

## Production Deployment

Before deploying to production:

1. Set `APP_ENV=production` in `.env`
2. Set `APP_DEBUG=false`
3. Run `php artisan config:cache`
4. Run `php artisan route:cache`
5. Set up proper logging and monitoring
6. Use HTTPS for all API communications
7. Implement rate limiting on `/api/chat` endpoint
8. Consider adding authentication for the chat UI

## License

This is a custom implementation for CRM integration.
