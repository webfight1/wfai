# Quick Start Guide

## Setup (5 minutes)

### 1. Configure Environment Variables

Edit `.env` and add your API credentials:

```env
CRM_API_URL=https://your-crm-api.com
CRM_API_TOKEN=your_bearer_token_here
OPENAI_API_KEY=sk-your-openai-api-key
OPENAI_MODEL=gpt-4o-mini
```

### 2. Start the Server

```bash
php artisan serve
```

### 3. Test the System

**Option A: Web UI (Recommended)**
- Open browser: http://localhost:8000/chat
- Type a question in Estonian
- Example: "Näita mulle kõik kliendid"

**Option B: API Endpoint**
```bash
curl -X POST http://localhost:8000/api/chat \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"message": "Näita mulle viimased 5 ülesannet"}'
```

## Example Questions (in Estonian)

- "Näita mulle kõik ootel ülesanded"
- "Millised kliendid meil on?"
- "Otsi klienti nimega Acme"
- "Näita mulle aktiivsed projektid"
- "Millised on kliendi number 5 projektid?"

## How It Works

1. User asks a question in Estonian
2. OpenAI GPT-4o-mini analyzes the question
3. AI decides which tool(s) to use:
   - `get_tasks` - for task-related queries
   - `get_clients` - for client-related queries
   - `get_deals` - for project/deal-related queries
4. System calls CRM API with appropriate parameters
5. AI formats the data into a human-readable Estonian response
6. User receives the answer

## Troubleshooting

**"Vabandust, tekkis viga AI teenusega suhtlemisel"**
- Check OPENAI_API_KEY is valid
- Verify you have OpenAI API credits

**"Failed to fetch [resource] from CRM"**
- Verify CRM_API_URL is correct
- Check CRM_API_TOKEN is valid
- Ensure CRM API is accessible

**Chat UI not loading**
```bash
php artisan config:clear
php artisan route:clear
php artisan serve
```

## Architecture Overview

```
User Question (Estonian)
    ↓
ChatController (/api/chat)
    ↓
OpenAIService
    ↓ (analyzes & selects tools)
CRMService
    ↓ (calls external API)
External CRM API
    ↓ (returns data)
OpenAIService
    ↓ (formats response)
User receives answer (Estonian)
```

## Security Notes

- Never commit `.env` file
- Keep API keys secure
- Use HTTPS in production
- Implement rate limiting for production use

## Next Steps

1. Add authentication to `/chat` route
2. Implement conversation history
3. Add more CRM tools as needed
4. Set up monitoring and logging
5. Deploy to production

For detailed documentation, see `README_AI_CHAT.md`
