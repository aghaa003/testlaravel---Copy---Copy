# SPA ↔ Laravel route parity (see FRONTEND_BACKEND_PARITY.md for full audit)

Quick reference — **full checklist:** [FRONTEND_BACKEND_PARITY.md](./FRONTEND_BACKEND_PARITY.md)

## Latest additions

- `POST /api/users/points` — add points to logged-in user  
- `DELETE /api/courses/{course}/reviews/{review}` — **course creator only**  
- Public reads: `GET /lessons/{lesson}/comments`, `GET /lessons/{lesson}/likes`, `GET /community/posts/{post}/comments`

## Status: SPA coverage ✅

All paths used in `academy_clean/artifacts/academy/src` have matching Laravel routes as of this audit.
