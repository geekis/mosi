# Realtime & chat

**Globs:** `app/Events/**`, `app/Actions/Chat/**`, `config/reverb.php`,
`config/broadcasting.php`

Chat is built in-house because ~25% of staff have no Teams account. It is their only
messaging channel, so reliability matters more than features.

## Broadcasting

- Laravel Reverb over Redis. Reverb runs as a Forge daemon behind an Nginx WebSocket
  proxy — see `.ai/rules/deployment.md`.
- Every private and presence channel has an authorisation callback. **Channel names
  are guessable; authorisation is the only thing stopping a user from subscribing to
  someone else's conversation.** Test each callback.
- Broadcast events carry IDs and minimal payloads, never full model dumps — a
  broadcast payload is visible to every subscriber on the channel.
- Broadcasting is a delivery mechanism, not a source of truth. Persist first, then
  broadcast. A dropped socket must never mean a lost message.

## Chat model

- Conversations (DM and group), messages, read receipts, typing indicators, presence.
- Attachments via MediaLibrary, same validation as posts.
- Unread counts must be cheap to compute — denormalise a per-participant counter
  rather than counting messages on every page load.
- Messages are soft-deleted, never hard-deleted. Public-sector record-keeping.

## Notifications

The notification layer is channel-agnostic from the start: database, mail, broadcast,
and later web push and native push. Do not couple notification logic to the web
client — the Teams app and native app in v2 use the same events.

Digest emails render in the recipient's locale (`.ai/rules/localisation.md`).

## Offline reality

Rangers work in national parks with patchy coverage. Assume reconnects are frequent:
messages sent while offline must queue and retry, and the UI must show delivery state
honestly rather than optimistically claiming success.
