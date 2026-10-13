# Integrations
The application contains adapters rather than fake integrations.

## AI
Set `AI_ENABLED`, `AI_ENDPOINT`, `AI_API_KEY`, `AI_MODEL` in `.env`. The AI service returns `not_configured` until these exist.

## Voice
Set telephony credentials and a verified caller ID. The current adapter reports `not_configured` or `adapter_ready`; it does not fabricate a completed call.

## Email/SMS/WhatsApp
The communication table and service queue messages now. Provider adapters can be enabled later without changing workflow logic.

## Government/authority
Use `authority_records` and service-specific adapters. Never mark a submission as successful unless the provider response is actually received.
