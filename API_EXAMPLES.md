# API Examples & Response Formats

## Endpoint: POST /api/chat

Base URL: `http://localhost:8000/api/chat`

### Example 1: Simple Question (No Tool Call)

**Request:**
```json
{
  "message": "Tere! Kes sa oled?"
}
```

**Response:**
```json
{
  "reply": "Tere! Olen CRM assistent. Saan aidata sul leida infot klientide, ülesannete ja projektide kohta. Küsi julgelt!"
}
```

---

### Example 2: Get All Tasks

**Request:**
```json
{
  "message": "Näita mulle kõik ülesanded"
}
```

**AI Process:**
1. Detects need for task information
2. Calls `get_tasks` tool with no parameters
3. Receives data from CRM API
4. Formats response in Estonian

**Response:**
```json
{
  "reply": "Siin on kõik ülesanded:\n\n1. Kliendi kohtumise ettevalmistus (Ootel)\n2. Projekti dokumentatsiooni uuendamine (Pooleli)\n3. Müügipakkumise koostamine (Valmis)\n\nKokku on 3 ülesannet."
}
```

---

### Example 3: Get Pending Tasks

**Request:**
```json
{
  "message": "Millised ülesanded on ootel?"
}
```

**AI Process:**
1. Detects need for filtered tasks
2. Calls `get_tasks` tool with `{"status": "pending"}`
3. CRM API called: `GET /api/tasks?status=pending`
4. Formats response

**Response:**
```json
{
  "reply": "Ootel on järgmised ülesanded:\n\n1. Kliendi kohtumise ettevalmistus\n2. Pakkumise saatmine kliendile XYZ\n\nKokku 2 ootel ülesannet."
}
```

---

### Example 4: Search for Clients

**Request:**
```json
{
  "message": "Otsi klienti nimega Acme"
}
```

**AI Process:**
1. Detects client search query
2. Calls `get_clients` tool with `{"search": "Acme"}`
3. CRM API called: `GET /api/clients?search=Acme`
4. Formats response

**Response:**
```json
{
  "reply": "Leidsin järgmised kliendid:\n\n1. Acme Corporation (ID: 42)\n   - Email: info@acme.com\n   - Telefon: +372 1234567\n\n2. Acme Solutions OÜ (ID: 89)\n   - Email: contact@acmesolutions.ee\n   - Telefon: +372 7654321"
}
```

---

### Example 5: Get Client's Deals

**Request:**
```json
{
  "message": "Millised projektid on kliendil number 42?"
}
```

**AI Process:**
1. Detects deal query with client ID
2. Calls `get_deals` tool with `{"client_id": 42}`
3. CRM API called: `GET /api/deals?client_id=42`
4. Formats response

**Response:**
```json
{
  "reply": "Kliendil number 42 (Acme Corporation) on järgmised projektid:\n\n1. Veebilehe arendus (Aktiivne)\n   - Väärtus: 15,000 EUR\n   - Algus: 2024-01-15\n\n2. CRM integratsioon (Läbirääkimised)\n   - Väärtus: 8,500 EUR\n   - Algus: 2024-03-01\n\nKokku 2 projekti."
}
```

---

### Example 6: Get Limited Results

**Request:**
```json
{
  "message": "Näita mulle viimased 3 klienti"
}
```

**AI Process:**
1. Detects need for limited client list
2. Calls `get_clients` tool with `{"limit": 3}`
3. CRM API called: `GET /api/clients?limit=3`
4. Formats response

**Response:**
```json
{
  "reply": "Siin on viimased 3 klienti:\n\n1. Tech Solutions OÜ\n2. Digital Marketing AS\n3. Startup Innovations\n\nKui soovid näha rohkem kliente, anna mulle teada!"
}
```

---

### Example 7: Complex Query (Multiple Parameters)

**Request:**
```json
{
  "message": "Näita mulle aktiivsed projektid, maksimaalselt 5 tükki"
}
```

**AI Process:**
1. Detects deal query with status and limit
2. Calls `get_deals` tool with `{"status": "active", "limit": 5}`
3. CRM API called: `GET /api/deals?status=active&limit=5`
4. Formats response

**Response:**
```json
{
  "reply": "Siin on aktiivsed projektid (max 5):\n\n1. E-poe arendus - Klient: ABC OÜ (25,000 EUR)\n2. Mobiilirakendus - Klient: XYZ AS (40,000 EUR)\n3. SEO optimeerimine - Klient: Digital Co (5,000 EUR)\n4. Brändingu uuendus - Klient: Fashion Ltd (12,000 EUR)\n5. Süsteemi audit - Klient: Security Inc (8,000 EUR)\n\nKokku väärtus: 90,000 EUR"
}
```

---

## Error Responses

### Validation Error

**Request:**
```json
{
  "message": ""
}
```

**Response (422):**
```json
{
  "message": "The message field is required.",
  "errors": {
    "message": [
      "The message field is required."
    ]
  }
}
```

---

### CRM API Error

**Request:**
```json
{
  "message": "Näita mulle ülesanded"
}
```

**Response (200):**
```json
{
  "reply": "Vabandust, CRM süsteemiga ühenduse loomisel tekkis viga. Palun proovi hiljem uuesti."
}
```

---

### OpenAI API Error

**Request:**
```json
{
  "message": "Tere!"
}
```

**Response (200):**
```json
{
  "reply": "Vabandust, tekkis viga AI teenusega suhtlemisel."
}
```

---

## CRM API Expected Format

Your CRM API should return data in these formats:

### GET /api/tasks
```json
[
  {
    "id": 1,
    "title": "Task name",
    "status": "pending",
    "due_date": "2024-03-30"
  }
]
```

### GET /api/clients
```json
[
  {
    "id": 42,
    "name": "Acme Corporation",
    "email": "info@acme.com",
    "phone": "+372 1234567"
  }
]
```

### GET /api/deals
```json
[
  {
    "id": 1,
    "client_id": 42,
    "title": "Project name",
    "status": "active",
    "value": 15000,
    "start_date": "2024-01-15"
  }
]
```

---

## Testing with cURL

```bash
# Test basic chat
curl -X POST http://localhost:8000/api/chat \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"message": "Tere!"}'

# Test with tool calling
curl -X POST http://localhost:8000/api/chat \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"message": "Näita mulle kõik kliendid"}'

# Test validation
curl -X POST http://localhost:8000/api/chat \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"message": ""}'
```

---

## Notes

- All responses are in Estonian as per system prompt
- AI automatically selects appropriate tools based on user question
- Multiple tools can be called in sequence if needed
- Error messages are user-friendly and in Estonian
- No SQL is ever generated - only tool calls to CRM API
